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
 * Tracking Ortho — seberapa RAPAT irama pasien, dan apa akibatnya di layar.
 *
 * TEMUAN YANG MELAHIRKAN BERKAS INI. Tahap C menandai keyakinan usulan dengan
 * `sch_basis_n` — BANYAKNYA selang yang dipakai menghitung irama — dan layar
 * menyebut usulan "kuat" kalau selangnya 6 ke atas. Itu salah ukur, dan saya
 * yang mengirimnya.
 *
 * Banyaknya data bukan konsistensinya. Pasien berselang 20·90·25·110 punya
 * LEBIH BANYAK selang daripada pasien 28·28·28, tapi iramanya jauh lebih tidak
 * bisa ditebak — dan dengan ukuran lama, yang liar justru dinilai paling
 * meyakinkan. Uji pertama di bawah persis menahan itu.
 *
 * Ukuran yang benar datang dari handoff HUB 1 Okt dan sudah diukur di prod:
 * irama disebut RAPAT kalau `(max − min) / median <= 0,5`, dan hanya **24%**
 * pasien memenuhinya. Angka 24% itulah yang mengikat seluruh rancangan: untuk
 * 76% sisanya tanggal yang kita hitung adalah tebakan dua minggu, dan tidak
 * pantas ditawarkan lewat tombol sekali klik.
 *
 * ⚠ PEMBAGIANNYA TIDAK BISA DIVALIDASI DI DB PENGEMBANGAN. Diukur di sini,
 *   21 dari 22 pasien (95%) masuk "rapat" — lawan 24% di prod. Data
 *   pengembangan selangnya terlalu rapi dan jumlahnya terlalu sedikit. Karena
 *   itu seluruh uji di bawah memakai fixture buatan sendiri, dan angka prod
 *   yang mengikat, bukan angka lokal.
 */
class OrtoTingkatIramaTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-TINGKAT';

    private string $placeId;
    private string $branchId;
    private string $doctorRef = 'UJI-TINGKAT-DOC';

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_case')) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.');
        }
        if (! Schema::hasColumn('ort_case', 'gap_spread_pct')) {
            $this->markTestSkipped('Jalankan database/sql/2026_10_02_ort_sebaran_irama.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }

        $this->placeId  = (string) array_key_first($peta);
        $this->branchId = (string) $peta[$this->placeId]['branch_id'];
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    private function dokterPraktikTiapHari(): void
    {
        $docId = 'uji-tingkat-doc-' . Str::random(6);
        DB::table('raw_doctor')->insert([
            'id' => $docId, 'name' => 'drg. Uji Tingkat',
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
     * Pasien dengan pola selang persis seperti yang diminta, kunjungan
     * terakhirnya hari ini, plus baris cermin supaya tarikan melahirkan usulan.
     *
     * @param  list<int>  $selang  jarak antar kunjungan, urut lama → baru
     */
    private function pasien(array $selang): OrtCase
    {
        $pid     = self::TANDA . '-' . Str::random(6);
        $hariIni = Carbon::today();

        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji Tingkat ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => $hariIni->copy()->subDays(800)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF, 'doctor_ref_id' => $this->doctorRef,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);

        // Dibangun mundur dari hari ini supaya kunjungan terakhirnya pasti
        // hari ini — titik berangkat yang sama dengan tarikan malam.
        $riwayat = [];
        $tgl     = $hariIni->copy();
        foreach (array_reverse($selang) as $s) {
            $tgl = $tgl->copy()->subDays($s);
            $riwayat[] = $tgl->toDateString();
        }

        $seq = 0;
        foreach (array_reverse($riwayat) as $t) {
            OrtControl::create([
                'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => ++$seq,
                'place_id' => $this->placeId, 'is_proposal' => 0,
                'sch_date' => $t, 'sch_source' => OrtControl::SCH_REKAM_MEDIS,
                'visit_date' => $t, 'visit_source' => 'rekam_medis', 'visit_confidence' => 'pasti',
                'outcome' => OrtControl::OUT_HADIR, 'closed_at' => now(),
                'closed_by' => 'uji', 'created_by' => 'uji',
            ]);
        }

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

    // ── Rumusnya sendiri ────────────────────────────────────────────────────

    /**
     * ⭐ INTI BERKAS INI. Yang selangnya LEBIH BANYAK justru yang lebih tidak
     *   bisa dipercaya — kendali langsung atas cacat `$basisN >= 6`.
     */
    public function test_banyak_selang_tidak_membuat_irama_jadi_rapat(): void
    {
        $rapi = OrtCase::irama(['2026-01-01', '2026-01-29', '2026-02-26', '2026-03-26']);
        $liar = OrtCase::irama(['2026-01-01', '2026-01-21', '2026-04-21', '2026-05-16', '2026-09-03']);

        $this->assertSame(OrtCase::BASIS_RAPAT, $rapi['basis'], '28·28·28 itu irama rapat.');
        $this->assertSame(OrtCase::BASIS_LONGGAR, $liar['basis'], '20·90·25·110 bukan irama, sekalipun selangnya lebih banyak.');

        $this->assertGreaterThan(
            $rapi['selang'], $liar['selang'],
            'Prasyarat yang membuat uji ini berarti: yang liar memang punya LEBIH BANYAK selang.'
        );
    }

    public function test_sebaran_dihitung_sebagai_persen_median(): void
    {
        $r = OrtCase::irama(['2026-01-01', '2026-01-29', '2026-02-26', '2026-03-26']);

        $this->assertSame(28, $r['median']);
        $this->assertSame(28, $r['min']);
        $this->assertSame(28, $r['max']);
        $this->assertSame(0, $r['sebar'], 'Selang yang selalu sama persis: sebaran nol.');
    }

    /** Dua selang saja belum cukup untuk disebut rapat, sekalipun keduanya sama. */
    public function test_dua_selang_belum_boleh_disebut_rapat(): void
    {
        $r = OrtCase::irama(['2026-01-01', '2026-01-29', '2026-02-26']);

        $this->assertSame(0, $r['sebar'], 'Sebarannya memang nol...');
        $this->assertSame(
            OrtCase::BASIS_LONGGAR, $r['basis'],
            '...tapi dua selang terlalu sedikit untuk menyimpulkan kebiasaan.'
        );
    }

    public function test_riwayat_terlalu_pendek_jadi_default(): void
    {
        $r = OrtCase::irama(['2026-01-01', '2026-01-29']);

        $this->assertNull($r['median']);
        $this->assertNull($r['sebar']);
        $this->assertSame(OrtCase::BASIS_DEFAULT, $r['basis']);
    }

    // ── Yang disimpan ───────────────────────────────────────────────────────

    public function test_rollup_menyimpan_sebaran(): void
    {
        $kasus = $this->pasien([28, 28, 29, 28]);

        app(OrtRollup::class)->refresh($kasus->id);

        $this->assertNotNull($kasus->refresh()->gap_spread_pct);
        $this->assertLessThanOrEqual(
            OrtCase::IRAMA_RAPAT_PCT, $kasus->gap_spread_pct,
            'Selang 28·28·29·28 harus jatuh di bawah ambang rapat.'
        );
    }

    /** ⭐ Tingkatnya ikut tersimpan di usulan, bukan diturunkan saat render. */
    public function test_usulan_menyimpan_tingkat_rapat(): void
    {
        $this->dokterPraktikTiapHari();
        $kasus = $this->pasien([28, 28, 29, 28]);

        $this->tarik();

        $this->assertSame(OrtCase::BASIS_RAPAT, $this->usulan($kasus)?->sch_basis);
    }

    public function test_usulan_irama_liar_ditandai_longgar(): void
    {
        $this->dokterPraktikTiapHari();
        $kasus = $this->pasien([20, 90, 25, 110]);

        $this->tarik();

        $u = $this->usulan($kasus);
        $this->assertSame(OrtCase::BASIS_LONGGAR, $u?->sch_basis);
        $this->assertSame(4, (int) $u->sch_basis_n, 'Banyaknya selang tetap dicatat — ia jujur, cuma bukan penentu.');
    }

    public function test_usulan_riwayat_pendek_ditandai_default(): void
    {
        $this->dokterPraktikTiapHari();
        $kasus = $this->pasien([30]);

        $this->tarik();

        $this->assertSame(OrtCase::BASIS_DEFAULT, $this->usulan($kasus)?->sch_basis);
    }

    /** `ort:rapikan-usulan` menulis ulang tingkat untuk usulan yang sudah ada. */
    public function test_rapikan_usulan_memperbarui_tingkat(): void
    {
        $this->dokterPraktikTiapHari();
        $kasus = $this->pasien([28, 28, 29, 28]);
        $this->tarik();

        // Tandai seolah lahir sebelum perubahan ini.
        $u = $this->usulan($kasus);
        $u->forceFill(['sch_basis' => 'irama'])->save();

        Artisan::call('ort:rapikan-usulan', [
            '--cabang' => $this->placeId, '--commit' => true,
        ]);

        $this->assertSame(
            OrtCase::BASIS_RAPAT, $u->fresh()?->sch_basis,
            'Baris lama bertanda "irama" harus naik ke tingkat yang sebenarnya.'
        );
    }

    /** `avg_gap_days` dan `sch_basis_n` TIDAK dibuang — keduanya tetap jujur. */
    public function test_ukuran_lama_tidak_dibuang(): void
    {
        $kasus = $this->pasien([28, 28, 29, 28]);

        app(OrtRollup::class)->refresh($kasus->id);
        $kasus->refresh();

        $this->assertNotNull($kasus->avg_gap_days);
        $this->assertNotNull($kasus->median_gap_days);
    }
}
