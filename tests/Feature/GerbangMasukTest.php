<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Gerbang masuk publik — pembatas percobaan & kebocoran lewat pesan galat.
 *
 * Brief: docs/handoff/handoff-security-hardening.md §3-§5.
 * Sifat risikonya steady-state, bukan regresi: sudah lama begini.
 *
 * Dua hal yang diuji di sini, dan yang kedua justru yang paling mudah
 * dirusak niat baik:
 *   1. Menebak sandi berulang HARUS terhenti.
 *   2. Terhentinya tidak boleh ikut mengunci ORANG LAIN di IP yang sama —
 *      satu cabang keluar lewat satu IP publik (NAT), jadi pagar per-IP murni
 *      akan menghukum sekantor gara-gara satu orang salah ketik.
 */
class GerbangMasukTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('masuk');
        \Illuminate\Support\Facades\Cache::flush();
    }

    /** Satu percobaan masuk dengan sandi asal-asalan. */
    private function coba(string $email, string $ip = '203.0.113.7')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/signin', ['email' => $email, 'password' => 'salah-sekali']);
    }

    /**
     * Akun sungguhan berumur pendek (ikut rollback transaksi uji) dengan sandi
     * yang kita tahu — supaya jalur masuk yang BERHASIL ikut teruji, bukan cuma
     * jalur gagal.
     */
    private function akunUji(string $sandi = 'sandi-benar-123'): object
    {
        $group = DB::table('group_path')->value('id');
        $this->assertNotNull($group, 'Butuh minimal satu role.');

        $id    = (string) \Illuminate\Support\Str::uuid();
        $email = 'uji.gerbang.' . substr($id, 0, 8) . '@contoh.invalid';

        DB::table('users')->insert([
            'id'         => $id,
            'nama'       => 'Uji Gerbang Masuk',
            'email'      => $email,
            'password'   => password_hash($sandi, PASSWORD_DEFAULT),
            'status'     => 1,
            'group_id'   => $group,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) ['id' => $id, 'email' => $email, 'sandi' => $sandi];
    }

    /* ═════════ REGRESI — yang paling mahal kalau rusak ═════════ */

    public function test_0_login_yang_BENAR_masih_jalan(): void
    {
        // Pagar anti-brute-force yang ikut menutup pintu bagi orang yang sandinya
        // benar bukan pengerasan, itu pemadaman. Ini uji pertama yang harus
        // dilihat kalau ada yang menyentuh berkas-berkas ini.
        $akun = $this->akunUji();

        $res = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.60'])
            ->post('/signin', ['email' => $akun->email, 'password' => $akun->sandi]);

        $res->assertRedirect();
        $this->assertTrue((bool) session('isLoggedIn'), 'Sesi harus terbentuk.');
        $this->assertSame($akun->id, session('id'));
        $this->assertNull($res->getSession()->get('error'));
    }

    public function test_0b_sandi_benar_tetap_diterima_meski_akun_LAIN_sedang_terkunci(): void
    {
        $ip   = '203.0.113.61';
        $akun = $this->akunUji();

        // Rekan sekantor menghabiskan jatahnya sampai terkunci…
        for ($i = 1; $i <= 6; $i++) {
            $this->coba('yang.lupa@sweettreats.id', $ip);
        }

        // …orang ini, dari IP yang sama, tetap harus bisa masuk.
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/signin', ['email' => $akun->email, 'password' => $akun->sandi])
            ->assertRedirect();

        $this->assertTrue((bool) session('isLoggedIn'),
            'Inti pilihan kunci email+IP: kelalaian satu orang tidak boleh mengunci cabangnya.');
    }

    /* ═════════════════════ §4 P0 — /signin ═════════════════════ */

    public function test_1_menebak_sandi_satu_akun_terhenti_sesudah_lima_kali(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->coba('target@sweettreats.id')
                ->assertRedirect();   // masih dilayani: 5 percobaan pertama lolos
        }

        // Ke-6 tidak lagi sampai ke pemeriksaan sandi.
        $this->coba('target@sweettreats.id')
            ->assertSessionHas('error', fn ($p) => str_contains($p, 'Terlalu banyak percobaan'));
    }

    public function test_2_yang_terkunci_HANYA_akun_itu_rekan_sekantor_tetap_bisa_mencoba(): void
    {
        // IP yang sama — ini justru keadaan normal di klinik: satu WiFi, satu
        // IP publik untuk semua orang.
        $ip = '203.0.113.9';

        for ($i = 1; $i <= 6; $i++) {
            $this->coba('yang.lupa@sweettreats.id', $ip);
        }

        $this->coba('yang.lupa@sweettreats.id', $ip)
            ->assertSessionHas('error', fn ($p) => str_contains($p, 'Terlalu banyak percobaan'));

        // Rekannya, dari IP yang SAMA, harus tetap dilayani.
        $this->coba('rekan.sebelah@sweettreats.id', $ip)
            ->assertSessionHas('error', fn ($p) => ! str_contains($p, 'Terlalu banyak percobaan'));
    }

    public function test_3_huruf_besar_kecil_tidak_bisa_dipakai_memutar_pembatas(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->coba('target@sweettreats.id');
        }

        // Kalau kuncinya mentah, "TARGET@..." dihitung orang lain dan penebak
        // dapat jatah baru cuma dengan menekan Caps Lock.
        $this->coba('TARGET@Sweettreats.id')
            ->assertSessionHas('error', fn ($p) => str_contains($p, 'Terlalu banyak percobaan'));
    }

    public function test_4_pesan_terkunci_berbahasa_indonesia_dan_menyebut_lamanya(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->coba('target@sweettreats.id');
        }

        $pesan = (string) $this->coba('target@sweettreats.id')->getSession()->get('error');

        // Halaman 429 bawaan Laravel berbahasa Inggris dan tanpa konteks — bagi
        // staf klinik itu terbaca seperti aplikasinya rusak.
        $this->assertStringContainsString('Terlalu banyak percobaan', $pesan);
        $this->assertMatchesRegularExpression('/\d+ (detik|menit)/', $pesan);
    }

    /* ═════════════════ §5 — kebocoran lewat pesan galat ═════════════════ */

    public function test_5_email_terdaftar_dan_tidak_terdaftar_dijawab_SAMA(): void
    {
        $adaEmail = DB::table('users')->whereNotNull('email')->where('email', '!=', '')
            ->value('email');
        $this->assertNotNull($adaEmail, 'Butuh minimal satu user untuk membandingkan.');

        $jawabAda = (string) $this->coba($adaEmail, '203.0.113.21')
            ->getSession()->get('error');
        $jawabTidak = (string) $this->coba('tidak.ada.sama.sekali@contoh.invalid', '203.0.113.22')
            ->getSession()->get('error');

        // Beda kalimat = halaman login berubah jadi alat memeriksa email siapa
        // saja yang terdaftar, tanpa perlu menebak satu sandi pun.
        $this->assertSame($jawabAda, $jawabTidak,
            'Pesan gagal masuk harus identik untuk email yang ada dan yang tidak.');
        $this->assertStringNotContainsString('tidak terdaftar', $jawabAda);
    }

    /**
     * Lupa-sandi tidak bisa diuji lewat HTTP dari sini: `_captcha()` memanggil
     * Google lewat Guzzle yang di-`new` langsung di dalam method (tak bisa
     * dipalsukan lewat container), dan kuncinya dibaca dengan `env()`. Uji HTTP
     * di sini hanya akan mengukur kegagalan captcha, bukan perilaku yang dimaksud.
     *
     * Jadi yang dijaga di sini bentuk KODENYA: cabang yang dulu menjawab beda
     * untuk email yang ada vs tidak sudah hilang, dan jawabannya bersyarat.
     * Ini pagar yang lebih lemah daripada uji perilaku — ditulis begitu supaya
     * tidak terbaca lebih kuat dari yang sebenarnya — tapi ia tetap menangkap
     * kalau seseorang mengembalikan pesan lamanya.
     */
    public function test_6_lupa_sandi_tidak_lagi_membocorkan_email_terdaftar(): void
    {
        $kode = file_get_contents(app_path('Http/Controllers/AuthController.php'));

        $this->assertStringNotContainsString("'Email not found'", $kode,
            'Pesan yang membedakan email terdaftar dan tidak, harus hilang.');
        $this->assertStringNotContainsString('New password has been sent to your email', $kode);
        $this->assertStringContainsString('Kalau email itu terdaftar', $kode,
            'Jawabannya harus bersyarat, dan sama untuk kedua keadaan.');
    }

    /* ═════════════════ §4 P0 — portal affiliate (PIN 6 digit) ═════════════════ */

    public function test_7_menebak_PIN_affiliate_terhenti_meski_berganti_ganti_IP(): void
    {
        // Lapis ber-IP saja tidak cukup untuk PIN 6 digit: penyerang tinggal
        // berganti alamat dan tiap alamat baru dapat jatah penuh lagi. Lapis
        // ketiga menempel pada NOMORNYA, lintas IP.
        for ($i = 1; $i <= 20; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.' . $i])
                ->post('/affiliate/login', ['phone' => '081234567890', 'pin' => '000000']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.250'])
            ->post('/affiliate/login', ['phone' => '081234567890', 'pin' => '000000'])
            ->assertSessionHas('error', fn ($p) => str_contains($p, 'Terlalu banyak percobaan'));

        // Dan nomor LAIN tidak ikut terkunci.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.251'])
            ->post('/affiliate/login', ['phone' => '089999999999', 'pin' => '000000'])
            ->assertSessionHas('error', fn ($p) => ! str_contains($p, 'Terlalu banyak percobaan'));
    }

    /* ═════════════════ §3 P1 — form publik ═════════════════ */

    public function test_8_perangkap_bot_menolak_reservasi_TERANG_TERANGAN(): void
    {
        $cabang = DB::table('raw_branch')->value('id');

        $res = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.41'])
            ->post('/reservasi', [
                'raw_branch_id'    => $cabang,
                'reservation_date' => now()->addDay()->toDateString(),
                'default_start'    => '10:00',
                'phone'            => '081234567890',
                'patients'         => [['name' => 'Bot', 'treatment_id' => 1]],
                'catatan_tambahan' => 'http://spam.example',   // kolom yang tak pernah dilihat manusia
            ]);

        $res->assertSessionHas('error');
        $this->assertStringContainsString('tidak bisa diproses',
            (string) $res->getSession()->get('error'),
            'Ditolak dengan pesan, BUKAN dibuang diam-diam sambil berpura-pura berhasil — '
            . 'orang sungguhan yang kena harus tahu bahwa jadwalnya tidak jadi.');
    }

    public function test_8b_perangkap_bot_TIDAK_menulis_satu_baris_log_untuk_tiap_percobaan(): void
    {
        // Temuan audit HUB: pagar yang dipasang untuk melindungi laravel.log
        // (kurungan di webhook) bisa dilewati lewat pintu sebelah — form publik
        // justru yang digedor bot, dan `throttle:5,1` masih membolehkan 5
        // baris/menit PER ALAMAT. Dari 50 alamat itu 360 ribu baris sehari, dan
        // laravel.log prod tidak berotasi (312 MB per 16 Sep).
        //
        // Berkas itu juga yang dibuka orang ketika ada yang rusak: membanjirinya
        // berarti menyembunyikan kejadian lain.
        Log::shouldReceive('warning')->once();

        $cabang = DB::table('raw_branch')->value('id');
        $kiriman = [
            'raw_branch_id'    => $cabang,
            'reservation_date' => now()->addDay()->toDateString(),
            'default_start'    => '10:00',
            'phone'            => '081234567890',
            'patients'         => [['name' => 'Bot', 'treatment_id' => 1]],
            'catatan_tambahan' => 'http://spam.example',
        ];

        for ($i = 0; $i < 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
                ->post('/reservasi', $kiriman)
                ->assertSessionHas('error');   // tetap DITOLAK tiap kali
        }
    }

    public function test_8c_kurungan_log_itu_per_alamat_bukan_global(): void
    {
        // Kalau kuncinya global, penyerang pertama memborong satu-satunya baris
        // dan serangan dari alamat lain jadi tak terlihat sama sekali.
        Log::shouldReceive('warning')->twice();

        $cabang = DB::table('raw_branch')->value('id');
        $kiriman = [
            'raw_branch_id'    => $cabang,
            'reservation_date' => now()->addDay()->toDateString(),
            'default_start'    => '10:00',
            'phone'            => '081234567890',
            'patients'         => [['name' => 'Bot', 'treatment_id' => 1]],
            'catatan_tambahan' => 'http://spam.example',
        ];

        foreach (['203.0.113.43', '203.0.113.44'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/reservasi', $kiriman);
        }
    }

    public function test_9_reservasi_publik_tidak_lagi_membolehkan_1200_kiriman_per_jam(): void
    {
        $rute = collect(app('router')->getRoutes())->first(
            fn ($r) => $r->uri() === 'reservasi' && in_array('POST', $r->methods(), true)
        );

        $this->assertNotNull($rute);
        $this->assertContains('throttle:5,1', $rute->gatherMiddleware(),
            '20/menit = 1.200 kiriman per jam dari satu alamat.');
    }
}
