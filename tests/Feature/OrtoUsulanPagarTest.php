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
 * Tracking Ortho — pagar mesin usulan jadwal.
 *
 * TEMUAN YANG MELAHIRKAN BERKAS INI (audit HUB, 1 Okt 2026). Tarikan mundur di
 * data prod penuh melahirkan 1.154 usulan, 651 di antaranya pada SATU tanggal
 * yang sama. Sebabnya aturan "kunjungan + 30, dijepit supaya tidak lahir
 * terlewat": saat menarik riwayat berbulan-bulan, setiap pasien yang +30-nya
 * sudah lewat dijepit ke tanggal dekat yang sama.
 *
 * Yang membuatnya mahal bukan kerapiannya. Tanggal itu hari Minggu, dan
 * drg. Andy — satu-satunya dokter ortho Kalideres — cuma praktik Rabu. Jadi
 * 117 usulan Kalideres mendarat di hari yang mustahil: Sales menelepon pasien,
 * menyebut tanggal, lalu tahu dokternya libur.
 *
 * Yang dijaga di sini adalah PAGAR KERAS — hal-hal yang membuat janji jadi
 * mustahil, bukan sekadar meleset:
 *   • klinik tutup Senin (terukur prod: reservasi 0, kunjungan ortho 0)
 *   • dokternya tidak praktik di hari itu
 *   • harinya sudah penuh
 * dan satu aturan produk: pasien hilang >6 bulan tidak diusulkan tanggal.
 *
 * ⚠ MINGGU BUKAN hari tutup — justru tersibuk ketiga di prod. Uji ini sengaja
 *   TIDAK melarang Minggu; dugaan "akhir pekan tutup" sempat hampir dipakai
 *   berdasarkan 13 baris jadwal di DB lokal, dan itu keliru.
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

    // ── Pagar keras ─────────────────────────────────────────────────────────

    /** ⭐ Pagar yang paling kasatmata: Senin klinik tutup. */
    public function test_usulan_tidak_pernah_jatuh_hari_senin(): void
    {
        // Praktik SETIAP hari — jadi kalau Senin muncul, yang melanggar
        // pasti pagar klinik-tutup, bukan jadwal dokter.
        $this->dokterPraktikHari([1, 2, 3, 4, 5, 6, 7]);

        // Sebar tanggal kunjungan supaya +30-nya mendarat di macam-macam hari.
        for ($i = 0; $i < 14; $i++) {
            $this->pasien(Carbon::today()->subDays(20 + $i)->toDateString(), $this->doctorRef);
        }
        $this->tarik();

        $senin = $this->usulan()->filter(fn ($u) => $u->sch_date->dayOfWeekIso === 1);

        $this->assertTrue($this->usulan()->isNotEmpty(), 'Harus ada usulan yang lahir.');
        $this->assertCount(0, $senin, 'Klinik tutup Senin — nol usulan boleh jatuh di sana.');
    }

    /** ⭐ Usulan tidak boleh jatuh di hari dokternya tidak praktik. */
    public function test_usulan_hanya_di_hari_praktik_dokter(): void
    {
        $this->dokterPraktikHari([3]);   // Rabu saja — kasus drg. Andy

        for ($i = 0; $i < 10; $i++) {
            $this->pasien(Carbon::today()->subDays(20 + $i)->toDateString(), $this->doctorRef);
        }
        $this->tarik();

        $usulan = $this->usulan();
        $this->assertTrue($usulan->isNotEmpty(), 'Dokter berjadwal Rabu harus tetap dapat usulan.');

        foreach ($usulan as $u) {
            $this->assertSame(
                3, $u->sch_date->dayOfWeekIso,
                "Usulan {$u->sch_date->toDateString()} jatuh di hari dokternya tidak praktik."
            );
        }
    }

    /** ⭐ Dokter tak terpetakan = hari praktiknya TIDAK DIKETAHUI → jangan menebak. */
    public function test_dokter_tak_terjadwal_tidak_diusulkan(): void
    {
        // Sengaja tanpa raw_doctor / raw_doctor_schedule.
        $this->pasien(Carbon::today()->subDays(20)->toDateString(), 'DOC-TIDAK-ADA');
        $this->pasien(Carbon::today()->subDays(21)->toDateString(), null);
        $this->tarik();

        $this->assertCount(
            0, $this->usulan(),
            'Hari praktik tak diketahui → tidak boleh ada tanggal yang ditebak.'
        );
    }

    /** ⭐ Inilah yang mencegah 651 usulan di satu tanggal. */
    public function test_tidak_melebihi_ambang_per_cabang_per_tanggal(): void
    {
        $this->dokterPraktikHari([1, 2, 3, 4, 5, 6, 7]);

        $ambang = 3;
        // 20 pasien yang tanggal kunjungannya SAMA — tanpa pagar, +30-nya
        // mendarat di satu tanggal yang sama persis. Ini peragaan ulang
        // kerusakannya dalam bentuk kecil.
        $sama = Carbon::today()->subDays(25)->toDateString();
        for ($i = 0; $i < 20; $i++) {
            $this->pasien($sama, $this->doctorRef);
        }
        $this->tarik(['--ambang' => $ambang]);

        $perTanggal = $this->usulan()->groupBy(fn ($u) => $u->sch_date->toDateString())
            ->map->count();

        $this->assertTrue($perTanggal->isNotEmpty(), 'Harus ada usulan yang lahir.');
        foreach ($perTanggal as $tanggal => $n) {
            $this->assertLessThanOrEqual(
                $ambang, $n,
                "Tanggal {$tanggal} memuat {$n} usulan, melebihi ambang {$ambang}."
            );
        }
        $this->assertGreaterThan(1, $perTanggal->count(), 'Usulannya harus MENYEBAR, bukan menumpuk.');
    }

    /** Pasien hilang >6 bulan: tempatnya di antrean, bukan di daftar kerja. */
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

    // ── Kendali negatif ─────────────────────────────────────────────────────

    /**
     * ⚠ Minggu TERSIBUK KETIGA di prod. Kalau uji ini merah, berarti ada yang
     *   menambahkan Minggu ke HARI_TUTUP — dan itu memotong hari kerja nyata.
     */
    public function test_minggu_bukan_hari_tutup(): void
    {
        $this->assertNotContains(7, OrtUsulan::HARI_TUTUP, 'Minggu bukan hari tutup klinik.');
        $this->assertSame([1], OrtUsulan::HARI_TUTUP, 'Yang tutup hanya Senin, terukur di prod.');
    }
}
