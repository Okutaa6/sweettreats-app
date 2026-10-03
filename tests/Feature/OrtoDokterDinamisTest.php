<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtRollup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — kolom "Dokter" yang DINAMIS.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG MELAHIRKAN BERKAS INI
 * ────────────────────────────────────────────────────────────────────────────
 * prompt_jadwal_kontrol.md (Phase 1, kolom 2): "Dokter TIDAK BOLEH diambil
 * dari field statis di master pasien. Dokter = dokter yang TERAKHIR menangani
 * pasien... Ambil yang paling baru tanggalnya. Kalau pasien dioper ke dokter
 * lain, kolom ini otomatis berubah. Bikin logika ini di satu tempat."
 *
 * Sebelum ini `ort_case.doctor_name` adalah snapshot dari kunjungan PERTAMA
 * yang menyebut item ortho (OrtSeedCases) dan tidak pernah disegarkan — jadi
 * layar menyebut dokter yang MEMASANG bracket. Ketika pasien dioper, Sales
 * menelepon dengan nama dokter yang salah.
 *
 * Datanya sudah ditarik tiap malam (OrtVisits membawa doc_reff per kunjungan),
 * tapi ort_control tidak punya tempat menyimpannya, jadi nilainya dibuang.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG DIJAGA DI SINI
 * ────────────────────────────────────────────────────────────────────────────
 *   • ⭐ yang paling BARU menang — kunjungan maupun reservasi
 *   • ⭐ kalau tidak ada calon, snapshot lama DIPERTAHANKAN. Mengosongkannya
 *        membuang satu-satunya dokter yang diketahui.
 *   • reservasi tanpa omnicare_doc_id dibuang, bukan dipaksa lewat nama — hari
 *     praktik yang salah akan disebut ke pasien lewat telepon
 *   • satu tempat saja: OrtRollup, yang dipanggil dari SEMUA jalur tulis
 */
class OrtoDokterDinamisTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-DOKDIN';

    private string $placeId;
    private string $branchId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ort_case', 'ort_control', 'raw_doctor', 'raw_cus_reservation'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("Tabel {$t} belum ada.");
            }
        }
        if (! Schema::hasColumn('ort_control', 'doctor_ref_id')) {
            $this->markTestSkipped('Jalankan database/sql/2026_10_06_ort_dokter_per_kontrol.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }
        $this->placeId  = (string) array_key_first($peta);
        $this->branchId = (string) $peta[$this->placeId]['branch_id'];
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    private function kasus(?string $kodeAwal, ?string $namaAwal): OrtCase
    {
        $pid = self::TANDA . '-' . Str::random(6);

        return OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji DokDin ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => Carbon::today()->subDays(400)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF,
            'doctor_ref_id' => $kodeAwal, 'doctor_name' => $namaAwal,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);
    }

    /** Kontrol yang sudah DIKUNJUNGI, dengan dokternya. */
    private function kunjungan(OrtCase $k, int $seq, string $tanggal, ?string $kode, ?string $nama): OrtControl
    {
        return OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $k->id, 'seq' => $seq,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date' => $tanggal, 'sch_source' => OrtControl::SCH_REKAM_MEDIS,
            'visit_date' => $tanggal, 'visit_source' => 'rekam_medis',
            'doctor_ref_id' => $kode, 'doctor_name' => $nama,
            'outcome' => OrtControl::OUT_HADIR, 'closed_at' => now(), 'closed_by' => 'sistem',
            'created_by' => 'uji',
        ]);
    }

    /** Dokter di raw_doctor, dengan atau tanpa kode Omnicare. */
    private function dokter(string $nama, ?string $kode): string
    {
        $id = 'uji-dokdin-' . Str::random(8);
        DB::table('raw_doctor')->insert([
            'id' => $id, 'name' => $nama, 'omnicare_doc_id' => $kode, 'is_active' => 1,
        ]);

        return $id;
    }

    /** Kontrol TERBUKA yang sudah punya reservasi ke dokter tertentu. */
    private function reservasi(OrtCase $k, int $seq, string $tanggal, string $doctorId): void
    {
        $rsvId = 'uji-dokdin-rsv-' . Str::random(10);
        DB::table('raw_cus_reservation')->insert([
            'id' => $rsvId, 'raw_branch_id' => $this->branchId, 'raw_doctor_id' => $doctorId,
            'date' => $tanggal, 'start_hour' => '10:00:00', 'end_hour' => '10:30:00',
            'status' => 'confirmed', 'promo_klaim' => 0,
        ]);

        OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $k->id, 'seq' => $seq,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date' => $tanggal, 'sch_source' => OrtControl::SCH_MANUAL,
            'reservation_id' => $rsvId, 'rsv_date' => $tanggal, 'rsv_status' => 'confirmed',
            'created_by' => 'uji',
        ]);
    }

    private function segarkan(OrtCase $k): OrtCase
    {
        app(OrtRollup::class)->refresh($k->id);

        return $k->refresh();
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⭐ Yang paling baru menang
    // ════════════════════════════════════════════════════════════════════════

    public function test_kunjungan_terakhir_menggantikan_snapshot_pemasang(): void
    {
        $k = $this->kasus('D-PASANG', 'drg. Pemasang');

        $this->kunjungan($k, 1, Carbon::today()->subDays(90)->toDateString(), 'D-PASANG', 'drg. Pemasang');
        $this->kunjungan($k, 2, Carbon::today()->subDays(30)->toDateString(), 'D-BARU', 'drg. Penerus');

        $k = $this->segarkan($k);

        $this->assertSame('D-BARU', $k->doctor_ref_id, 'Kolom Dokter harus mengikuti yang terakhir menangani.');
        $this->assertSame('drg. Penerus', $k->doctor_name);
    }

    public function test_urutan_kunjungan_tidak_mengubah_hasil(): void
    {
        // Dimasukkan dengan seq yang TIDAK sejalan tanggalnya — yang menentukan
        // harus TANGGAL, bukan urutan baris.
        $k = $this->kasus('D-LAMA', 'drg. Lama');

        $this->kunjungan($k, 1, Carbon::today()->subDays(10)->toDateString(), 'D-TERBARU', 'drg. Terbaru');
        $this->kunjungan($k, 2, Carbon::today()->subDays(80)->toDateString(), 'D-TUA', 'drg. Tua');

        $k = $this->segarkan($k);

        $this->assertSame('D-TERBARU', $k->doctor_ref_id);
    }

    /** ⭐ Reservasi yang lebih baru dari kunjungan terakhir juga menang. */
    public function test_reservasi_lebih_baru_menang_atas_kunjungan(): void
    {
        $k   = $this->kasus('D-AWAL', 'drg. Awal');
        $dok = $this->dokter('drg. Reservasi', 'D-RSV');

        $this->kunjungan($k, 1, Carbon::today()->subDays(40)->toDateString(), 'D-AWAL', 'drg. Awal');
        $this->reservasi($k, 2, Carbon::today()->addDays(5)->toDateString(), $dok);

        $k = $this->segarkan($k);

        $this->assertSame('D-RSV', $k->doctor_ref_id);
        $this->assertSame('drg. Reservasi', $k->doctor_name);
    }

    /** Dan reservasi yang LEBIH TUA tidak boleh mengalahkan kunjungan. */
    public function test_reservasi_lebih_tua_tidak_menang(): void
    {
        $k   = $this->kasus(null, null);
        $dok = $this->dokter('drg. Reservasi Tua', 'D-RSVTUA');

        $this->reservasi($k, 1, Carbon::today()->subDays(60)->toDateString(), $dok);
        $this->kunjungan($k, 2, Carbon::today()->subDays(10)->toDateString(), 'D-KUNJUNG', 'drg. Kunjungan');

        $k = $this->segarkan($k);

        $this->assertSame('D-KUNJUNG', $k->doctor_ref_id);
    }

    /**
     * Seri tanggal: KUNJUNGAN menang atas reservasi. Kunjungan adalah
     * KENYATAAN, reservasi baru RENCANA.
     */
    public function test_seri_tanggal_kunjungan_menang(): void
    {
        $tgl = Carbon::today()->subDays(3)->toDateString();
        $k   = $this->kasus(null, null);
        $dok = $this->dokter('drg. Rencana', 'D-RENCANA');

        $this->kunjungan($k, 1, $tgl, 'D-NYATA', 'drg. Nyata');
        $this->reservasi($k, 2, $tgl, $dok);

        $k = $this->segarkan($k);

        $this->assertSame('D-NYATA', $k->doctor_ref_id, 'Kunjungan itu kenyataan; reservasi baru rencana.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⭐ Tanpa calon, snapshot dipertahankan
    // ════════════════════════════════════════════════════════════════════════

    public function test_tanpa_calon_snapshot_dipertahankan(): void
    {
        $k = $this->kasus('D-SATUSATUNYA', 'drg. Satu-satunya');

        // Kontrol terbuka, belum dikunjungi, tanpa reservasi.
        OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $k->id, 'seq' => 1,
            'place_id' => $this->placeId, 'is_proposal' => 1,
            'sch_date' => Carbon::today()->toDateString(),
            'sch_source' => OrtControl::SCH_SISTEM, 'created_by' => 'uji',
        ]);

        $k = $this->segarkan($k);

        $this->assertSame('D-SATUSATUNYA', $k->doctor_ref_id, 'Jangan mengosongkan satu-satunya dokter yang diketahui.');
        $this->assertSame('drg. Satu-satunya', $k->doctor_name);
    }

    public function test_kasus_tanpa_dokter_sama_sekali_tetap_null(): void
    {
        $k = $this->kasus(null, null);

        OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $k->id, 'seq' => 1,
            'place_id' => $this->placeId, 'is_proposal' => 1,
            'sch_date' => Carbon::today()->toDateString(),
            'sch_source' => OrtControl::SCH_SISTEM, 'created_by' => 'uji',
        ]);

        $k = $this->segarkan($k);

        $this->assertNull($k->doctor_ref_id, 'Tanpa data, null lebih jujur daripada menebak.');
        $this->assertNull($k->doctor_name);
    }

    /** Kunjungan tanpa kode dokter tidak boleh jadi calon — kodenya yang dipakai mencari jadwal. */
    public function test_kunjungan_tanpa_kode_dokter_bukan_calon(): void
    {
        $k = $this->kasus('D-LAMA', 'drg. Lama');

        $this->kunjungan($k, 1, Carbon::today()->subDays(5)->toDateString(), null, 'drg. Tanpa Kode');

        $k = $this->segarkan($k);

        $this->assertSame('D-LAMA', $k->doctor_ref_id, 'Nama tanpa kode tidak bisa dipakai mencari hari praktik.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // Reservasi: kode Omnicare, bukan nama
    // ════════════════════════════════════════════════════════════════════════

    /**
     * ⭐ Dokter reservasi yang BELUM punya omnicare_doc_id dibuang sebagai
     * calon — tidak dipaksa lewat nama.
     *
     * Brief Lampiran A melarang tegas mencocokkan dokter lewat nama ("sudah
     * terbukti nyasar ke rekam dokter lain"), dan di sini risikonya konkret:
     * kode dokter yang salah berarti hari praktik yang salah, dan hari praktik
     * yang salah akan disebut ke pasien lewat telepon.
     */
    public function test_dokter_reservasi_tanpa_kode_omnicare_diabaikan(): void
    {
        $k   = $this->kasus('D-AWAL', 'drg. Awal');
        $dok = $this->dokter('drg. Belum Terpetakan', null);

        $this->kunjungan($k, 1, Carbon::today()->subDays(40)->toDateString(), 'D-AWAL', 'drg. Awal');
        $this->reservasi($k, 2, Carbon::today()->addDays(5)->toDateString(), $dok);

        $k = $this->segarkan($k);

        $this->assertSame('D-AWAL', $k->doctor_ref_id, 'Tanpa kode Omnicare, dokter reservasi bukan calon.');
        $this->assertSame('drg. Awal', $k->doctor_name);
    }

    // ════════════════════════════════════════════════════════════════════════
    // Satu tempat saja
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Kolom dokter ditulis OrtRollup, dan rollup dipanggil dari semua jalur
     * tulis. Uji ini menjaga bahwa keduanya ditulis BERSAMAAN — kode tanpa nama
     * membuat layar kosong, nama tanpa kode membuat hari praktiknya tidak bisa
     * dicari.
     */
    public function test_kode_dan_nama_selalu_sepasang(): void
    {
        $k = $this->kasus(null, null);
        $this->kunjungan($k, 1, Carbon::today()->subDays(5)->toDateString(), 'D-PASANGAN', 'drg. Pasangan');

        $k = $this->segarkan($k);

        $this->assertNotNull($k->doctor_ref_id);
        $this->assertNotNull($k->doctor_name);
    }

    /** Kolomnya ada di ort_control dan NULL-able — kunjungan lama tidak punya. */
    public function test_kolom_dokter_per_kontrol_nullable(): void
    {
        $this->assertTrue(Schema::hasColumn('ort_control', 'doctor_ref_id'));
        $this->assertTrue(Schema::hasColumn('ort_control', 'doctor_name'));

        $k = $this->kasus(null, null);
        $c = $this->kunjungan($k, 1, Carbon::today()->subDay()->toDateString(), null, null);

        $this->assertNull($c->refresh()->doctor_ref_id, 'Kunjungan tanpa dokter tercatat tetap sah.');
    }
}
