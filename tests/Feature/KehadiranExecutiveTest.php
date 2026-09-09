<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Executive (Dani, Robi) membuka Kehadiran — DUA pagar, bukan satu.
 *
 * Keluhan 9 Sep 2026: grant menu "Kehadiran" sudah diberikan, tapi layarnya
 * tetap tak bisa dibuka. Sebabnya dua-duanya di KODE:
 *
 *   1. AuthGuard hanya mencocokkan SEGMEN-1 URL ('absensi') dengan
 *      access_path.link. Node 'absensi/kehadiran' karena itu mati kecuali
 *      role-nya kebetulan juga memegang 'absensi' (Upload Data). Gejalanya
 *      MENIPU: 302 ke landing, bukan 403.
 *   2. AbsensiKehadiranController::guard() memakai canSeeAllBranches() —
 *      helper yang juga membuka Upload fingerprint, Koreksi Absen, Periode
 *      Gaji, dan petty cash. Executive tak boleh masuk ke sana.
 *
 * Uji ini menjaga keduanya TERPISAH, plus satu hal yang gampang terlewat:
 * halaman Kehadiran merender deret tab ke Upload/Mapping/Koreksi/Periode —
 * pintu-pintu yang MENGUBAH data absensi. Executive tak boleh melihatnya.
 *
 * Grant Executive disisipkan sendiri di dalam transaksi, jadi kelas ini jujur
 * di environment mana pun (dev belum punya grant itu, prod punya) dan tak
 * meninggalkan jejak.
 */
class KehadiranExecutiveTest extends TestCase
{
    use DatabaseTransactions;

    private object $exec;      // group Executive (asli)
    private object $finance;   // user Finance (asli) — kendali pembanding
    private ?object $tanpaAbsensi = null;  // group tanpa grant absensi sama sekali

    protected function setUp(): void
    {
        parent::setUp();

        $exec = DB::table('group_path')->where('nama', 'Executive')->first(['id', 'nama', 'landing_page']);
        $this->assertNotNull($exec, 'group Executive tak ada di environment ini');
        $this->exec = $exec;

        $fin = DB::table('users as u')->join('group_path as g', 'g.id', '=', 'u.group_id')
            ->where('g.nama', 'Finance')->where('u.status', 1)
            ->first(['u.id', 'u.nama', 'u.group_id', 'u.raw_branch_id']);
        $this->assertNotNull($fin, 'butuh akun Finance aktif sebagai pembanding');
        $this->finance = $fin;

        $this->tanpaAbsensi = DB::table('group_path')->whereIn('nama', ['Front Office', 'Sales'])
            ->first(['id', 'nama', 'landing_page']);
        $this->assertNotNull($this->tanpaAbsensi, 'butuh satu role tanpa grant absensi');

        // Grant 'absensi/kehadiran' untuk Executive — di dalam transaksi.
        $this->pastikanGrant($this->exec->id, 'absensi/kehadiran');
    }

    private function pastikanGrant(string $groupId, string $link): void
    {
        $nodeId = DB::table('access_path')->where('link', $link)->value('id');
        $this->assertNotNull($nodeId, "node access_path '$link' tak ada");

        $ada = DB::table('group_access')->where('group_id', $groupId)->where('access_id', $nodeId)->exists();
        if (! $ada) {
            // group_access.id tak punya default — WAJIB diisi sendiri.
            DB::table('group_access')->insert([
                'id' => (string) Str::uuid(), 'group_id' => $groupId, 'access_id' => $nodeId,
            ]);
        }
    }

    /** @param array<string,mixed> $x */
    private function sesi(string $groupId, string $groupName, array $x = []): array
    {
        return array_merge([
            'isLoggedIn' => true,
            'id'         => $this->finance->id,
            'group_id'   => $groupId,
            'group_name' => $groupName,
            'name'       => 'Uji ' . $groupName,
            'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'),
            'expires_in' => time() + 3600,
            'login_at'   => time(),
            'pa_touch'   => time(),
        ], $x);
    }

    private function sesiExecutive(): array
    {
        return $this->sesi($this->exec->id, 'Executive');
    }

    private function sesiFinance(): array
    {
        return $this->sesi($this->finance->group_id, 'Finance', ['raw_branch_id' => $this->finance->raw_branch_id]);
    }

    // ── AuthGuard: path penuh dulu, baru segmen-1 ──────────────────────────

    /** Inti keluhannya. Bukan cuma kode 200 — halamannya harus benar-benar terbentuk. */
    public function test_executive_bisa_membuka_kehadiran(): void
    {
        $res = $this->withSession($this->sesiExecutive())->get('/absensi/kehadiran');

        $res->assertStatus(200);
        $html = $res->getContent();
        $this->assertStringContainsString('khExportForm', $html, 'formulir ekspor = penanda halaman Kehadiran sungguhan');
        $this->assertStringContainsString('kh2-', $html, 'kisi matriks kehadiran wajib terender');
        $this->assertStringNotContainsString('name="password"', $html, 'ini halaman login, bukan Kehadiran');
    }

    /**
     * Upload Data (segmen-1 'absensi') tetap TERTUTUP. Wajib 302 ke landing —
     * bukan 403 (itu berarti pagar lain yang bicara) dan jelas bukan 200.
     */
    public function test_executive_tetap_tertutup_dari_upload_data(): void
    {
        $res = $this->withSession($this->sesiExecutive())->get('/absensi');

        $res->assertStatus(302);
        $this->assertStringContainsString(
            (string) $this->exec->landing_page,
            (string) $res->headers->get('Location'),
            'harus dipantulkan ke landing-nya sendiri'
        );
    }

    /** Koreksi Absen MENGUBAH data absensi — Executive tak boleh sampai ke sana. */
    public function test_executive_tetap_tertutup_dari_koreksi_absen(): void
    {
        $this->withSession($this->sesiExecutive())->get('/absensi/koreksi')->assertStatus(302);
    }

    /**
     * Perilaku LAMA tak boleh rusak: memegang segmen-1 tetap membuka seluruh
     * isinya, termasuk path yang tak punya node sendiri ('absensi/koreksi').
     */
    public function test_grant_segmen_satu_tetap_membuka_seluruh_isinya(): void
    {
        $this->assertNull(
            DB::table('access_path')->where('link', 'absensi/koreksi')->value('id'),
            'uji ini baru bermakna kalau path itu memang TAK punya node sendiri'
        );

        $this->withSession($this->sesiFinance())->get('/absensi/koreksi')->assertStatus(200);
    }

    /** Role tanpa grant absensi sama sekali tetap dipantulkan. */
    public function test_role_tanpa_grant_dipantulkan_ke_landing(): void
    {
        $res = $this->withSession($this->sesi($this->tanpaAbsensi->id, (string) $this->tanpaAbsensi->nama))
            ->get('/absensi/kehadiran');

        $res->assertStatus(302);
        $this->assertStringContainsString(
            (string) $this->tanpaAbsensi->landing_page,
            (string) $res->headers->get('Location')
        );
    }

    /**
     * Anti-loop landing (AuthGuard) tetap hidup: kalau landing role justru ada
     * di klaster yang ditolak, jawabannya 403 — bukan pantulan tanpa henti.
     */
    public function test_anti_loop_landing_menjawab_403_bukan_memantul(): void
    {
        DB::table('group_path')->where('id', $this->tanpaAbsensi->id)
            ->update(['landing_page' => 'absensi/recap']);

        $this->withSession($this->sesi($this->tanpaAbsensi->id, (string) $this->tanpaAbsensi->nama))
            ->get('/absensi/kehadiran')
            ->assertStatus(403);
    }

    // ── Pagar BACA di controller ───────────────────────────────────────────

    /**
     * Pagar controller diuji TERPISAH dari AuthGuard: group_id-nya Finance
     * (jadi AuthGuard pasti lolos), yang diganti cuma group_name.
     */
    public function test_pagar_baca_menerima_executive_menolak_role_lain(): void
    {
        $sesi = $this->sesiFinance();

        $sesi['group_name'] = 'Executive';
        $this->withSession($sesi)->get('/absensi/kehadiran')->assertStatus(200);

        $sesi['group_name'] = 'Sales';
        $this->withSession($sesi)->get('/absensi/kehadiran')->assertStatus(403);
    }

    /** Ekspor = membaca data yang sama, jadi ikut pagar baca. */
    public function test_ekspor_kehadiran_ikut_pagar_baca(): void
    {
        $this->withSession($this->sesiExecutive())
            ->get('/absensi/kehadiran/export?from=' . date('Y-m-01') . '&to=' . date('Y-m-d'))
            ->assertStatus(200);
    }

    /**
     * ⛔ PENJAGA ARAH. Kalau suatu hari ada yang "merapikan" dengan menambahkan
     * Executive ke canSeeAllBranches(), uji ini merah — dan itu memang yang
     * diinginkan: helper itu juga membuka upload fingerprint, koreksi absen,
     * periode gaji, dan petty cash lintas cabang.
     */
    public function test_executive_TIDAK_boleh_masuk_canSeeAllBranches(): void
    {
        $this->withSession($this->sesiExecutive())->get('/absensi/kehadiran');

        session(['group_name' => 'Executive']);
        $this->assertFalse(canSeeAllBranches(), 'Executive tak boleh punya kuasa tulis lintas cabang');
        $this->assertTrue(canSeeKehadiran(), 'tapi tetap boleh MEMBACA Kehadiran');
    }

    /**
     * Executive tak punya cabang asal (raw_branch_id NULL). Layar ini
     * mengelompokkan per cabang ASAL, jadi peran tanpa cabang berisiko diam-diam
     * melihat SEBAGIAN data — dan halaman yang tampak normal dengan angka yang
     * kurang jauh lebih berbahaya daripada halaman yang menolak terbuka.
     */
    public function test_executive_melihat_data_yang_sama_dengan_finance(): void
    {
        $baris = function (array $sesi): int {
            $html = $this->withSession($sesi)->get('/absensi/kehadiran')
                ->assertStatus(200)->getContent();
            return substr_count($html, 'kh2-name');
        };

        $exec = $baris($this->sesiExecutive());
        $fin  = $baris($this->sesiFinance());

        $this->assertGreaterThan(0, $exec, 'Executive melihat halaman kosong — cek penyaringan cabang');
        $this->assertSame($fin, $exec, 'Executive tak boleh melihat lebih sedikit baris daripada Finance');
    }

    /**
     * PROD-ONLY, dipentaskan di sini: di prod Executive JUGA dipegangi grant
     * 'absensi/recap'. Sesudah AuthGuard mencocokkan path penuh, gerbang itu
     * lolos dan yang menjawab jadi pagar controller — 403, bukan lagi 302.
     *
     * Dev tak bisa memperagakannya sendiri (grant itu sengaja tak disemai di
     * sana), jadi grantnya disisipkan di dalam transaksi. Uji ini yang membuat
     * pertanyaan terbukanya terlihat di kode, bukan cuma di catatan.
     */
    public function test_grant_recap_executive_dijawab_403_bukan_pantulan(): void
    {
        $this->pastikanGrant($this->exec->id, 'absensi/recap');

        $res = $this->withSession($this->sesiExecutive())->get('/absensi/recap');

        $res->assertStatus(403);
        $this->assertNull($res->headers->get('Location'), 'bukan pantulan — pagar controller yang bicara');
    }
    // ── Deret tab: pintu tulis tak boleh tampil ────────────────────────────

    /** Menu yang tampil lalu menolak adalah kegagalan senyap. */
    public function test_executive_tak_melihat_tab_yang_mengubah_data(): void
    {
        $html = $this->withSession($this->sesiExecutive())->get('/absensi/kehadiran')
            ->assertStatus(200)->getContent();

        $this->assertStringContainsString('>Kehadiran<', $html, 'tab tempatnya berdiri wajib ada');

        foreach (['Upload Data', 'Koreksi Absen', 'Mapping Karyawan', 'Periode Gaji', 'Rekap Persetujuan'] as $tab) {
            $this->assertStringNotContainsString(">$tab<", $html, "tab '$tab' berujung 403 untuk Executive");
        }
    }

    /** Kendali pembanding: Finance tak boleh kehilangan satu tab pun. */
    public function test_finance_tetap_melihat_seluruh_tabnya(): void
    {
        $html = $this->withSession($this->sesiFinance())->get('/absensi/kehadiran')
            ->assertStatus(200)->getContent();

        foreach (['Kehadiran', 'Upload Data', 'Koreksi Absen', 'Mapping Karyawan', 'Periode Gaji', 'Rekap Persetujuan'] as $tab) {
            $this->assertStringContainsString(">$tab<", $html, "Finance kehilangan tab '$tab'");
        }
    }

    // ── Menu samping ───────────────────────────────────────────────────────

    /**
     * Menu dibangun dari BERKAS ber-cache (`group_access_<id>`), bukan dari DB,
     * jadi pohonnya disuntikkan langsung supaya ujinya tak bergantung pada
     * `access:rebuild` pernah dijalankan di environment ini.
     */
    private function suntikMenu(string $groupId, array $links): void
    {
        Cache::put('group_access_' . $groupId, [[
            'nama' => 'Absensi',
            'icon' => '',
            'sub'  => array_map(fn ($l) => ['nama' => $l, 'link' => $l, 'icon' => ''], $links),
        ]], 300);
    }

    private function tautanMenu(array $sesi): array
    {
        $keluar = [];
        $this->withSession($sesi)->get('/absensi/kehadiran');
        foreach (shellMenuSections() as $sec) {
            foreach ($sec['items'] as $it) $keluar[] = $it['link'];
        }
        return $keluar;
    }

    public function test_menu_executive_punya_kehadiran_tanpa_upload_data(): void
    {
        $this->suntikMenu($this->exec->id, ['absensi', 'absensi/kehadiran', 'absensi/recap']);

        $links = $this->tautanMenu($this->sesiExecutive());

        $this->assertContains('absensi/kehadiran', $links, 'Dani harus melihat pintunya');
        $this->assertNotContains('absensi', $links, 'Upload Data tak boleh tampil');
        $this->assertNotContains('absensi/recap', $links, 'Rekap Persetujuan masih 403 untuk Executive');
    }

    /** Aturan lama utuh: role tanpa pintu DAN tanpa kuasa lintas cabang tetap disembunyikan. */
    public function test_menu_role_tanpa_kuasa_tetap_disembunyikan(): void
    {
        $this->suntikMenu($this->tanpaAbsensi->id, ['absensi']);

        $links = $this->tautanMenu($this->sesi($this->tanpaAbsensi->id, (string) $this->tanpaAbsensi->nama));

        $this->assertNotContains('absensi', $links);
    }
}
