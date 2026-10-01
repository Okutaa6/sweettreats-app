<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtUsulan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — siapa yang SENGAJA tidak diberi jatuh tempo, dan ke mana
 * mereka pergi.
 *
 * ⚠ BERKAS INI DULU MENJAGA EMPAT PAGAR JADWAL: klinik tutup Senin, hari
 *   praktik dokter, kapasitas cabang, dan pasien hilang. Tiga yang pertama
 *   sudah DIBUANG bersama pagarnya, dan ujinya ikut dibuang — bukan
 *   dilonggarkan diam-diam.
 *
 * Alasannya: kolom Jadwal adalah JATUH TEMPO, bukan janji. Tidak ada slot yang
 * dipesan dan tidak ada pasien yang diberi tahu, jadi tidak ada yang bisa
 * mustahil. Ketersediaan dokter diperiksa saat MEMESAN, oleh modul Reservasi.
 * Dan penumpukan 651 yang melahirkan pagar-pagar itu ternyata datang dari
 * LANTAI MIN_MAJU_HARI, bukan dari ketiadaan pagar — tanpa lantai dan tanpa
 * pagar kapasitas, 34 pasien tersebar di 29 tanggal berbeda.
 *
 * Yang TERSISA di sini satu aturan produk, dan ia bukan soal tanggal melainkan
 * soal beban kerja:
 *
 *   • pasien hilang lebih dari 6 bulan tidak diberi jatuh tempo sama sekali.
 *     Dari 927 pasien Bintaro, 495 hilang lebih dari itu; memberi mereka
 *     tanggal berarti membanjiri daftar harian dengan nama yang tidak akan
 *     datang.
 *
 * Plus dua hal yang menjaga keputusan itu tidak merugikan siapa pun: bendera
 * `--tanpa-usulan` untuk tarikan mundur pertama, dan jaminan bahwa pasien yang
 * tidak diberi jatuh tempo TETAP TERLIHAT di antrean penjadwalan.
 *
 * Fixture-nya dibuat sendiri (dokter + jadwal praktik + kasus + cermin), karena
 * DB pengembangan tidak punya dokter ortho ber-jadwal sama sekali. Semuanya
 * ikut rollback DatabaseTransactions.
 */
class OrtoUsulanPagarTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-PAGAR';

    private string $placeId;
    private string $branchId;
    private string $doctorRef = 'UJI-DOC-RABU';

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_case') || ! Schema::hasTable('omnicare_sales_detail')) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }

        $this->placeId  = (string) array_key_first($peta);
        $this->branchId = (string) $peta[$this->placeId]['branch_id'];
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    /** Dokter yang praktik HANYA di hari-hari tertentu (ISO: 1 Senin … 7 Minggu). */
    private function dokterPraktikHari(array $hariIso): void
    {
        $docId = 'uji-doc-' . Str::random(8);
        DB::table('raw_doctor')->insert([
            'id' => $docId, 'name' => 'drg. Uji Pagar',
            'omnicare_doc_id' => $this->doctorRef, 'is_active' => 1,
        ]);

        foreach ($hariIso as $h) {
            DB::table('raw_doctor_schedule')->insert([
                'id' => (string) Str::uuid(), 'raw_doctor_id' => $docId,
                'raw_branch_id' => $this->branchId, 'day' => $h,
                'start_hour' => '10:00:00', 'end_hour' => '20:00:00', 'type' => 'work',
            ]);
        }
    }

    /**
     * Satu kasus + satu kontrol terbuka di $schDate, plus baris cermin pada
     * tanggal itu juga (selisih 0 hari → pasti cocok → ditutup Hadir →
     * memicu kelahiran usulan).
     */
    private function pasien(string $schDate, ?string $doctorRef = null): OrtCase
    {
        $pid = self::TANDA . '-' . Str::random(6);

        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji Pagar ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => Carbon::parse($schDate)->subDays(200)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF, 'doctor_ref_id' => $doctorRef,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);

        OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 1,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date' => $schDate, 'sch_source' => OrtControl::SCH_MANUAL, 'created_by' => 'uji',
        ]);

        DB::table('omnicare_sales_detail')->insert([
            'trx_id' => self::TANDA . '-' . Str::random(10), 'trx_date' => $schDate,
            'p_id' => $pid, 'place_id' => $this->placeId, 'branch_name' => 'Uji',
            'item_name' => 'Control Metal Braces', 'item_extra_name' => '',
            'item_code' => '26CON178', 'sales_notes' => self::TANDA,
        ]);

        return $kasus;
    }

    private function tarik(array $opsi = []): void
    {
        $tgl = DB::table('omnicare_sales_detail')->where('sales_notes', self::TANDA);
        Artisan::call('ort:pull-visits', array_merge([
            '--sejak'  => (string) $tgl->min('trx_date'),
            '--sampai' => (string) $tgl->max('trx_date'),
            '--cabang' => $this->placeId,
        ], $opsi));
    }

    /** @return \Illuminate\Support\Collection<int,OrtControl> usulan yang lahir */
    private function usulan()
    {
        $ids = OrtCase::where('seed_source', self::TANDA)->pluck('id');

        return OrtControl::whereIn('ort_case_id', $ids)->where('is_proposal', 1)->get();
    }

    // ── Pagar yang tersisa ─────────────────────────────────────

    public function test_pasien_hilang_tidak_diusulkan(): void
    {
        $this->dokterPraktikHari([1, 2, 3, 4, 5, 6, 7]);
        $this->pasien(Carbon::today()->subDays(OrtCase::HILANG_HARI + 30)->toDateString(), $this->doctorRef);
        $this->tarik();

        $this->assertCount(
            0, $this->usulan(),
            'Pasien hilang >6 bulan tidak diusulkan tanggal — keputusan pemilik produk.'
        );
    }

    // ── Bendera tarikan mundur ──────────────────────────────────────────────

    /** ⭐ Tanpa ini, hari pertama di prod lahir 651 tombol "Konfirmasi jadwal". */
    public function test_tanpa_usulan_mengisi_fakta_tapi_nol_usulan(): void
    {
        $this->dokterPraktikHari([1, 2, 3, 4, 5, 6, 7]);
        $kasus = $this->pasien(Carbon::today()->subDays(20)->toDateString(), $this->doctorRef);

        $this->tarik(['--tanpa-usulan' => true]);

        $this->assertCount(0, $this->usulan(), '--tanpa-usulan harus melahirkan NOL usulan.');
        $this->assertNotNull(
            OrtControl::where('ort_case_id', $kasus->id)->whereNotNull('visit_date')->first(),
            'Fakta kunjungannya tetap harus terisi — itu gunanya tarikan mundur.'
        );
    }

    // ── Yang tidak diusulkan tetap terlihat ─────────────────────────────────

    /**
     * Revisi 3 menghapus kelompok ketiga antrean karena barisnya akan tinggal
     * di List sebagai usulan. Begitu kita sengaja tidak mengusulkan, alasan itu
     * gugur — tanpa kelompok ini pasiennya lenyap tanpa penjelasan.
     */
    public function test_yang_tak_diusulkan_muncul_di_kelompok_baru_selesai(): void
    {
        $this->pasien(Carbon::today()->subDays(5)->toDateString(), 'DOC-TIDAK-ADA');
        $this->tarik();

        $kelompok = app(\App\Services\Ortho\OrtQuery::class)
            ->kelompokkanAntrean(OrtCase::where('seed_source', self::TANDA)->get());

        $this->assertArrayHasKey('baru', $kelompok, 'Kelompok ketiga harus ada lagi.');
        $this->assertCount(
            1, $kelompok['baru']['baris'],
            'Pasien yang baru selesai kontrol tanpa usulan harus terlihat, bukan lenyap.'
        );
    }

}
