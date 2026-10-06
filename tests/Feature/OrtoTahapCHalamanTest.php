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
 * Tracking Ortho — yang berubah DI HALAMAN karena Tahap C.
 *
 * Tiga hal, semuanya lahir dari audit HUB 1 Okt 2026, dan masing-masing punya
 * satu cara gagal yang khas:
 *
 *  1. TANDA "±" UNTUK TANGGAL PASANG YANG CUMA PERKIRAAN.
 *     45,7% tanggal pasang di prod diperkirakan dari kunjungan ortho pertama
 *     yang terlihat, bukan dari baris pemasangan — brief §6.1 mencatat 132
 *     nama item pemasangan berbeda, jadi sebagian besar pemasangan memang
 *     tidak tertangkap pola. Dulu keduanya tampil identik.
 *     ⚠ Cara gagalnya: menandai SEMUA baris. Tanda yang muncul di mana-mana
 *       sama tidak berinformasinya dengan tanda yang tidak pernah muncul —
 *       karena itu uji di sini selalu memeriksa sisi negatifnya juga.
 *
 *  2. DUA SARINGAN KERJA HARIAN.
 *     ⚠ Cara gagalnya: opsi baru muncul di dropdown tapi tidak menyaring apa
 *       pun. Dropdown Status di halaman ini memang sudah pernah berisi pilihan
 *       yang perlu diterjemahkan tangan ke kolom, jadi opsi yang diam adalah
 *       kegagalan yang sangat mungkin dan sangat sunyi.
 *
 *  3. PAGINASI 10 → 50.
 *     Bintaro punya 959 kasus aktif; 10 baris per halaman berarti 96 halaman.
 *
 * Semua perubahan data ikut rollback DatabaseTransactions.
 */
class OrtoTahapCHalamanTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-TAHAPC';

    private string $placeId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_case')) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.');
        }
        if (! Schema::hasColumn('ort_case', 'install_is_estimated')) {
            $this->markTestSkipped('Jalankan database/sql/2026_10_01_ort_tahap_c.sql dulu.');
        }
        if (! DB::table('access_path')->where('link', 'tracking-ortho')->exists()) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_access.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }

        $this->placeId = (string) array_key_first($peta);
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    /** Satu pasien dengan satu jadwal TERBUKA, jadi ia pasti muncul di tab List. */
    private function pasien(array $kasusUbah = [], array $kontrolUbah = []): OrtControl
    {
        $pid = self::TANDA . '-' . Str::random(6);

        $kasus = OrtCase::create(array_merge([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji TahapC ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => Carbon::today()->subDays(400)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ], $kasusUbah));

        return OrtControl::create(array_merge([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 1,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date' => Carbon::today()->toDateString(),
            'sch_source' => OrtControl::SCH_MANUAL, 'created_by' => 'uji',
        ], $kontrolUbah));
    }

    /** @param array<string,string> $query */
    private function halaman(array $query = [], ?string $nama = 'Uji Sales'): string
    {
        $gid = DB::table('group_path')->where('nama', 'Supervisor')->value('id');
        if (! $gid) {
            $this->markTestSkipped("Role 'Supervisor' tidak ada di DB ini.");
        }

        $node = DB::table('access_path')->where('link', 'tracking-ortho')->first(['id', 'pid']);
        foreach (array_filter([$node->id, $node->pid]) as $accessId) {
            $ada = DB::table('group_access')->where('group_id', $gid)->where('access_id', $accessId)->exists();
            if (! $ada) {
                DB::table('group_access')->insert([
                    'id' => (string) Str::uuid(), 'group_id' => $gid, 'access_id' => $accessId,
                ]);
            }
        }

        $url = '/tracking-ortho?' . http_build_query(array_merge(['utama' => $this->placeId], $query));

        return $this->withSession([
            'isLoggedIn' => true, 'id' => 'uji-' . Str::random(8), 'group_id' => $gid,
            'group_name' => 'Supervisor', 'name' => $nama, 'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600,
            'login_at' => time(), 'pa_touch' => time(),
        ])->get($url)->assertOk()->getContent();
    }

    /**
     * Pasien uji mana saja yang benar-benar terbaca di halaman.
     *
     * ⚠ Dicocokkan ke NOMOR REKAM MEDIS-nya, bukan ke nama lengkapnya.
     *   Nama panjang dipotong `.ort-clamp2` di sel List, jadi mencocokkan
     *   "Uji TahapC <pid>" utuh akan berbunyi "tidak ada" untuk baris yang
     *   sebetulnya tampil — dan uji saringan jadi hijau/merah karena alasan
     *   yang sama sekali bukan saringannya. Sudah kena sekali.
     *
     * @return list<string>
     */
    /**
     * Berapa BARIS yang lolos saringan.
     *
     * ⚠ Dihitung dari penanda baris, bukan dari nomor rekam medis di teksnya.
     *   Versi lama mencocokkan "UJI-TAHAPC-xxxxxx" dan itu rapuh karena dua
     *   hal yang baru ketahuan 6 Okt 2026: kolom Nama merender
     *   `Str::title($r->patient_name)` sehingga tandanya berubah jadi
     *   "Uji-Tahapc-...", dan yang selama ini menyelamatkannya adalah PID
     *   MENTAH di panel baris — panel yang kini dimuat belakangan.
     */
    private function namaTampil(string $html): array
    {
        preg_match_all('~data-ort-row="([^"]+)"~', $html, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * Isi panel baris — DIAMBIL TERSENDIRI sejak 6 Okt 2026.
     *
     * Panel tidak lagi ikut dirender di halaman daftar: ia dimuat saat
     * barisnya dibuka (?frag=panel). Alasannya terukur — 95% dari fragmen
     * tabel 454 KB adalah 23 panel yang paling banyak SATU yang pernah
     * terbuka, dan menukar DOM sebesar itu tiap kali follow-up disimpan
     * terasa seperti memuat ulang halaman.
     *
     * Jadi assertion tentang ISI panel harus menanyakan panelnya, bukan
     * halamannya.
     */
    private function panel(OrtControl $ctl): string
    {
        // Kuncinya KASUS, bukan kontrol: panel juga melayani pasien yang belum
        // punya kontrol terbuka (tab "Pasien di cabang lain").
        return $this->halaman(['frag' => 'panel', 'kasus' => $ctl->ort_case_id]);
    }

    // ── 1 · Tanda perkiraan ─────────────────────────────────────────────────

    /** ⭐ Muncul untuk yang diperkirakan. */
    public function test_tanda_perkiraan_muncul_untuk_tanggal_yang_ditebak(): void
    {
        $ctl = $this->pasien(['install_is_estimated' => 1]);

        // Penanda ± hidup di kolom Profile panel, dan panel kini dimuat
        // belakangan — lihat catatan di panel().
        $this->assertStringContainsString(
            'ort-kira', $this->panel($ctl),
            'Tanggal pasang yang cuma perkiraan harus ditandai.'
        );
    }

    /** ⭐ Dan TIDAK muncul untuk yang pasti — ini sisi yang mudah terlewat. */
    public function test_tanda_perkiraan_tidak_muncul_untuk_tanggal_pasti(): void
    {
        $ctl = $this->pasien(['install_is_estimated' => 0]);

        /* ⚠ Menanyakan PANEL-nya, bukan halamannya. Sejak panel dimuat
           belakangan, `ort-kira` memang tidak pernah ada di HTML halaman —
           jadi versi lama uji ini akan HIJAU apa pun isi datanya, dan berhenti
           menjaga sisi yang justru paling mudah terlewat. */
        $this->assertStringNotContainsString(
            'ort-kira', $this->panel($ctl),
            'Tanda yang muncul di semua baris tidak memberi tahu apa pun.'
        );
    }

    public function test_bendera_perkiraan_dibaca_sebagai_boolean(): void
    {
        $ctl = $this->pasien(['install_is_estimated' => 1]);

        $this->assertTrue($ctl->case->install_is_estimated);
        $this->assertFalse($this->pasien(['install_is_estimated' => 0])->case->install_is_estimated);
    }

    // ── 2 · Saringan kerja harian ───────────────────────────────────────────

    /** ⭐ "Belum pernah di-FU" benar-benar menyaring, bukan sekadar ada. */
    public function test_saringan_belum_pernah_di_fu_menyaring(): void
    {
        $this->pasien([], ['fu_date' => null]);
        $this->pasien([], ['fu_date' => Carbon::today()->toDateString(), 'fu_by' => 'Orang Lain']);

        $html = $this->halaman(['q' => self::TANDA, 'status' => 'Belum pernah di-FU']);

        $this->assertCount(1, $this->namaTampil($html), 'Hanya yang belum pernah di-FU yang boleh lolos.');
    }

    /**
     * ⚠ Beda tegas dari "Menunggu" yang sudah ada: itu membuang pasien
     *   terlewat (`sch_date >= hari ini`). Pasien terlewat yang belum pernah
     *   ditelepon justru yang paling perlu ditelepon — membuangnya dari daftar
     *   panggilan adalah persis cara pasien hilang.
     */
    public function test_belum_pernah_di_fu_ikut_menampilkan_yang_terlewat(): void
    {
        $this->pasien([], [
            'fu_date' => null,
            'sch_date' => Carbon::today()->subDays(30)->toDateString(),
        ]);

        $html = $this->halaman(['q' => self::TANDA, 'status' => 'Belum pernah di-FU']);

        $this->assertCount(1, $this->namaTampil($html), 'Pasien terlewat tidak boleh dibuang dari daftar panggilan.');
    }

    /** ⭐ "Saya FU hari ini" = PUNYA SAYA, bukan follow-up siapa pun. */
    public function test_saya_fu_hari_ini_hanya_pekerjaan_sendiri(): void
    {
        $this->pasien([], ['fu_date' => Carbon::today()->toDateString(), 'fu_by' => 'Uji Sales']);
        $this->pasien([], ['fu_date' => Carbon::today()->toDateString(), 'fu_by' => 'Orang Lain']);
        $this->pasien([], ['fu_date' => Carbon::today()->subDay()->toDateString(), 'fu_by' => 'Uji Sales']);

        $html = $this->halaman(['q' => self::TANDA, 'status' => 'Saya FU hari ini'], 'Uji Sales');

        $this->assertCount(1, $this->namaTampil($html), 'Hanya FU saya, dan hanya yang hari ini.');
    }

    /** Tanpa nama di sesi, saringan itu tidak punya arti — kosongkan, jangan bocorkan. */
    public function test_tanpa_nama_pengguna_saringan_saya_tidak_membocorkan_fu_orang_lain(): void
    {
        $this->pasien([], ['fu_date' => Carbon::today()->toDateString(), 'fu_by' => 'Orang Lain']);

        $html = $this->halaman(['q' => self::TANDA, 'status' => 'Saya FU hari ini'], null);

        $this->assertCount(0, $this->namaTampil($html), 'Lebih baik kosong daripada menampilkan pekerjaan orang lain sebagai milik sendiri.');
    }

    /** Opsinya memang ditawarkan di dropdown — saringan yang tak bisa dipilih sama saja tidak ada. */
    public function test_opsi_saringan_ditawarkan_di_dropdown(): void
    {
        $html = $this->halaman();

        $this->assertStringContainsString('Belum pernah di-FU', $html);
        $this->assertStringContainsString('Saya FU hari ini', $html);
    }

    public function test_opsi_saya_disembunyikan_kalau_sesi_tanpa_nama(): void
    {
        $this->assertStringNotContainsString(
            'Saya FU hari ini', $this->halaman([], null),
            'Jangan tawarkan saringan yang sudah pasti kosong.'
        );
    }

    // ── 3 · Paginasi ────────────────────────────────────────────────────────

    public function test_satu_halaman_memuat_lima_puluh_baris(): void
    {
        $this->assertSame(50, OrtQuery::PER_HAL);
    }

    /** Angkanya benar-benar dipakai, bukan cuma konstanta yang menganggur. */
    public function test_konstanta_paginasi_dipakai_kuerinya(): void
    {
        $this->pasien();

        $hal = app(OrtQuery::class)->daftar($this->placeId, 'semua', ['q' => self::TANDA]);

        $this->assertSame(OrtQuery::PER_HAL, $hal->perPage(), 'daftar() harus memakai PER_HAL, bukan 10.');
    }
}
