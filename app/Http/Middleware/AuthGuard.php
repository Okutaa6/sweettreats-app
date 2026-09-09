<?php

namespace App\Http\Middleware;

use App\Models\GroupAccess;
use App\Models\GroupPath;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthGuard
{
    /**
     * Segment yang di-skip dari RBAC — SATU-SATUNYA sumber kebenaran.
     * Dipakai juga oleh Set\KesehatanController (detektor landing) dan
     * Set\UserManagement (flaggedUserIds) — jangan bikin salinan baru.
     */
    public const SKIP_RBAC = ['export', 'api', 'auditlog', 'executive', 'fund-sources', 'finance',
        // petty-cash-categories: aksi CRUD kategori pengeluaran, dipakai DARI
        // halaman `fund-sources` (Kelola Wallet). Segmen-1-nya berbeda dari
        // halaman induknya, jadi tanpa baris ini RBAC memantulkannya ke landing
        // page — untuk SEMUA orang, Super Admin sekalipun, karena tak ada node
        // `access_path` berlink 'petty-cash-categories'. Gejalanya menipu:
        // bukan 403, melainkan 302 ke beranda seolah tombolnya tak berbuat apa
        // apa. Gerbang Super Admin sungguhannya ada di
        // PettyCashCategoryController::guard(), sama polanya dengan fund-sources.
        'petty-cash-categories'];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!session()->has('isLoggedIn')) { // check if user is logged in
            // "Ingat perangkat ini": sesi habis tapi cookie perangkat masih
            // hidup → bangun ulang sesi diam-diam, tanpa nyuruh login ulang.
            if (!app(\App\Services\DeviceAuth::class)->attempt($request)) {
                return redirect()->route('signin')->with('error', 'Silahkan login terlebih dahulu');
            }
        }

        $expires_in = session('expires_in');
        $current_time = time();

        if ($expires_in < $current_time) { // check if session is expired
            session()->flush();
            // Sesi idle kelamaan — tapi kalau perangkat diingat, hidupkan lagi.
            if (!app(\App\Services\DeviceAuth::class)->attempt($request)) {
                return redirect()->route('signin')->with('error', 'Sesi login telah berakhir, silahkan login kembali');
            }
        }

        // Get the first URI segment as controller identifier
        $controller = $request->segment(1);
        $group_id = session()->get('group_id');
        $uid = session('id');

        // ── Pusat Akun: wajib ganti password (must_change_password) ──
        // Selama flag hidup, giring ke halaman ganti-password (di luar grup ini).
        // 'signout' tetap boleh biar user bisa bail.
        if (session('must_change_password') && $controller !== 'signout') {
            return redirect()->route('ganti.password');
        }

        // ── Pusat Akun: last_seen (monitoring) + paksa-logout, throttle 60s ──
        // Satu SELECT + satu UPDATE per user tiap ≥60 detik aktivitas — murah.
        if ($uid && (time() - (int) session('pa_touch')) >= 60) {
            session(['pa_touch' => time()]);
            $u = \Illuminate\Support\Facades\DB::table('users')->where('id', $uid)->first(['force_logout_at']);
            if ($u) {
                // IT klik "Paksa Logout" → stempel > waktu login sesi ini → tendang.
                if ($u->force_logout_at && strtotime($u->force_logout_at) > (int) session('login_at')) {
                    session()->flush();
                    return redirect()->route('signin')->with('error', 'Sesi kamu diakhiri oleh IT. Silakan login kembali.');
                }
                \Illuminate\Support\Facades\DB::table('users')->where('id', $uid)->update(['last_seen_at' => now()]);
            }
        }

        // ── Cabang efektif: hitung ulang kalau TANGGAL sudah ganti ──
        // Cabang sesi diisi saat login (cabangSesiUntuk). Sesi cabang sering
        // hidup melewati tengah malam (tablet jarang logout), jadi rolling yang
        // mulai/berakhir hari ini tak akan kepakai kalau cuma mengandalkan
        // login. Satu query per sesi per HARI — murah.
        // Batas yang diterima sadar: rolling yang dibuat di tengah hari baru
        // berlaku setelah keluar-masuk sekali.
        if ($uid && session('branch_day') !== date('Y-m-d')) {
            session(['branch_day' => date('Y-m-d')]);
            $ub = \Illuminate\Support\Facades\DB::table('users')->where('id', $uid)->first(['raw_branch_id']);
            // Baris user hilang → JANGAN sentuh sesi. Menulis null di sini
            // sama saja memberi akses semua cabang secara diam-diam.
            if ($ub) {
                session(['raw_branch_id' => cabangSesiUntuk($uid, $ub->raw_branch_id)]);
            }
        }

        // Allow home route (/) without access check
        if ($controller === null || $controller === '') {
            // Refresh session expiry
            session(['expires_in' => time() + 3600]);
            return $next($request);
        }

        // Skip RBAC for utility routes (already behind auth middleware).
        // fund-sources: Super Admin gating dilakukan di controller-nya
        // sendiri (FundSourceController::guard).
        // finance: dashboard monitoring + approval top-up, di-guard di
        // FinanceDashboardController + TopupRequestController via canSeeAllBranches().
        if (in_array($controller, self::SKIP_RBAC)) {
            session(['expires_in' => time() + 3600]);
            return $next($request);
        }

        // Akses dicocokkan terhadap SEMUA awalan path sekaligus: 'a/b/c', 'a/b',
        // lalu 'a'. Cukup SALAH SATU cocok — ini pemeriksaan ADA/TIDAK, bukan
        // pemilihan node. Urutan $kandidat menggambarkan niat (spesifik dulu),
        // tapi kuerinya `whereIn` tanpa ORDER BY, jadi baris mana yang terambil
        // TIDAK dijamin. Aman selama hasilnya cuma dipakai `empty()` di bawah.
        // ⛔ Kalau suatu hari $groupAccess dibaca isinya — menentukan node aktif,
        // label, atau hak turunan — urutan itu WAJIB dipaksa dulu (ORDER BY
        // FIELD(link, …)), kalau tidak perilakunya berubah-ubah tanpa gejala.
        //
        // Sampai 9 Sep 2026 gerbang ini hanya membandingkan SEGMEN-1 URL dengan
        // `access_path.link`. Akibatnya setiap node bertingkat — 'absensi/kehadiran',
        // 'absensi/lembar-gaji', 'checklist/review', 'report/executive' — cuma bisa
        // dibuka role yang KEBETULAN juga memegang node segmen-1-nya. Grant yang
        // diberikan tepat sasaran justru mati: Executive diberi "Kehadiran" 9 Sep
        // lalu tetap dipantulkan, karena tak memegang "Upload Data". Gejalanya
        // menipu — 302 ke landing, BUKAN 403 — jadi terbaca seolah menunya rusak.
        // Pola yang sama pernah ditambal setengah lewat SKIP_RBAC untuk
        // 'petty-cash-categories'; ini menutupnya untuk semua node bertingkat.
        //
        // Memegang segmen-1 TETAP membuka seluruh isinya, jadi tak ada role yang
        // kehilangan akses. Yang bertambah hanya: node bertingkat yang sudah
        // di-grant kini benar-benar bisa dibuka. Diukur di prod sebelum naik —
        // tepat 2 baris yang berubah, dua-duanya Executive.
        $segmen   = $request->segments();
        $kandidat = [];
        for ($n = min(3, count($segmen)); $n >= 2; $n--) {
            $kandidat[] = implode('/', array_slice($segmen, 0, $n));
        }
        $kandidat[] = $controller;

        $groupAccess = GroupAccess::join('access_path', 'group_access.access_id', '=', 'access_path.id')
            ->where('group_id', $group_id)
            ->whereIn('link', $kandidat)
            ->first();
            
        if (empty($groupAccess)) { // user doesn't have access
            $landingPage = GroupPath::where('id', $group_id)->first();

            // Role user sudah dihapus / landing kosong → jangan fatal 500,
            // beri 403 yang jujur (kasusnya nyata: Group::destroy).
            if (! $landingPage || ! $landingPage->landing_page) {
                abort(403, 'Anda tidak memiliki akses ke halaman ini');
            }

            // landing_page = PATH (bisa multi-segmen). Anti redirect-loop
            // dibandingkan pakai SEGMENT-1-nya — kalau dibandingkan utuh,
            // landing 'absensi/recap' bikin loop redirect tanpa henti.
            if ($request->segment(1) === explode('/', $landingPage->landing_page)[0]) {
                abort(403, 'Anda tidak memiliki akses ke halaman ini');
            }

            return redirect()->to($landingPage->landing_page)->with('error', 'Anda tidak memiliki akses ke halaman tersebut');
        }

        // Refresh session expiry on successful access
        session(['expires_in' => time() + 3600]);

        return $next($request);
    }
}
