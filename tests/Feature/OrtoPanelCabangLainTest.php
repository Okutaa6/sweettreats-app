<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — panel baris di tab "Pasien di cabang lain": LIHAT SAJA.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG MELAHIRKAN BERKAS INI
 * ────────────────────────────────────────────────────────────────────────────
 * Tab itu menampilkan pasien yang cabang PASANGNYA kita tapi sedang DIPEGANG
 * cabang lain. Reza meminta panel tiga kolom yang sama muncul di sana — dengan
 * syarat tegas: Transfer, pemilah Active/Inactive, dan Tandai tidak hadir
 * disembunyikan, sementara lipatan riwayat kontrol tetap ada dan bisa dibuka.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * DUA HAL YANG DIJAGA DI SINI, DAN KEDUANYA MUDAH RUSAK DIAM-DIAM
 * ────────────────────────────────────────────────────────────────────────────
 *   1. GERBANGNYA. Panel adalah endpoint tersendiri; kalau gerbangnya longgar,
 *      ia jadi celah membaca pasien cabang mana pun lewat tebak-tebak id.
 *      Aturannya sama persis dengan drawerDetail(): boleh kalau kita yang
 *      memegang ATAU cabang pasangnya kita; selain itu 403.
 *
 *   2. TOMBOL TULISNYA TIDAK DIRENDER, bukan sekadar dinonaktifkan. Tombol mati
 *      tetap mengundang klik, dan setiap endpoint tulis modul ini toh menjawab
 *      403 — jadi satu-satunya hasil yang mungkin adalah Sales mencoba lalu
 *      ditolak tanpa penjelasan.
 *
 * ⚠ Dan satu jebakan yang SUDAH menggigit saat ini dibangun: $ctx halaman sudah
 *   memuat 'hanyaLihat', jadi menimpanya dengan operator `+` TIDAK PERNAH
 *   SAMPAI — panel dirender bertombol lengkap lalu meledak di route() untuk
 *   pasien yang belum punya kontrol terbuka. Uji di bawah menahan keduanya.
 */
class OrtoPanelCabangLainTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-LINTAS';

    private string $kita;
    private string $sana;
    private array $sesi;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ort_case', 'ort_control'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("Tabel {$t} belum ada.");
            }
        }
        if (! DB::table('access_path')->where('link', 'tracking-ortho')->exists()) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_access.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (count($peta) < 2) {
            $this->markTestSkipped('Butuh minimal dua cabang ber-omnicare_place_id.');
        }

        $kunci      = array_keys($peta);
        $this->kita = (string) $kunci[0];
        $this->sana = (string) $kunci[1];

        $gid = DB::table('group_path')->where('nama', 'Supervisor')->value('id');
        if (! $gid) {
            $this->markTestSkipped("Role 'Supervisor' tidak ada di DB ini.");
        }
        $node = DB::table('access_path')->where('link', 'tracking-ortho')->first(['id', 'pid']);
        foreach (array_filter([$node->id, $node->pid]) as $accessId) {
            if (! DB::table('group_access')->where('group_id', $gid)->where('access_id', $accessId)->exists()) {
                DB::table('group_access')->insert(['id' => (string) Str::uuid(), 'group_id' => $gid, 'access_id' => $accessId]);
            }
        }

        $this->sesi = [
            'isLoggedIn' => true, 'id' => 'uji-' . Str::random(8), 'group_id' => $gid,
            'group_name' => 'Supervisor', 'name' => 'Uji Sales', 'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600,
            'login_at' => time(), 'pa_touch' => time(),
        ];
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    /**
     * @param  bool  $adaKontrolTerbuka  false = pasien "belum dijadwalkan",
     *                                   keadaan yang lazim di tab cabang lain
     */
    private function pasien(string $pasang, string $pegang, bool $adaKontrolTerbuka = true): OrtCase
    {
        $pid   = self::TANDA . '-' . Str::random(6);
        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji Lintas ' . $pid,
            'owner_place_id' => $pegang, 'install_place_id' => $pasang,
            'install_date' => Carbon::today()->subDays(400)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF,
            'last_visit_date' => Carbon::today()->subDays(60)->toDateString(),
            'median_gap_days' => 30,
            'control_count' => 1, 'visit_count' => 1, 'visit_count_180d' => 1,
        ]);

        if ($adaKontrolTerbuka) {
            $ctl = OrtControl::create([
                'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 2,
                'place_id' => $pegang, 'is_proposal' => 1,
                'sch_date' => Carbon::today()->subDays(30)->toDateString(),
                'sch_source' => OrtControl::SCH_SISTEM, 'created_by' => 'uji',
            ]);
            $kasus->forceFill(['open_control_id' => $ctl->id])->save();
        }

        return $kasus;
    }

    private function panel(string $dariCabang, string $caseId)
    {
        return $this->withSession($this->sesi)->get('/tracking-ortho?' . http_build_query([
            'utama' => $dariCabang, 'frag' => 'panel', 'kasus' => $caseId,
        ]));
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⭐ Gerbang
    // ════════════════════════════════════════════════════════════════════════

    /** Pasien yang cabang pasangnya kita, dipegang cabang lain → boleh DILIHAT. */
    public function test_pasien_kita_di_cabang_lain_boleh_dilihat(): void
    {
        $k = $this->pasien($this->kita, $this->sana);

        $this->panel($this->kita, $k->id)->assertOk();
    }

    /** Pasien yang kita pegang sendiri → tentu boleh. */
    public function test_pasien_milik_sendiri_boleh(): void
    {
        $k = $this->pasien($this->kita, $this->kita);

        $this->panel($this->kita, $k->id)->assertOk();
    }

    /**
     * ⭐ Cabang yang tidak punya klaim apa pun → 403, bukan panel kosong.
     *
     * Panel endpoint tersendiri; gerbang yang longgar membuatnya jadi celah
     * membaca pasien cabang mana pun lewat tebak-tebak id.
     */
    public function test_cabang_tanpa_klaim_ditolak(): void
    {
        $k = $this->pasien($this->sana, $this->sana);

        $this->panel($this->kita, $k->id)->assertForbidden();
    }

    public function test_kasus_yang_tidak_ada_404(): void
    {
        $this->panel($this->kita, (string) Str::uuid())->assertNotFound();
    }

    /**
     * ⭐ Pasien TANPA kontrol terbuka tetap bisa dibuka.
     *
     * Ini keadaan yang LAZIM di tab cabang lain ("belum dijadwalkan"), dan
     * panel yang dicari lewat id kontrol tidak akan pernah menemukannya —
     * itu sebabnya kuncinya kasus, dan barisKasus() memakai LEFT JOIN.
     */
    public function test_pasien_tanpa_kontrol_terbuka_tetap_bisa_dibuka(): void
    {
        $k = $this->pasien($this->kita, $this->sana, adaKontrolTerbuka: false);

        $this->assertNull($k->refresh()->open_control_id, 'Prasyarat: memang belum punya kontrol terbuka.');

        $html = $this->panel($this->kita, $k->id)->assertOk()->getContent();

        $this->assertStringContainsString('ort-p2__head', $html, 'Panelnya tetap terender utuh.');
        $this->assertStringContainsString($k->omnicare_pid, $html);
    }

    /** Dan barisnya memang terbentuk walau kolom kontrolnya kosong. */
    public function test_baris_kasus_tanpa_kontrol_tetap_ada(): void
    {
        $k = $this->pasien($this->kita, $this->sana, adaKontrolTerbuka: false);

        $r = app(OrtQuery::class)->barisKasus($k->id);

        $this->assertNotNull($r, 'LEFT JOIN: barisnya ada walau kontrolnya tidak.');
        $this->assertNull($r->id, 'Kolom kontrol kosong — dan itu yang harus ditahan panel.');
        $this->assertSame($k->id, $r->ort_case_id, 'ort_case_id WAJIB dari k.id, bukan c.ort_case_id.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⭐ Lihat-saja
    // ════════════════════════════════════════════════════════════════════════

    /**
     * ⭐ Tombol tulis TIDAK DIRENDER. Bukan dinonaktifkan — tombol mati tetap
     * mengundang klik, dan endpoint tulisnya toh menjawab 403.
     */
    public function test_tombol_tulis_tidak_dirender_di_cabang_lain(): void
    {
        $k    = $this->pasien($this->kita, $this->sana);
        $html = $this->panel($this->kita, $k->id)->assertOk()->getContent();

        foreach ([
            'Transfer Pasien'     => 'memindahkan pasien yang dipegang cabang lain bukan keputusan cabang ini',
            'ort-seg__b'          => 'pemilah Active/Inactive',
            'Tandai tidak hadir'  => 'menutup kontrol milik cabang lain',
            'Batalkan follow-up'  => 'membatalkan follow-up cabang lain',
            'Geser pengingat'     => 'menggeser jadwal cabang lain',
        ] as $jejak => $kenapa) {
            $this->assertStringNotContainsString($jejak, $html, "Harus disembunyikan — {$kenapa}.");
        }
    }

    /** Yang TETAP ada: navigasi, lipatan riwayat, dan keadaan yang dikatakan. */
    public function test_yang_boleh_dilihat_tetap_ada(): void
    {
        $k    = $this->pasien($this->kita, $this->sana);
        $html = $this->panel($this->kita, $k->id)->assertOk()->getContent();

        $this->assertStringContainsString('Buka detail pasien', $html, 'Navigasi, bukan tulisan — tetap ada.');
        $this->assertStringContainsString('ort-nlist__tgl', $html, 'Lipatan riwayat kontrol tetap bisa dibuka.');
        $this->assertStringContainsString(
            'ditentukan cabang pemegang', $html,
            'Status kontrol tetap DIKATAKAN; menyembunyikannya membuat pembaca mengira pasien ini tidak punya status.'
        );
    }

    /**
     * ⭐ Dan panel pasien SENDIRI tidak ikut terkunci.
     *
     * Jebakannya nyata: $ctx halaman sudah memuat 'hanyaLihat', jadi menimpanya
     * dengan operator `+` tidak pernah sampai — dan arah salahnya bisa ke dua
     * sisi. Uji ini menahan sisi yang satunya.
     */
    public function test_panel_pasien_sendiri_tetap_bisa_diubah(): void
    {
        $k    = $this->pasien($this->kita, $this->kita);
        $html = $this->panel($this->kita, $k->id)->assertOk()->getContent();

        $this->assertStringContainsString('Transfer Pasien', $html);
        $this->assertStringContainsString('ort-seg__b', $html, 'Pemilah Active/Inactive harus bisa dipakai.');
        $this->assertStringNotContainsString('ditentukan cabang pemegang', $html);
    }

    // ════════════════════════════════════════════════════════════════════════
    // Tab-nya sendiri
    // ════════════════════════════════════════════════════════════════════════

    /**
     * ⭐ Keempat tab utama terbuka. Tanpa uji ini, tab "Belum punya jadwal"
     * sempat 500 TANPA ketahuan: antrean naik dari kartu jadi tab, dan penjaga
     * lama `if ($tab !== 'list') $kartuAktif = null;` membatalkan tab barunya
     * sendiri — konteks antrean tidak pernah dibangun, _antrean dirender tanpa
     * $kelompok. Suite tetap hijau karena tidak satu pun uji membuka tab itu.
     */
    public function test_keempat_tab_terbuka(): void
    {
        $this->pasien($this->kita, $this->kita);

        foreach (['list', 'antrean', 'kalender', 'pasien'] as $tab) {
            $this->withSession($this->sesi)
                ->get('/tracking-ortho?' . http_build_query(['utama' => $this->kita, 'tab' => $tab]))
                ->assertOk();
        }
    }

    /** Dan kartu ringkasnya memang sudah tidak dirender di mana pun. */
    public function test_kartu_ringkas_sudah_tidak_ada(): void
    {
        $html = $this->withSession($this->sesi)
            ->get('/tracking-ortho?' . http_build_query(['utama' => $this->kita]))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('ort-card__icon', $html, 'Kartu ringkas sudah dibuang dari layar.');

        /* ⚠ ANGKANYA tidak ikut dibuang: _btabs memakai $kartu['aktif'] sebagai
           hitungan pasien di tab cabang, dan spanduk "hari pertama" memakai
           ketiganya. Dua kali saat membuangnya saya salah menyimpulkan angka itu
           "cuma untuk kartu". */
        $this->assertStringContainsString('ort-btab__count', $html, 'Hitungan pasien di tab cabang tetap ada.');
    }

    /** Barisnya bisa diklik dan membawa cangkang panel beserta skripnya. */
    public function test_tab_cabang_lain_punya_cangkang_dan_skrip(): void
    {
        $this->pasien($this->kita, $this->sana);

        $html = $this->withSession($this->sesi)->get('/tracking-ortho?' . http_build_query([
            'utama' => $this->kita, 'cabang' => $this->sana,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('data-ort-kosong', $html, 'Cangkang panel harus ada di tiap baris.');
        $this->assertStringContainsString('data-ort-kasus', $html, 'Cangkang dikunci id KASUS, bukan kontrol.');
        $this->assertStringContainsString('data-ort-toggle', $html, 'Tombol pembuka panel harus ada.');
        $this->assertStringContainsString(
            'data-ort-kpanel', $html,
            'Skrip panel (_js_panel) harus ikut terbawa — tanpanya barisnya tidak bisa dibuka sama sekali.'
        );
    }
}
