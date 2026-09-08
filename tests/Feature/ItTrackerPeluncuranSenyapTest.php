<?php

namespace Tests\Feature;

use App\Console\Commands\RebuildGroupAccess;
use App\Models\ItTicket;
use App\Services\ItTracker\ItTrackerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PELUNCURAN SENYAP IT Request Tracker ke prod (keputusan Ekky 9 Sep 2026):
 * dicapai dari DROP-UP profil (bukan sidebar), akses hanya IT + Executive +
 * Konsultan (+ admin), TANPA Head departemen.
 *
 * Yang dijaga — dengan ELEMEN, bukan kata:
 *  1. drop-up memuat tautan it-tracker; seksi "IT" HILANG dari sidebar
 *     (aturan shellMenuSections: induk yang isinya cuma personal dibuang);
 *     /it-tracker/katalog tetap terbuka (segmen sama).
 *  2. SQL 2026_09_09 mencabut grant Finance · Head Marketing · Admin:
 *     akun Finance → 302 ke landing (BUKAN 403 — 403 berarti segmen bertabrakan
 *     dengan landing, jebakan SKIP_RBAC), dan menunya nol jejak.
 *  3. Executive & Konsultan: papan terbuka, judul tiket TIDAK disensor
 *     (peran pengawas — sensor hanya untuk `head`).
 *  4. Seeder MEMAJUKAN tiket ANALISIS→DEV (#09 #10 #19), yang lain diam.
 *
 * Pohon menu dibangun dari DB (di dalam transaksi) lewat RebuildGroupAccess
 * dan ditaruh di Cache — TANPA menulis storage/app/group_access/*.txt, supaya
 * berkas lokal tak tercemar keadaan uji yang digulung balik.
 * Kelas terpisah — batas ±17 uji ber-DB per proses.
 */
class ItTrackerPeluncuranSenyapTest extends TestCase
{
    use DatabaseTransactions;

    private const SQL = 'database/sql/2026_09_09_it_tracker_akses_sementara.sql';
    private ItTrackerService $svc;
    private array $cacheDipakai = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);
        if (! DB::table('access_path')->where('link', 'it-tracker')->exists()) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_02_it_tracker_access.sql dulu (node it-tracker belum ada).');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->cacheDipakai as $k) Cache::forget($k);
        parent::tearDown();
    }

    // ── perkakas ──────────────────────────────────────────────────────────

    private function gid(string $nama): ?string
    {
        return DB::table('group_path')->where('nama', $nama)->value('id');
    }

    private function sesi(string $grup, string $uid): array
    {
        $gid = $this->gid($grup);
        if (! $gid) $this->markTestSkipped("group '$grup' tidak ada di DB ini");

        return ['isLoggedIn' => true, 'id' => $uid, 'group_id' => $gid, 'group_name' => $grup, 'name' => 'Uji ' . $grup,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time()];
    }

    /** grant seperti SQL 2026_09_02 untuk satu group — idempoten, digulung balik bersama transaksi */
    private function beriGrant(string $grup): void
    {
        $gid = $this->gid($grup);
        $node = DB::table('access_path')->where('link', 'it-tracker')->value('id');
        foreach ([$node, 'it-parent-0000000000000'] as $aid) {
            if (! $aid) continue;
            if (! DB::table('group_access')->where('group_id', $gid)->where('access_id', $aid)->exists()) {
                DB::table('group_access')->insert(['id' => 'uji-' . Str::random(16), 'group_id' => $gid, 'access_id' => $aid]);
            }
        }
    }

    /** pohon menu group dari DB SAAT INI (di dalam transaksi) → Cache, bukan berkas */
    private function pasangPohon(string $grup): void
    {
        $gid = $this->gid($grup);
        $m = new \ReflectionMethod(RebuildGroupAccess::class, 'buildTree');
        $m->setAccessible(true);
        $pohon = $m->invoke(app(RebuildGroupAccess::class), $gid);
        $key = 'group_access_' . $gid;
        Cache::put($key, $pohon, 300);
        $this->cacheDipakai[] = $key;
    }

    /** jalankan HANYA pernyataan tulis dari berkas SQL (SELECT ringkasan dilewati) */
    private function terapkanSqlSementara(): int
    {
        $n = 0;
        foreach (preg_split('/;\s*\n/', file_get_contents(base_path(self::SQL))) as $stmt) {
            $stmt = trim(preg_replace('/^--.*$/m', '', $stmt));
            if ($stmt === '' || stripos($stmt, 'SELECT') === 0) continue;
            DB::statement($stmt);
            $n++;
        }
        return $n;
    }

    private function potong(string $html, string $mulai, string $akhir): string
    {
        $a = strpos($html, $mulai);
        $this->assertNotFalse($a, "penanda '$mulai' tidak ada di halaman");
        $b = strpos($html, $akhir, $a);
        return substr($html, $a, $b === false ? null : $b - $a);
    }

    // ── 1. drop-up, bukan sidebar ─────────────────────────────────────────

    /** Executive: tautan IT Tracker ada di drop-up; seksi "IT" TIDAK ada di sidebar; katalog tetap terbuka. */
    public function test_executive_drop_up_memuat_it_tracker_dan_sidebar_tanpa_seksi_it(): void
    {
        $this->beriGrant('Executive');
        $this->pasangPohon('Executive');
        $ses = $this->sesi('Executive', 'uji-robi-senyap');

        $html = $this->withSession($ses)->get('/it-tracker')->assertOk()->getContent();

        // drop-up: ELEMEN <a class="sbx-drop-item" href=".../it-tracker"> dengan ikon FA5
        $dropup = $this->potong($html, 'id="sbxDropup"', 'id="sbxAccountBtn"');
        $this->assertMatchesRegularExpression('#<a class="sbx-drop-item" href="[^"]*/it-tracker">#', $dropup, 'tautan it-tracker di drop-up');
        $this->assertStringContainsString('fas fa-tasks', $dropup, 'ikon FontAwesome 5');
        $this->assertStringContainsString('IT Tracker', $dropup);

        // sidebar: TIDAK ada seksi "IT" dan TIDAK ada tautan it-tracker di nav
        $nav = $this->potong($html, '<nav class="sbx-menu">', '</nav>');
        $this->assertStringNotContainsString('<div class="sbx-sec-label">IT</div>', $nav, 'seksi IT hilang: induk yang isinya cuma personal dibuang');
        $this->assertDoesNotMatchRegularExpression('#<a class="sbx-item[^"]*" href="[^"]*/it-tracker"#', $nav, 'baris sidebar it-tracker tak ada');

        // segmen sama → katalog ikut terbuka tanpa node tambahan
        $this->withSession($ses)->get('/it-tracker/katalog')->assertOk()->assertSee('APA SAJA YANG KAMI PUNYA');
    }

    /** Peta personal: ikon harus nama FA5 (FA6 render jadi kotak kosong di stack ini). */
    public function test_ikon_drop_up_memakai_fontawesome_5(): void
    {
        $peta = shellPersonalMap();
        $this->assertArrayHasKey('it-tracker', $peta);
        [$label, $ikon] = $peta['it-tracker'];
        $this->assertSame('IT Tracker', $label);
        $this->assertSame('fas fa-tasks', $ikon);
        $this->assertDoesNotMatchRegularExpression('/fa-solid|fa-regular|fa-brands|fa-light/', $ikon, 'awalan FA6 dilarang');
    }

    // ── 2. akses sementara ────────────────────────────────────────────────

    /** SQL mencabut Finance · Head Marketing · Admin; sisanya utuh; jalan dua kali tetap sama. */
    public function test_sql_mencabut_tiga_group_dan_idempoten(): void
    {
        foreach (['Finance', 'Head Marketing', 'Admin', 'Executive', 'Head IT'] as $g) if ($this->gid($g)) $this->beriGrant($g);

        $pemegang = fn () => DB::table('group_access as ga')->join('group_path as gp', 'gp.id', '=', 'ga.group_id')
            ->join('access_path as ap', 'ap.id', '=', 'ga.access_id')->where('ap.link', 'it-tracker')
            ->pluck('gp.nama')->unique()->values()->all();

        $this->assertContains('Finance', $pemegang(), 'prasyarat: Finance memegang grant sebelum SQL');

        $this->assertGreaterThanOrEqual(2, $this->terapkanSqlSementara());
        $sesudah = $pemegang();
        foreach (['Finance', 'Head Marketing', 'Admin'] as $g) $this->assertNotContains($g, $sesudah, "$g dicabut");
        foreach (['Executive', 'Head IT'] as $g) $this->assertContains($g, $sesudah, "$g tetap");

        // induk "IT" ikut dicabut → tak ada section kosong
        $indukFinance = DB::table('group_access')->where('group_id', $this->gid('Finance'))
            ->where('access_id', 'it-parent-0000000000000')->count();
        $this->assertSame(0, $indukFinance);

        $this->terapkanSqlSementara();   // idempoten
        $this->assertSame($sesudah, $pemegang());
    }

    /** Finance sungguhan → 302 ke landing (bukan 403), dan menunya nol jejak IT Tracker. */
    public function test_finance_dipantulkan_ke_landing_dan_menunya_tanpa_jejak(): void
    {
        $this->beriGrant('Finance');
        $this->terapkanSqlSementara();
        $gid = $this->gid('Finance');
        $landing = DB::table('group_path')->where('id', $gid)->value('landing_page');
        $this->assertNotEmpty($landing, 'group Finance harus punya landing_page (di prod: ada)');
        $this->assertNotSame('it-tracker', explode('/', $landing)[0], 'landing tak boleh se-segmen — kalau sama, hasilnya 403 bukan 302');

        $ses = $this->sesi('Finance', 'uji-della-senyap');
        $res = $this->withSession($ses)->get('/it-tracker');
        $res->assertStatus(302);
        $res->assertRedirect(url($landing));

        // katalog: segmen sama → ikut terpantul
        $this->withSession($ses)->get('/it-tracker/katalog')->assertStatus(302);

        // menu dibangun dari DB sesudah SQL: nol jejak di sidebar maupun drop-up
        $this->pasangPohon('Finance');
        session($ses);
        $link = [];
        foreach (shellMenuSections() as $sec) {
            $this->assertNotSame('IT', $sec['label']);
            foreach ($sec['items'] as $it) $link[] = $it['link'];
        }
        foreach (shellAccountLinks() as $a) $link[] = $a['link'];
        $this->assertNotContains('it-tracker', $link, 'IT Tracker tak muncul di mana pun untuk Finance');
    }

    // ── 3. Executive & Konsultan: papan terbuka, tanpa sensor ─────────────

    /** Executive melihat judul tiket departemen lain apa adanya — sensor hanya untuk peran head. */
    public function test_executive_melihat_judul_tiket_tanpa_sensor(): void
    {
        $this->beriGrant('Executive');
        $this->pasangPohon('Executive');
        $judul = 'Rahasia Finance ' . Str::random(5);
        $t = $this->svc->buat(['judul' => $judul, 'sumber' => 'permintaan', 'jenis_permintaan' => 'Fitur baru',
            'prioritas_pemohon' => 'Tinggi', 'latar' => 'uji', 'kebutuhan' => 'uji', 'terdampak' => 'x'], 'uji-della-lain');

        $ses = $this->sesi('Executive', 'uji-robi-senyap');
        $this->withSession($ses)->get('/it-tracker')->assertOk()->assertSee($judul);
        $this->withSession($ses)->get('/it-tracker/katalog')->assertOk()
            ->assertSee($judul)->assertDontSee('Tiket departemen lain — isinya tidak ditampilkan');
        $this->withSession($ses)->get(route('it-tracker.show', $t->id))->assertOk()->assertSee($judul);
    }

    /** Konsultan Finance: sama seperti Executive (peran pengawas). Dilewati bila group-nya tak ada di DB ini. */
    public function test_konsultan_finance_sama_seperti_executive(): void
    {
        if (! $this->gid('Konsultan Finance')) $this->markTestSkipped('group Konsultan Finance tidak ada di DB ini');
        $this->beriGrant('Konsultan Finance');
        $this->pasangPohon('Konsultan Finance');
        $judul = 'Tiket lintas dept ' . Str::random(5);
        $this->svc->buat(['judul' => $judul, 'sumber' => 'permintaan', 'jenis_permintaan' => 'Fitur baru',
            'prioritas_pemohon' => 'Sedang', 'latar' => 'uji', 'kebutuhan' => 'uji', 'terdampak' => 'x'], 'uji-yovan-lain');

        $ses = $this->sesi('Konsultan Finance', 'uji-haickal-senyap');
        $html = $this->withSession($ses)->get('/it-tracker')->assertOk()->assertSee($judul)->getContent();
        $dropup = $this->potong($html, 'id="sbxDropup"', 'id="sbxAccountBtn"');
        $this->assertMatchesRegularExpression('#<a class="sbx-drop-item" href="[^"]*/it-tracker">#', $dropup);
    }

    // ── 4. seeder memajukan ANALISIS → DEV ────────────────────────────────

    /** Berkas yang mengubah fase ke DEV (RANK lebih tinggi) MEMAJUKAN tiket + PIC; tiket lain tak bergerak. */
    public function test_seeder_memajukan_analisis_ke_dev_dan_lainnya_diam(): void
    {
        if (! DB::table('users')->where('email', 'ikhsan@sweettreats.id')->exists()) {
            DB::table('users')->insert(['id' => 'uji-ikhsan-' . Str::random(6), 'nama' => 'Muhamad Ikhsan', 'email' => 'ikhsan@sweettreats.id',
                'password' => 'x', 'status' => 1, 'group_id' => $this->gid('Head IT') ?: 'grp-uji', 'created_at' => now(), 'updated_at' => now()]);
        }
        $ikhsan = DB::table('users')->where('email', 'ikhsan@sweettreats.id')->value('id');
        Artisan::call('it:seed-katalog', ['--env-guard' => 'local']);

        $baris = fn (string $fase, string $pic, string $masuk) => [
            ['90', 'Uji maju A', 'inisiatif', '', '', $pic, 'M', '3', '', 'P1', $fase, $masuk, 'aktif', '', '', '', 'uji', 'Pusat Akun'],
            ['91', 'Uji diam B', 'inisiatif', '', '', '', 'S', '1', '', 'P2', 'ANTRE', '2026-09-01 09:00', 'aktif', '', '', '', 'uji', ''],
        ];
        // 1) keadaan lama: ANALISIS tanpa PIC
        $this->assertSame(0, Artisan::call('it:seed-antrian', ['berkas' => $this->berkas($baris('ANALISIS', '', '2026-09-01 12:00')), '--env-guard' => 'local']));
        $a = ItTicket::where('seed_urut', 90)->firstOrFail();
        $b = ItTicket::where('seed_urut', 91)->firstOrFail();
        $this->assertSame('ANALISIS', $a->fase);
        $bSince = $b->fase_since;

        // 2) berkas diperbarui: DEV, PIC Ekky, masuk 10 Sep → seeder memajukan
        $this->assertSame(0, Artisan::call('it:seed-antrian', ['berkas' => $this->berkas($baris('DEV', 'ikhsan@sweettreats.id', '2026-09-10 09:00')), '--env-guard' => 'local']));
        $a = $a->fresh();
        $this->assertSame('DEV', $a->fase, 'RANK DEV > ANALISIS → dimajukan');
        $this->assertSame($ikhsan, $a->pic_user_id);
        $this->assertSame('2026-09-10 09:00', $a->fase_since->format('Y-m-d H:i'));
        $this->assertSame(0, DB::table('it_fr')->where('ticket_id', $a->id)->count(), 'FR TIDAK dikarang — Ekky mengisinya dari layar');

        // tiket lain tak bergerak
        $b = $b->fresh();
        $this->assertSame('ANTRE', $b->fase);
        $this->assertEquals($bSince, $b->fase_since);
    }

    private array $berkasDibuat = [];

    private function berkas(array $baris): string
    {
        $kepala = 'urut|judul|sumber|pemohon_email|pemohon_nama|pic_email|jenjang|bobot|prioritas_pemohon|'
            . 'prioritas_it|fase|masuk_at|status|alasan_tunda|parent_urut|deps_urut|latar|modul';
        $path = storage_path('app/uji-senyap-' . Str::random(8) . '.psv');
        file_put_contents($path, $kepala . "\n" . implode("\n", array_map(fn ($b) => implode('|', $b), $baris)) . "\n");
        $this->berkasDibuat[] = $path;
        register_shutdown_function(fn () => @unlink($path));
        return $path;
    }
}
