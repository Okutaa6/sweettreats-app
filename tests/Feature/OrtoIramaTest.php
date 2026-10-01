<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtRollup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — Tahap C: usulan jadwal mengikuti IRAMA PASIEN, bukan +30
 * untuk semua orang.
 *
 * TEMUAN YANG MELAHIRKAN BERKAS INI (audit HUB, 1 Okt 2026). Diukur di prod
 * terhadap kunjungan yang benar-benar terjadi: aturan "+30 hari untuk semua"
 * meleset rata-rata 22 hari; irama pasien itu sendiri meleset 14 dan menang
 * di 86% kasus. Masuk akal — pasien yang terbiasa datang tiap 26 hari tidak
 * berubah jadi pasien 30 hari hanya karena angka itu bulat.
 *
 * Yang dijaga berkas ini, dan yang paling mudah salah:
 *
 *   ⚠ MEDIAN, bukan rata-rata. `avg_gap_days` sudah lama ada di tabel dan
 *     menggoda untuk dipakai ulang — tapi ia tertarik pencilan: satu kali
 *     pasien hilang 8 bulan lalu kembali sudah cukup menggeser rata-ratanya
 *     puluhan hari, dan dari situ seluruh usulan berikutnya ikut melar.
 *     Uji "pencilan" di bawah yang menahannya.
 *
 *   ⚠ Irama DIJEPIT [21, 60] hari. Tanpa jepitan itu, pasien yang pernah
 *     menghilang mendapat usulan setahun ke depan — sistem ikut melembagakan
 *     keterlambatannya alih-alih menjadwalkannya kembali.
 *
 *   • Kurang dari 2 selang bukan irama, itu satu kebetulan → jatuh ke +30.
 *   • Dasar tiap usulan DISIMPAN (sch_basis, sch_basis_n), tidak diturunkan
 *     saat render — kalau diturunkan, usulan lama ikut berubah pengakuannya
 *     tiap ada kunjungan baru.
 *
 * ⚠ Pagar jadwal (Senin tutup, hari praktik dokter, kapasitas) sudah DIBUANG
 *   dari mesin usulan: kolom Jadwal adalah jatuh tempo, bukan janji. Jadi
 *   tanggal yang dihitung di sini tidak pernah digeser lagi oleh apa pun, dan
 *   uji di bawah bisa menyebut tanggalnya persis.
 */
class OrtoIramaTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-IRAMA';

    private string $placeId;
    private string $branchId;
    private string $doctorRef = 'UJI-IRAMA-DOC';

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_case') || ! Schema::hasTable('omnicare_sales_detail')) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.');
        }
        if (! Schema::hasColumn('ort_case', 'median_gap_days')) {
            $this->markTestSkipped('Jalankan database/sql/2026_10_01_ort_tahap_c.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }

        $this->placeId  = (string) array_key_first($peta);
        $this->branchId = (string) $peta[$this->placeId]['branch_id'];

        $this->dokterPraktikTiapHari();
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    private function dokterPraktikTiapHari(): void
    {
        $docId = 'uji-irama-doc-' . Str::random(6);
        DB::table('raw_doctor')->insert([
            'id' => $docId, 'name' => 'drg. Uji Irama',
            'omnicare_doc_id' => $this->doctorRef, 'is_active' => 1,
        ]);

        foreach ([1, 2, 3, 4, 5, 6, 7] as $h) {
            DB::table('raw_doctor_schedule')->insert([
                'id' => (string) Str::uuid(), 'raw_doctor_id' => $docId,
                'raw_branch_id' => $this->branchId, 'day' => $h,
                'start_hour' => '10:00:00', 'end_hour' => '20:00:00', 'type' => 'work',
            ]);
        }
    }

    /**
     * Pasien dengan riwayat kunjungan berirama $selangHari, sebanyak
     * $jumlahLampau kunjungan di masa lalu, lalu satu kunjungan HARI INI yang
     * memicu lahirnya usulan.
     */
    private function pasienBerirama(int $selangHari, int $jumlahLampau): OrtCase
    {
        $pid     = self::TANDA . '-' . Str::random(6);
        $hariIni = Carbon::today();

        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji Irama ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => $hariIni->copy()->subDays($selangHari * ($jumlahLampau + 2))->toDateString(),
            'status' => OrtCase::STATUS_AKTIF, 'doctor_ref_id' => $this->doctorRef,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);

        // Riwayat: kunjungan lampau yang sudah tertutup, berjarak tetap.
        $seq = 0;
        for ($i = $jumlahLampau; $i >= 1; $i--) {
            $tgl = $hariIni->copy()->subDays($selangHari * $i)->toDateString();
            OrtControl::create([
                'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => ++$seq,
                'place_id' => $this->placeId, 'is_proposal' => 0,
                'sch_date' => $tgl, 'sch_source' => OrtControl::SCH_REKAM_MEDIS,
                'visit_date' => $tgl, 'visit_source' => 'rekam_medis', 'visit_confidence' => 'pasti',
                'outcome' => OrtControl::OUT_HADIR, 'closed_at' => now(),
                'closed_by' => 'uji', 'created_by' => 'uji',
            ]);
        }

        // Jadwal terbuka HARI INI + baris cermin di tanggal yang sama: selisih
        // 0 hari → pasti cocok → ditutup Hadir → usulan lahir.
        OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => ++$seq,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date' => $hariIni->toDateString(), 'sch_source' => OrtControl::SCH_MANUAL,
            'created_by' => 'uji',
        ]);

        DB::table('omnicare_sales_detail')->insert([
            'trx_id' => self::TANDA . '-' . Str::random(10), 'trx_date' => $hariIni->toDateString(),
            'p_id' => $pid, 'place_id' => $this->placeId, 'branch_name' => 'Uji',
            'item_name' => 'Control Metal Braces', 'item_extra_name' => '',
            'item_code' => '26CON178', 'sales_notes' => self::TANDA,
        ]);

        return $kasus;
    }

    /** Tarikan dengan pagar kapasitas dilonggarkan — yang diuji titik awalnya. */
    private function tarik(): void
    {
        Artisan::call('ort:pull-visits', [
            '--sejak'  => Carbon::today()->toDateString(),
            '--sampai' => Carbon::today()->toDateString(),
            '--cabang' => $this->placeId,
        ]);
    }

    private function usulan(OrtCase $kasus): ?OrtControl
    {
        return OrtControl::where('ort_case_id', $kasus->id)->where('is_proposal', 1)->first();
    }

    // ── Rumus iramanya sendiri ──────────────────────────────────────────────

    /** ⭐ Inti keputusannya: median, bukan rata-rata. */
    public function test_pencilan_tidak_menggeser_irama(): void
    {
        // Tiga selang 28 hari, lalu sekali hilang 240 hari dan kembali.
        $tanggal = ['2026-01-01', '2026-01-29', '2026-02-26', '2026-03-26', '2026-11-21'];

        $r = OrtCase::irama($tanggal);

        $this->assertSame(28, $r['median'], 'Median harus bertahan di 28 meski ada pencilan 240 hari.');
        $this->assertSame(4, $r['selang']);

        // Pembanding: rata-rata yang selama ini dipakai meleset jauh.
        $rata = (int) round((28 + 28 + 28 + 240) / 4);
        $this->assertGreaterThan(
            60, $rata,
            'Pembuktian kenapa rata-rata tidak dipakai: satu pencilan membuatnya ' . $rata . ' hari.'
        );
    }

    public function test_kurang_dari_dua_selang_bukan_irama(): void
    {
        $this->assertNull(OrtCase::irama(['2026-01-01'])['median'], 'Satu kunjungan: nol selang.');
        $this->assertNull(OrtCase::irama(['2026-01-01', '2026-01-29'])['median'], 'Satu selang itu kebetulan, bukan irama.');
        $this->assertSame(28, OrtCase::irama(['2026-01-01', '2026-01-29', '2026-02-26'])['median']);
    }

    /** Dua baris di hari yang sama itu satu kunjungan, bukan selang nol hari. */
    public function test_kunjungan_hari_sama_tidak_dihitung_sebagai_selang(): void
    {
        $r = OrtCase::irama(['2026-01-01', '2026-01-01', '2026-01-29', '2026-02-26']);

        $this->assertSame(28, $r['median']);
        $this->assertSame(2, $r['selang'], 'Selang nol hari tidak boleh ikut terhitung.');
    }

    // ── Yang dipakai saat mengusulkan ───────────────────────────────────────

    /** ⭐ Usulan berangkat dari irama pasien, bukan dari angka 30. */
    public function test_usulan_memakai_irama_pasien(): void
    {
        $kasus = $this->pasienBerirama(selangHari: 28, jumlahLampau: 3);

        $this->tarik();

        $u = $this->usulan($kasus);
        $this->assertNotNull($u, 'Usulan harus lahir.');
        $this->assertSame(
            Carbon::today()->addDays(28)->toDateString(),
            $u->sch_date->toDateString(),
            'Irama pasien 28 hari, jadi titik awalnya hari ini + 28 — bukan + 30.'
        );
        // Selang 28 hari yang selalu sama persis: tingkatnya RAPAT. Tingkat
        // keyakinannya sendiri diuji di OrtoTingkatIramaTest; di sini yang
        // penting cuma bahwa dasarnya memang irama, bukan jarak baku.
        $this->assertSame(OrtCase::BASIS_RAPAT, $u->sch_basis);
        $this->assertSame(3, (int) $u->sch_basis_n, 'Empat kunjungan berarti tiga selang.');
    }

    /** Tanpa cukup riwayat, sistem kembali ke +30 dan MENGAKUINYA. */
    public function test_riwayat_pendek_jatuh_ke_tiga_puluh_hari(): void
    {
        $kasus = $this->pasienBerirama(selangHari: 28, jumlahLampau: 1);

        $this->tarik();

        $u = $this->usulan($kasus);
        $this->assertNotNull($u);
        $this->assertSame(
            Carbon::today()->addDays(OrtControl::USULAN_MAJU_HARI)->toDateString(),
            $u->sch_date->toDateString(),
            'Satu selang saja → jatuh ke jarak baku 30 hari.'
        );
        $this->assertSame('default', $u->sch_basis, 'Dasarnya harus diakui "default", bukan menyamar sebagai irama.');
    }

    /** ⭐ Pasien yang pernah hilang tidak dijadwalkan setahun ke depan. */
    public function test_irama_terlalu_panjang_dijepit(): void
    {
        $kasus = $this->pasienBerirama(selangHari: 120, jumlahLampau: 3);

        $this->tarik();

        $u = $this->usulan($kasus);
        $this->assertNotNull($u);
        $this->assertSame(
            Carbon::today()->addDays(OrtCase::IRAMA_MAX_HARI)->toDateString(),
            $u->sch_date->toDateString(),
            'Selang 120 hari bukan irama melainkan kelalaian — dijepit di ' . OrtCase::IRAMA_MAX_HARI . ' hari.'
        );
    }

    public function test_irama_terlalu_pendek_dijepit(): void
    {
        $kasus = $this->pasienBerirama(selangHari: 10, jumlahLampau: 3);

        $this->tarik();

        $u = $this->usulan($kasus);
        $this->assertNotNull($u);
        $this->assertSame(
            Carbon::today()->addDays(OrtCase::IRAMA_MIN_HARI)->toDateString(),
            $u->sch_date->toDateString(),
            'Selang 10 hari biasanya perbaikan bracket lepas, bukan kontrol — dijepit di ' . OrtCase::IRAMA_MIN_HARI . '.'
        );
    }

    /**
     * Irama dihitung SEGAR saat mengusulkan, bukan dibaca dari kolom.
     *
     * Kalau OrtUsulan membaca median_gap_days, kunjungan yang baru saja
     * ditulis tarikan malam ini belum ikut terhitung — kolom itu baru
     * disegarkan OrtRollup sesudah seluruh kasus selesai diproses. Pasien ini
     * sengaja dibuat berkolom KOSONG supaya bedanya kelihatan.
     */
    public function test_irama_dihitung_segar_bukan_dari_kolom(): void
    {
        $kasus = $this->pasienBerirama(selangHari: 28, jumlahLampau: 3);
        $kasus->forceFill(['median_gap_days' => null])->save();

        $this->tarik();

        $this->assertNotSame(
            OrtCase::BASIS_DEFAULT, $this->usulan($kasus)?->sch_basis,
            'Kolom kosong tidak boleh membuat sistem jatuh ke +30 — iramanya dihitung ulang.'
        );
    }

    // ── Yang disimpan untuk ditampilkan ─────────────────────────────────────

    public function test_rollup_menyimpan_median_di_samping_rata_rata(): void
    {
        $kasus = $this->pasienBerirama(selangHari: 28, jumlahLampau: 3);

        // Satu kunjungan pencilan: rata-rata melar, median tidak.
        OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 99,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date' => Carbon::today()->subDays(400)->toDateString(),
            'sch_source' => OrtControl::SCH_REKAM_MEDIS,
            'visit_date' => Carbon::today()->subDays(400)->toDateString(),
            'visit_source' => 'rekam_medis', 'visit_confidence' => 'pasti',
            'outcome' => OrtControl::OUT_HADIR, 'closed_at' => now(),
            'closed_by' => 'uji', 'created_by' => 'uji',
        ]);

        app(OrtRollup::class)->refresh($kasus->id);
        $kasus->refresh();

        $this->assertSame(28, $kasus->median_gap_days, 'Median bertahan di 28.');
        $this->assertGreaterThan(
            $kasus->median_gap_days, $kasus->avg_gap_days,
            'Rata-rata harus terlihat melar oleh pencilan — itu sebabnya ia tidak dipakai mengusulkan.'
        );
    }

    /** `avg_gap_days` TIDAK dibuang — median mendampingi, bukan menggantikan. */
    public function test_rata_rata_tetap_diisi(): void
    {
        $kasus = $this->pasienBerirama(selangHari: 28, jumlahLampau: 3);

        app(OrtRollup::class)->refresh($kasus->id);

        $this->assertNotNull($kasus->refresh()->avg_gap_days);
    }
}
