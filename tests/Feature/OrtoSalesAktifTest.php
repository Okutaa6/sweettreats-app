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
 * Tracking Ortho — penilaian Sales "Active / Inactive", urutan, dan saringan.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG MELAHIRKAN BERKAS INI
 * ────────────────────────────────────────────────────────────────────────────
 * prompt_jadwal_kontrol.md: "Pasien Active selalu di atas, pasien Inactive
 * selalu di PALING BAWAH (tetap tampil, tidak disembunyikan). Di dalam grup
 * Active, urutkan dari yang paling mendesak."
 *
 * Dua hal yang tidak boleh tercampur, dan itu pokok berkas ini:
 *   · `ort_case.status`       — FAKTA KLINIS, diisi MESIN (bracket lepas).
 *   · `ort_case.sales_active` — PENILAIAN MANUSIA, diubah manual.
 * Kalau ditumpangkan ke satu kolom, tarikan malam menimpa keputusan Sales
 * tanpa jejak dan pasien yang sengaja dilepas kembali sendiri tiap malam.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG DIJAGA DI SINI
 * ────────────────────────────────────────────────────────────────────────────
 *   • ⭐ Inactive TETAP TAMPIL — tidak pernah ada WHERE sales_active = 1
 *   • ⭐ Inactive selalu di bawah, walau ia yang paling terlambat
 *   • ⭐ badge hitungan ada — tanpanya "diurutkan ke bawah" sama dengan
 *        "disembunyikan", dan itu bukan yang diminta prompt
 *   • urutan paginasi STABIL (pemecah seri unik) — bug yang sudah ada sebelum ini
 *   • saringan Status Pasien bekerja BARENG saringan Status yang lama
 *   • toggle tidak menyentuh kolom `status` klinis
 */
class OrtoSalesAktifTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-SALESAKTIF';

    private string $placeId;
    private array $sesi;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ort_case', 'ort_control'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("Tabel {$t} belum ada.");
            }
        }
        if (! Schema::hasColumn('ort_case', 'sales_active')) {
            $this->markTestSkipped('Jalankan database/sql/2026_10_06_ort_case_sales_active.sql dulu.');
        }
        if (! DB::table('access_path')->where('link', 'tracking-ortho')->exists()) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_access.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }
        $this->placeId = (string) array_key_first($peta);

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
     * Satu pasien + satu kontrol terbuka.
     *
     * @param  int   $umurKunjungan  hari sejak kunjungan terakhir
     * @param  int   $irama          median_gap_days
     */
    private function pasien(string $nama, int $umurKunjungan, int $irama = 30, bool $aktif = true): OrtCase
    {
        $pid   = self::TANDA . '-' . Str::random(6);
        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => self::TANDA . ' ' . $nama,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => Carbon::today()->subDays(400)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF,
            'last_visit_date' => Carbon::today()->subDays($umurKunjungan)->toDateString(),
            'median_gap_days' => $irama,
            'sales_active' => $aktif ? OrtCase::SALES_AKTIF : OrtCase::SALES_PASIF,
            'control_count' => 1, 'visit_count' => 1, 'visit_count_180d' => 1,
        ]);

        OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 2,
            'place_id' => $this->placeId, 'is_proposal' => 1,
            'sch_date' => Carbon::today()->subDays(max(0, $umurKunjungan - $irama))->toDateString(),
            'sch_source' => OrtControl::SCH_SISTEM, 'created_by' => 'uji',
        ]);

        return $kasus;
    }

    /** Nama pasien di tabel, berurutan seperti yang dirender. */
    private function urutanNama(string $urgensi = 'semua'): array
    {
        $baris = app(OrtQuery::class)->daftar($this->placeId, $urgensi, ['q' => self::TANDA], 100);

        return collect($baris->items())->pluck('patient_name')->all();
    }

    private function halaman(array $query = []): string
    {
        $url = '/tracking-ortho?' . http_build_query(array_merge(
            ['utama' => $this->placeId, 'q' => self::TANDA],
            $query
        ));

        return $this->withSession($this->sesi)->get($url)->assertOk()->getContent();
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⭐ Inactive tetap tampil
    // ════════════════════════════════════════════════════════════════════════

    public function test_inactive_tetap_tampil_di_daftar(): void
    {
        $this->pasien('Dilepas', 90, 30, false);

        $nama = $this->urutanNama();

        $this->assertCount(1, $nama, 'Inactive TIDAK boleh disaring keluar — ia hanya turun ke bawah.');
        $this->assertStringContainsString('Dilepas', $nama[0]);
    }

    /** ⭐ Dan selalu di bawah, walau ia yang paling terlambat. */
    public function test_inactive_di_bawah_walau_paling_terlambat(): void
    {
        $this->pasien('ZPasif', 300, 30, false);   // paling parah, tapi dilepas
        $this->pasien('AAktif', 35, 30, true);     // baru 5 hari lewat

        $nama = $this->urutanNama('terlewat');

        $this->assertCount(2, $nama);
        $this->assertStringContainsString('AAktif', $nama[0], 'Active harus di atas.');
        $this->assertStringContainsString('ZPasif', $nama[1], 'Inactive harus di bawah walau 300 hari terlambat.');
    }

    public function test_inactive_di_bawah_juga_di_tab_semua(): void
    {
        $this->pasien('ZPasif', 300, 30, false);
        $this->pasien('AAktif', 35, 30, true);

        $nama = $this->urutanNama('semua');

        $this->assertStringContainsString('AAktif', $nama[0]);
        $this->assertStringContainsString('ZPasif', $nama[1]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⭐ Urutan mendesak: SIKLUS, bukan selisih hari
    // ════════════════════════════════════════════════════════════════════════

    /**
     * ⭐ Dua pasien "telat 10 hari" yang kegentingannya TIDAK sama.
     *
     * Irama 21 hari, telat 10 → 31/21 = 1,48 siklus.
     * Irama 60 hari, telat 10 → 70/60 = 1,17 siklus.
     * Mengurutkan dengan selisih hari akan menyamakan keduanya; prompt minta
     * "paling mendesak dulu", dan yang mendesak adalah yang pertama.
     */
    public function test_mendesak_dihitung_per_siklus_bukan_per_hari(): void
    {
        $this->pasien('Longgar', 70, 60, true);
        $this->pasien('Rapat', 31, 21, true);

        $nama = $this->urutanNama('terlewat');

        $this->assertCount(2, $nama);
        $this->assertStringContainsString('Rapat', $nama[0], 'Irama rapat yang telat sama = lebih mendesak.');
        $this->assertStringContainsString('Longgar', $nama[1]);
    }

    /**
     * ⭐ Belum pernah berkunjung naik ke ATAS, bukan tenggelam ke dasar.
     *
     * Bracket terpasang tanpa satu pun kunjungan tercatat adalah keadaan
     * terburuk di himpunan ini, dan OrtCase::isHilang() sudah memperlakukan
     * NULL sebagai hilang. Tanpa penjaga ini, DESC menerjunkannya ke dasar grup
     * Active — tepat ke tempat yang paling salah.
     */
    public function test_belum_pernah_datang_naik_ke_atas(): void
    {
        /* ⚠ Dibuat dengan umur kunjungan 60 hari LEBIH DULU, baru
           last_visit_date-nya dikosongkan. Kalau dibuat dengan umur 0,
           sch_date-nya jatuh di hari ini dan barisnya tidak masuk sub-tab
           Terlewat sama sekali — ujinya lalu hijau/merah karena alasan yang
           salah. */
        $kasus = $this->pasien('TanpaKunjungan', 60, 30, true);
        $kasus->forceFill(['last_visit_date' => null])->save();

        $this->pasien('Telat', 60, 30, true);

        $nama = $this->urutanNama('terlewat');

        $this->assertNotEmpty($nama);
        $this->assertStringContainsString(
            'TanpaKunjungan', $nama[0],
            'Pasien tanpa kunjungan tercatat harus paling atas, bukan paling bawah.'
        );
    }

    /**
     * Tab "Semua" tetap kronologis — aturan yang tertulis di _tabel.blade.php
     * ("'Semua' hanya DIURUTKAN Terlewat → Hari ini/Minggu ini → Mendatang").
     * Rasio siklus dipakai di SUB-TAB, di mana rentang tanggalnya sudah diikat
     * sehingga urutan tanggal tidak memberi informasi apa pun lagi.
     */
    public function test_tab_semua_tetap_berurut_tanggal(): void
    {
        // Irama rapat & telat sedikit (sch_date lebih baru) vs irama longgar
        // & telat banyak (sch_date lebih lama).
        $this->pasien('SchBaru', 25, 21, true);
        $this->pasien('SchLama', 120, 60, true);

        $nama = $this->urutanNama('semua');

        $this->assertCount(2, $nama);
        $this->assertStringContainsString('SchLama', $nama[0], 'Tab Semua: sch_date paling lama di atas.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // Paginasi stabil
    // ════════════════════════════════════════════════════════════════════════

    /**
     * ⭐ Memperbaiki bug yang SUDAH ADA sebelum pekerjaan ini: urutan lama
     * `sch_date, patient_name` tidak unik, jadi baris bisa berpindah halaman
     * antar klik tanpa satu data pun berubah.
     */
    public function test_paginasi_stabil_saat_tanggal_dan_nama_kembar(): void
    {
        foreach (range(1, 4) as $i) {
            $this->pasien('Kembar', 40, 30, true);   // nama DAN sch_date identik
        }

        $q = app(OrtQuery::class);
        $hal1a = collect($q->daftar($this->placeId, 'semua', ['q' => self::TANDA], 2)->items())->pluck('id')->all();
        $hal1b = collect($q->daftar($this->placeId, 'semua', ['q' => self::TANDA], 2)->items())->pluck('id')->all();

        $this->assertSame($hal1a, $hal1b, 'Dua kueri identik memberi halaman berbeda — kunci urutnya tidak unik.');
        $this->assertCount(2, array_unique($hal1a));
    }

    // ════════════════════════════════════════════════════════════════════════
    // Saringan
    // ════════════════════════════════════════════════════════════════════════

    public function test_saringan_memilah_active_dan_inactive(): void
    {
        $this->pasien('AAktif', 40, 30, true);
        $this->pasien('ZPasif', 40, 30, false);

        $q = app(OrtQuery::class);

        $active = collect($q->daftar($this->placeId, 'semua', ['q' => self::TANDA, 'rutin' => 'Active'], 100)->items());
        $pasif  = collect($q->daftar($this->placeId, 'semua', ['q' => self::TANDA, 'rutin' => 'Inactive'], 100)->items());
        $semua  = collect($q->daftar($this->placeId, 'semua', ['q' => self::TANDA], 100)->items());

        $this->assertCount(1, $active);
        $this->assertCount(1, $pasif);
        $this->assertCount(2, $semua, 'Tanpa saringan, keduanya tampil.');
        $this->assertStringContainsString('AAktif', $active[0]->patient_name);
        $this->assertStringContainsString('ZPasif', $pasif[0]->patient_name);
    }

    /** Nilai ngawur = Semua, bukan daftar kosong. */
    public function test_nilai_saringan_ngawur_diabaikan(): void
    {
        $this->pasien('AAktif', 40, 30, true);
        $this->pasien('ZPasif', 40, 30, false);

        $hasil = app(OrtQuery::class)
            ->daftar($this->placeId, 'semua', ['q' => self::TANDA, 'rutin' => 'ngawur'], 100);

        $this->assertCount(2, $hasil->items());
    }

    /**
     * ⭐ Bekerja BARENG saringan Status yang lama — prompt memintanya begitu
     * ("Filter ini bekerja bareng filter lain yang sudah ada, jangan hapus
     * filter lama"). "Inactive + Terlewat" adalah pertanyaan yang sah: siapa
     * yang sudah dilepas padahal masih terlambat.
     */
    public function test_bekerja_bareng_saringan_status_lama(): void
    {
        $this->pasien('PasifTelat', 90, 30, false);
        $this->pasien('PasifBelum', 5, 30, false);

        $hasil = app(OrtQuery::class)->daftar(
            $this->placeId, 'semua',
            ['q' => self::TANDA, 'rutin' => 'Inactive', 'status' => 'Terlewat'],
            100
        );

        $this->assertCount(1, $hasil->items(), 'Kedua saringan harus berlaku bersamaan.');
        $this->assertStringContainsString('PasifTelat', $hasil->items()[0]->patient_name);
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⭐ Badge hitungan
    // ════════════════════════════════════════════════════════════════════════

    /**
     * ⭐ Angka badge dihitung atas populasi yang SAMA dengan yang dirender,
     * TAPI tanpa saringan `rutin` itu sendiri — kalau tidak, memilih "Inactive"
     * membuat badge berbunyi "1 Inactive" padahal ada 400.
     */
    public function test_hitungan_badge_tidak_ikut_tersaring_dirinya(): void
    {
        $this->pasien('A1', 40, 30, true);
        $this->pasien('A2', 40, 30, true);
        $this->pasien('P1', 40, 30, false);

        $q = app(OrtQuery::class);

        $tanpa = $q->hitungSalesAktif($this->placeId, 'semua', ['q' => self::TANDA]);
        $saring = $q->hitungSalesAktif($this->placeId, 'semua', ['q' => self::TANDA, 'rutin' => 'Inactive']);

        $this->assertSame(['aktif' => 2, 'pasif' => 1], $tanpa);
        $this->assertSame($tanpa, $saring, 'Saringan rutin tidak boleh mempengaruhi angka badge-nya sendiri.');
    }

    /** Badge-nya memang sampai ke layar — bukan sekadar terhitung di controller. */
    public function test_badge_terender_di_halaman(): void
    {
        $this->pasien('P1', 40, 30, false);

        $html = $this->halaman();

        /* Pembawanya berganti 3 Okt 2026: badge mengambang di atas tabel
           digantikan PITA PEMISAH yang berdiri tepat sebelum baris Inactive
           pertama. Alasannya sama dengan sebelumnya dan tidak melemah —
           turunnya mereka ke bawah harus terbaca sebagai keputusan, bukan
           seperti daftar yang tiba-tiba berubah sifat di tengah. */
        $this->assertStringContainsString('ort-group__hint', $html, 'Pita pemisah Inactive tidak terender.');
        $this->assertStringContainsString(
            'jarang kontrol atau menghilang', $html,
            'Pita harus menerangkan apa arti Inactive, bukan cuma melabelinya.'
        );
        $this->assertStringContainsString('ort-chip--inactive', $html, 'Baris Inactive harus ber-chip di kolom Nama.');
    }

    /** Tanpa pasien Inactive, pita pemisahnya tidak perlu ada. */
    public function test_pita_hilang_kalau_tidak_ada_inactive(): void
    {
        $this->pasien('A1', 40, 30, true);

        $html = $this->halaman();

        $this->assertStringNotContainsString('jarang kontrol atau menghilang', $html);
        $this->assertStringNotContainsString('ort-chip--inactive', $html);
    }

    /** ⭐ Dan pita itu muncul SEKALI saja, bukan di tiap baris Inactive. */
    public function test_pita_muncul_sekali_saja(): void
    {
        $this->pasien('A1', 40, 30, true);
        foreach (['P1', 'P2', 'P3'] as $n) {
            $this->pasien($n, 40, 30, false);
        }

        $this->assertSame(
            1, substr_count($this->halaman(), 'jarang kontrol atau menghilang'),
            'Pita pemisah harus berdiri sekali, tepat sebelum baris Inactive pertama.'
        );
    }

    // ════════════════════════════════════════════════════════════════════════
    // Toggle
    // ════════════════════════════════════════════════════════════════════════

    public function test_toggle_menyimpan_jejak_siapa_dan_kapan(): void
    {
        $kasus = $this->pasien('Toggle', 40, 30, true);

        $this->withSession($this->sesi)
            ->from('/tracking-ortho?utama=' . $this->placeId)
            ->post('/tracking-ortho/kasus/' . $kasus->id . '/sales-aktif', ['aktif' => 0, 'catatan' => 'nomor mati'])
            ->assertRedirect();

        $kasus->refresh();

        $this->assertSame(0, (int) $kasus->sales_active);
        $this->assertSame('Uji Sales', $kasus->sales_active_by);
        $this->assertSame('nomor mati', $kasus->sales_active_note);
        $this->assertNotNull($kasus->sales_active_at);
    }

    /** ⭐ Dan TIDAK menyentuh status klinis. Itu sumbu lain, diisi mesin. */
    public function test_toggle_tidak_menyentuh_status_klinis(): void
    {
        $kasus = $this->pasien('Klinis', 40, 30, true);

        $this->withSession($this->sesi)
            ->from('/tracking-ortho?utama=' . $this->placeId)
            ->post('/tracking-ortho/kasus/' . $kasus->id . '/sales-aktif', ['aktif' => 0])
            ->assertRedirect();

        $this->assertSame(
            OrtCase::STATUS_AKTIF, $kasus->refresh()->status,
            'status itu fakta klinis dari rekam medis — penilaian Sales tidak boleh menyentuhnya.'
        );
        $this->assertNull($kasus->finished_at);
    }

    public function test_toggle_bisa_diurungkan(): void
    {
        $kasus = $this->pasien('Urung', 40, 30, true);

        $this->withSession($this->sesi)
            ->from('/tracking-ortho?utama=' . $this->placeId)
            ->post('/tracking-ortho/kasus/' . $kasus->id . '/sales-aktif', ['aktif' => 0])
            ->assertRedirect();

        $token = session('ort_undo');
        $this->assertNotNull($token);

        $this->withSession($this->sesi)->post('/tracking-ortho/urungkan/' . $token)->assertRedirect();

        $this->assertSame(1, (int) $kasus->refresh()->sales_active);
    }

    public function test_toggle_menolak_nilai_yang_sama(): void
    {
        $kasus = $this->pasien('Sama', 40, 30, true);

        $this->withSession($this->sesi)
            ->from('/tracking-ortho?utama=' . $this->placeId)
            ->post('/tracking-ortho/kasus/' . $kasus->id . '/sales-aktif', ['aktif' => 1])
            ->assertSessionHas('error');

        $this->assertNull($kasus->refresh()->sales_active_at, 'Tidak ada perubahan = tidak ada jejak baru.');
    }

    /** Gerbangnya kepemilikan cabang, bukan role — modul ini nol pemeriksaan role. */
    public function test_pasien_cabang_lain_ditolak(): void
    {
        $kasus = $this->pasien('Asing', 40, 30, true);
        $kasus->forceFill(['owner_place_id' => 'CABANG-LAIN-UJI'])->save();

        $this->withSession($this->sesi)
            ->from('/tracking-ortho?utama=' . $this->placeId)
            ->post('/tracking-ortho/kasus/' . $kasus->id . '/sales-aktif', ['aktif' => 0])
            ->assertForbidden();

        $this->assertSame(1, (int) $kasus->refresh()->sales_active);
    }

    public function test_bawaan_pasien_baru_adalah_active(): void
    {
        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => self::TANDA . '-' . Str::random(6), 'patient_name' => self::TANDA . ' Baru',
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => Carbon::today()->toDateString(), 'status' => OrtCase::STATUS_AKTIF,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);

        $this->assertSame(
            1, (int) $kasus->refresh()->sales_active,
            '"Inactive" adalah penilaian POSITIF yang harus dibuat seseorang — bawaannya Active.'
        );
    }
}
