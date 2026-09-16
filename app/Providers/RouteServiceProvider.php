<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->batasiPercobaanMasuk();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Open API vendor (Cekat dst) — grup web tanpa /api prefix,
            // CSRF dikecualikan di VerifyCsrfToken, auth via api.client.
            Route::middleware('web')
                ->group(base_path('routes/cekat.php'));
        });
    }

    /**
     * Pembatas percobaan masuk — /signin, /affiliate/login, /forgot-password.
     *
     * ⛔ KUNCINYA IDENTITAS + IP, BUKAN IP SAJA. Staf satu cabang keluar lewat
     * satu IP publik (NAT). Kalau dibatasi per-IP murni, satu orang yang salah
     * ketik lima kali akan **mengunci seluruh cabang** dari sistem — pagar yang
     * menghukum orang yang tidak bersalah, persis kegagalan yang membuat orang
     * berhenti memakai sistemnya.
     *
     * Jadi dua lapis:
     *   • per identitas+IP  → menebak SATU akun cepat terhenti;
     *   • per IP saja, longgar → menahan penyapuan banyak akun dari satu tempat
     *     (password spraying), tapi angkanya jauh di atas pemakaian jujur satu
     *     cabang, jadi orang normal tidak pernah menyentuhnya.
     *
     * Angkanya sengaja tidak galak: yang dilawan di sini bukan manusia yang lupa
     * password, tapi mesin yang mencoba ribuan kali.
     */
    private function batasiPercobaanMasuk(): void
    {
        // Sekali ketik salah = wajar. Lima kali semenit dari satu identitas =
        // bukan lagi orang yang mengingat-ingat.
        RateLimiter::for('masuk', fn (Request $request) => [
            Limit::perMinute(5)->by($this->kunciIdentitas($request))
                ->response($this->balasanTerkunci(...)),
            Limit::perMinute(60)->by('ip:' . $request->ip())
                ->response($this->balasanTerkunci(...)),
        ]);

        /*
         * Portal affiliate: kredensialnya PIN 6 DIGIT — sejuta kemungkinan,
         * kredensial terlemah yang kita punya. Pembatas ber-IP saja tidak cukup
         * di sini: penyerang tinggal berganti IP, dan tiap IP baru dapat jatah
         * penuh lagi. Karena itu ada lapis KETIGA yang menempel pada NOMORNYA
         * saja, lintas IP.
         *
         * Angkanya dipilih supaya dua-duanya benar:
         *   • 20 percobaan/jam per nomor → menghabiskan sejuta PIN butuh
         *     bertahun-tahun, jadi menebak tidak lagi jadi jalan;
         *   • tetap longgar untuk orang yang lupa PIN-nya sendiri, dan kalaupun
         *     seseorang iseng mengunci nomor orang lain, kuncinya lepas sendiri
         *     dalam sejam — bukan akun yang mati sampai admin turun tangan.
         */
        RateLimiter::for('masuk-affiliate', fn (Request $request) => [
            Limit::perMinute(5)->by($this->kunciIdentitas($request))
                ->response($this->balasanTerkunci(...)),
            Limit::perMinute(60)->by('ip:' . $request->ip())
                ->response($this->balasanTerkunci(...)),
            Limit::perMinutes(60, 20)->by('hp:' . Str::lower(trim((string) $request->input('phone'))))
                ->response($this->balasanTerkunci(...)),
        ]);

        // Lupa password: tidak ada yang sungguhan menekannya 3× dalam 10 menit,
        // dan tiap penekanan mengirim email atas nama kita.
        RateLimiter::for('lupa-sandi', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by($this->kunciIdentitas($request))
                ->response($this->balasanTerkunci(...)),
            Limit::perMinutes(10, 20)->by('ip:' . $request->ip())
                ->response($this->balasanTerkunci(...)),
        ]);

        // Tautan reset: tokennya sudah 128-bit, ini cuma menahan penggedoran.
        RateLimiter::for('reset-sandi', fn (Request $request) => Limit::perMinute(6)
            ->by('ip:' . $request->ip())
            ->response($this->balasanTerkunci(...)));

        // Pendaftaran affiliate: spam pendaftaran, bukan brute-force.
        RateLimiter::for('daftar-affiliate', fn (Request $request) => Limit::perMinutes(10, 3)
            ->by('ip:' . $request->ip())
            ->response($this->balasanTerkunci(...)));
    }

    /**
     * Identitas yang dipakai orang untuk masuk + IP-nya.
     *
     * `email` untuk /signin & /forgot-password, `phone` untuk portal affiliate
     * (masuknya pakai nomor HP + PIN). Ditransliterasi & dikecilkan supaya
     * "Budi@x.com" dan "budi@x.com" tidak dihitung sebagai dua orang berbeda —
     * kalau tidak, pembatasnya bisa dilewati cuma dengan mengubah huruf besar.
     */
    private function kunciIdentitas(Request $request): string
    {
        $id = (string) ($request->input('email') ?? $request->input('phone') ?? '');

        return Str::transliterate(Str::lower(trim($id))) . '|' . $request->ip();
    }

    /**
     * Balasan saat terkunci.
     *
     * Kembali ke formnya dengan pesan berbahasa Indonesia yang menyebut BERAPA
     * LAMA harus menunggu — halaman 429 bawaan Laravel berbahasa Inggris, tanpa
     * konteks, dan bagi staf klinik terbaca seperti aplikasinya rusak.
     */
    private function balasanTerkunci(Request $request, array $headers)
    {
        $detik = (int) ($headers['Retry-After'] ?? 60);
        $menit = (int) ceil($detik / 60);
        $lama  = $detik < 60 ? $detik . ' detik' : $menit . ' menit';

        $pesan = 'Terlalu banyak percobaan. Coba lagi dalam ' . $lama . '.';

        if ($request->expectsJson()) {
            return response()->json(['error' => $pesan], 429, $headers);
        }

        // Fallback eksplisit: `back()` polos jatuh ke '/' kalau request datang
        // tanpa Referer, dan pesannya ikut hilang ke halaman yang salah — pola
        // yang sama dengan kembali() di controller Finance.
        $cadangan = $request->is('affiliate/*') ? route('affiliate.login') : route('signin');

        return redirect()->back(302, [], $cadangan)
            ->withInput($request->except('password', 'pin', '_token'))
            ->with('error', $pesan);
    }
}
