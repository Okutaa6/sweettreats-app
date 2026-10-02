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
 * Tracking Ortho — kolom Jadwal menyebut PERKIRAAN, bukan tanggal.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * KENAPA
 * ────────────────────────────────────────────────────────────────────────────
 * Tanggal di kolom itu tebakan. Irama pasien sendiri meleset 14 hari (terukur
 * prod), dan tanggal sungguhannya baru lahir saat Sales menelepon lalu
 * menawarkan hari praktik dokter. Menuliskan "28 Okt '26" memberi ketepatan
 * yang tidak kita punya — dan Sales menyebutkannya ke pasien.
 *
 * Dibaca ulang dari brief aslinya (Brief-Ortho-2026-09-09), tiga hal:
 *
 *   a. Sheet Sales TIDAK punya kolom "tanggal kontrol berikutnya". Berkas
 *      Cinere punya 149 kolom, 30 di antaranya kolom BULAN. Petugas mencatat
 *      tanggal pasien DATANG di bawah bulannya; tidak ada tempat untuk
 *      tanggal yang belum terjadi.
 *   b. Target brief butir 1 justru ingin kolom bulan itu HILANG, dan tak satu
 *      pun dari tujuh butir target meminta tanggal ramalan.
 *   c. Kata Anisa sendiri: "di kasih tau kalau udah lama ga kontrol dan di
 *      tawarin jadwal" — pemicunya DURASI, tanggalnya DITAWARKAN saat menelepon.
 *
 * Satuannya mengikuti yang memang dipakai Sales: bulan untuk yang jauh, pekan
 * untuk yang dekat.
 *
 * ⚠ Tanggalnya sendiri TIDAK dibuang — ia tetap mengurutkan daftar, memilah
 *   sub-tab, mengisi kartu, dan jadi default lembar Buat Reservasi. Yang
 *   hilang cuma angkanya di layar, dan uji di bawah menjaga keduanya.
 */
class OrtoPerkiraanJadwalTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-PERKIRA';

    private string $placeId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_control')) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.');
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

    private function pasien(int $jatuhTempoDalamHari, array $kontrolUbah = []): OrtControl
    {
        $pid     = self::TANDA . '-' . Str::random(6);
        $hariIni = Carbon::today();

        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji Perkira ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => $hariIni->copy()->subDays(400)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);

        return OrtControl::create(array_merge([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 1,
            'place_id' => $this->placeId, 'is_proposal' => 1,
            'sch_date' => $hariIni->copy()->addDays($jatuhTempoDalamHari)->toDateString(),
            'sch_source' => OrtControl::SCH_SISTEM, 'created_by' => 'sistem',
        ], $kontrolUbah));
    }

    private function halaman(array $query = []): string
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
            'isLoggedIn' => true, 'id' => 'uji-perkira', 'group_id' => $gid,
            'group_name' => 'Supervisor', 'name' => 'Uji Sales', 'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600,
            'login_at' => time(), 'pa_touch' => time(),
        ])->get($url)->assertOk()->getContent();
    }

    /** Isi setiap sel kolom JADWAL di tabel daftar kerja. */
    private function selJadwal(string $html): array
    {
        /* Ditangkap lewat penanda `data-ort-jadwal`, bukan lewat bentuk HTML-nya.
           Versi lama mencocokkan `<div class="ort-cell">teks</div>` dan ikut
           menyapu sel Dokter/Follow-up/Reservasi; ia juga putus total begitu sel
           Jadwal punya elemen anak — "tidak ketemu" lalu terbaca sama dengan
           "isinya salah".

           ⚠ Mengembalikan HTML DALAM sel, bukan teks polos: kelas penanda
             (ort-proposal) dan atribut title ikut diperiksa uji di bawah. */
        preg_match_all('~<div class="ort-cell[^"]*" data-ort-jadwal>(.*?)</div>~s', $html, $m);

        return array_map('trim', $m[1]);
    }

    // ── Rumusnya: tiap batas diuji persis ───────────────────────────────────

    /** ⭐ Sudah lewat tidak mengulang chip Status — chip yang membawa berapa harinya. */
    public function test_yang_sudah_lewat_berbunyi_sudah_waktunya(): void
    {
        $h = Carbon::parse('2026-10-02');

        foreach ([-1, -6, -40, -300] as $d) {
            $this->assertSame(
                'Sudah waktunya',
                OrtControl::perkiraanJatuhTempo($h->copy()->addDays($d), $h),
                "Selisih {$d} hari."
            );
        }
    }

    public function test_batas_hari_ini_dan_pekan_ini(): void
    {
        $h = Carbon::parse('2026-10-02');

        $this->assertSame('Hari ini', OrtControl::perkiraanJatuhTempo($h, $h));
        $this->assertSame('Pekan ini', OrtControl::perkiraanJatuhTempo($h->copy()->addDay(), $h));
        $this->assertSame('Pekan ini', OrtControl::perkiraanJatuhTempo($h->copy()->addDays(6), $h));
    }

    /** ⭐ Batas pekan dan bulan, dua sisi masing-masing. */
    public function test_batas_pekan_dan_bulan(): void
    {
        $h = Carbon::parse('2026-10-02');

        $this->assertSame('± 1 pekan lagi', OrtControl::perkiraanJatuhTempo($h->copy()->addDays(7), $h));
        $this->assertSame('± 4 pekan lagi', OrtControl::perkiraanJatuhTempo($h->copy()->addDays(27), $h));
        $this->assertSame('± Okt', OrtControl::perkiraanJatuhTempo($h->copy()->addDays(28), $h));
        $this->assertSame('± Nov', OrtControl::perkiraanJatuhTempo($h->copy()->addDays(45), $h));
    }

    /** Menyeberang tahun menyertakan tahunnya — "± Jan" di Desember ambigu. */
    public function test_beda_tahun_menyertakan_tahun(): void
    {
        // 20 Nov + 28 hari = 18 Des (tahun sama) · + 50 hari = 9 Jan (beda tahun).
        $h = Carbon::parse('2026-11-20');

        $this->assertSame('± Des', OrtControl::perkiraanJatuhTempo($h->copy()->addDays(28), $h));
        $this->assertSame("± Jan '27", OrtControl::perkiraanJatuhTempo($h->copy()->addDays(50), $h));
    }

    // ── Yang dilihat di layar ───────────────────────────────────────────────

    /** ⭐ KENDALI UTAMA: nol tanggal kalender di kolom Jadwal. */
    /**
     * ⭐ DIBALIK 5 Okt 2026 — dulu uji ini menuntut kolom Jadwal TIDAK memuat
     * tanggal. Sekarang justru wajib memuatnya.
     *
     * Yang membatalkan keputusan lama bukan selera, melainkan efeknya di layar
     * kerja: perkiraan memberi masa depan EMPAT tingkat (Hari ini · Pekan ini ·
     * ± N pekan lagi · ± Nov) tapi masa lalu cuma SATU ember, "Sudah waktunya".
     * Daftar diurutkan dari jatuh tempo terdekat, jadi semua yang terlambat
     * menumpuk di paling atas — tepat di situ mereka kehilangan pembeda.
     * Terukur di DB pengembangan: enam baris teratas semuanya "Sudah waktunya",
     * padahal yang satu telat 32 hari dan lima sisanya 2–3 hari.
     *
     * Itu penyakit yang sama dengan lantai MIN_MAJU_HARI yang sudah dicabut —
     * yang satu meratakan DATANYA, yang satu lagi meratakan TAMPILANNYA.
     */
    public function test_kolom_jadwal_memuat_tanggal_kalender(): void
    {
        $this->pasien(45);
        $this->pasien(10);
        $this->pasien(-20);

        $sel = $this->selJadwal($this->halaman(['q' => self::TANDA]));

        $this->assertNotEmpty($sel, 'Prasyarat: barisnya memang terender.');

        foreach ($sel as $isi) {
            $this->assertMatchesRegularExpression(
                "~\d{1,2}\s+\w{3}\s+&#039;\d{2}~u", $isi,
                "Kolom Jadwal wajib memuat tanggal kalender, yang ada: {$isi}"
            );
        }
    }

    /**
     * Tanggal yang masih hitungan mesin tetap ditandai — kejujurannya dipindah,
     * bukan dibuang. Begitu Sales menyentuhnya (is_proposal = 0), tandanya hilang.
     */
    public function test_tanggal_hitungan_mesin_ditandai(): void
    {
        $this->pasien(45, ['is_proposal' => 1]);
        $this->pasien(44, ['is_proposal' => 0]);

        $html = $this->halaman(['q' => self::TANDA]);
        $sel  = $this->selJadwal($html);

        $this->assertCount(2, $sel, 'Prasyarat: dua baris terender.');
        $this->assertSame(
            1,
            count(array_filter($sel, fn ($x) => str_contains($x, 'ort-proposal'))),
            'Tepat satu sel bergaris putus — hanya yang is_proposal.'
        );
    }

    /**
     * Kalimat perkiraan TIDAK dibuang — ia pindah ke tooltip. Jadi "kira-kira
     * kapan" tetap terbaca tanpa memakan lebar kolom 112px, dan rumusnya
     * (`perkiraanJatuhTempo`) tetap punya pemakai sehingga uji satuan di atas
     * tidak menjaga kode mati.
     */
    public function test_kalimat_perkiraan_pindah_ke_tooltip(): void
    {
        $this->pasien(45);

        $sel = $this->selJadwal($this->halaman(['q' => self::TANDA]));

        $this->assertNotEmpty($sel, 'Prasyarat: barisnya memang terender.');
        $this->assertNotEmpty(
            array_filter($sel, fn ($x) => str_contains($x, 'title="±')),
            'Perkiraan harus ada di atribut title. Yang ada: ' . implode(' | ', $sel)
        );
    }

    /**
     * ⭐ Tanggal yang NYATA tidak boleh ikut terhapus.
     *
     * Follow-up dan Reservasi mencatat hal yang sudah benar-benar terjadi atau
     * sudah benar-benar dipesan — keduanya tetap bertanggal.
     */
    public function test_tanggal_follow_up_tetap_tampil(): void
    {
        $this->pasien(10, [
            'fu_date' => Carbon::today()->toDateString(),
            'fu_channel' => 'wa', 'fu_by' => 'Uji Sales',
        ]);

        $this->assertStringContainsString(
            'ort-fu', $this->halaman(['q' => self::TANDA]),
            'Tanggal follow-up itu fakta dan harus tetap terlihat.'
        );
    }

    // ── Yang TIDAK boleh ikut berubah ───────────────────────────────────────

    /** Tanggalnya tetap mengurutkan daftar: paling tertinggal di atas. */
    public function test_urutan_daftar_tidak_berubah(): void
    {
        $this->pasien(40);
        $this->pasien(-30);
        $this->pasien(5);

        $baris = app(OrtQuery::class)->daftar($this->placeId, 'semua', ['q' => self::TANDA]);
        $tgl   = $baris->pluck('sch_date')->map(fn ($d) => (string) $d)->all();
        $urut  = $tgl;
        sort($urut);

        $this->assertSame($urut, $tgl, 'Daftar tetap diurut jatuh tempo menaik.');
    }

    /** Sub-tab tetap memilah dengan angka yang sama. */
    public function test_sub_tab_tetap_memilah(): void
    {
        $sebelum = app(OrtQuery::class)->hitungUrgensi($this->placeId);

        $this->pasien(-30);   // terlewat
        $this->pasien(3);     // minggu ini
        $this->pasien(40);    // mendatang

        $sesudah = app(OrtQuery::class)->hitungUrgensi($this->placeId);

        $this->assertSame($sebelum['terlewat'] + 1, $sesudah['terlewat']);
        $this->assertSame($sebelum['minggu-ini'] + 1, $sesudah['minggu-ini']);
        $this->assertSame($sebelum['mendatang'] + 1, $sesudah['mendatang']);
    }

    /** Lembar Buat Reservasi tetap menerima default tanggal dari jatuh tempo. */
    public function test_lembar_reservasi_tetap_punya_default_tanggal(): void
    {
        $ctl = $this->pasien(20);

        $html = $this->halaman(['sheet' => 'reservasi', 'kontrol' => $ctl->id]);

        $this->assertStringContainsString(
            'value="' . $ctl->sch_date->toDateString() . '"', $html,
            'Tanggalnya berhenti dipajang, bukan berhenti dipakai.'
        );
    }
}
