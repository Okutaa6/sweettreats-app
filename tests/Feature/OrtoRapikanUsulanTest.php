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
 * Tracking Ortho — membersihkan USULAN yang sudah telanjur ada.
 *
 * TEMUAN YANG MELAHIRKAN BERKAS INI (1 Okt 2026, dari layar). Tahap A memasang
 * pagar keras di mesin usulan, dan pagar itu bekerja — tapi hanya SAAT USULAN
 * DIBUAT. Baris yang sudah duduk di tabel tidak pernah diperiksa lagi oleh
 * siapa pun.
 *
 * Ketahuannya dengan cara paling sederhana: membuka halaman dan melihat satu
 * usulan jatuh hari MINGGU untuk dokter yang bahkan tidak ada di raw_doctor.
 * Usulan itu lahir pukul 11:49; pagarnya baru ada pukul 15:08. Mesin yang
 * benar tidak membatalkan apa yang sudah ditulis mesin lama — dan 651 usulan
 * yang lahir di satu tanggal yang sama sebelum perbaikan juga tidak.
 *
 * Yang dijaga berkas ini, dan sisi kedua yang jauh lebih penting:
 *
 *   • usulan yang melanggar pagar DIGESER atau DIHAPUS, dan
 *   • ⭐ yang BUKAN usulan tidak pernah disentuh. Jadwal yang sudah
 *     dikonfirmasi Sales, dan baris yang sudah punya kunjungan, adalah FAKTA.
 *     Perintah pembersih yang ikut merapikan fakta bukan pembersih melainkan
 *     penghapus riwayat — dan kerusakannya tidak bisa diurungkan.
 *
 * Plus: pasien yang usulannya dihapus tidak boleh ikut hilang dari layar.
 */
class OrtoRapikanUsulanTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-RAPIKAN';

    private string $placeId;
    private string $branchId;
    private string $doctorRef = 'UJI-RAPIKAN-DOC';

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_control')) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.');
        }
        if (! Schema::hasColumn('ort_control', 'sch_basis')) {
            $this->markTestSkipped('Jalankan database/sql/2026_10_01_ort_tahap_c.sql dulu.');
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
        $docId = 'uji-rapikan-doc-' . Str::random(6);
        DB::table('raw_doctor')->insert([
            'id' => $docId, 'name' => 'drg. Uji Rapikan',
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
     * Pasien dengan satu kunjungan lampau yang sudah tertutup, plus satu baris
     * terbuka di $schDate yang sifatnya ditentukan $kontrolUbah.
     */
    private function pasien(
        string $schDate,
        ?string $doctorRef,
        array $kontrolUbah = [],
        int $kunjunganLalu = 20
    ): OrtControl {
        $pid     = self::TANDA . '-' . Str::random(6);
        $hariIni = Carbon::today();

        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji Rapikan ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => $hariIni->copy()->subDays(300)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF, 'doctor_ref_id' => $doctorRef,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);

        // Kunjungan terakhir: titik berangkat perhitungan ulang.
        $tglKunjungan = $hariIni->copy()->subDays($kunjunganLalu)->toDateString();
        OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 1,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date' => $tglKunjungan, 'sch_source' => OrtControl::SCH_REKAM_MEDIS,
            'visit_date' => $tglKunjungan, 'visit_source' => 'rekam_medis',
            'visit_confidence' => 'pasti', 'outcome' => OrtControl::OUT_HADIR,
            'closed_at' => now(), 'closed_by' => 'uji', 'created_by' => 'uji',
        ]);

        return OrtControl::create(array_merge([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 2,
            'place_id' => $this->placeId, 'is_proposal' => 1,
            'sch_date' => $schDate, 'sch_source' => OrtControl::SCH_SISTEM,
            'created_by' => 'sistem',
        ], $kontrolUbah));
    }

    /** Hari Senin berikutnya yang jaraknya masuk akal dari hari ini. */
    private function seninBerikutnya(): string
    {
        $t = Carbon::today()->addDays(10);
        while ($t->dayOfWeekIso !== 1) {
            $t->addDay();
        }

        return $t->toDateString();
    }

    private function jalankan(bool $commit = true): void
    {
        Artisan::call('ort:rapikan-usulan', array_filter([
            '--cabang' => $this->placeId,
            '--commit' => $commit ?: null,
        ]));
    }

    // ── Yang memang harus dirapikan ─────────────────────────────────────────

    /**
     * ⭐ Tanggal yang salah dihitung ulang dari kunjungan terakhir.
     *
     * Inilah pekerjaan utama perintah ini sesudah lantai MIN_MAJU_HARI dibuang:
     * baris lama memegang tanggal yang pernah didorong ke "hari ini + 3", dan
     * pasien yang sebenarnya telat berbulan-bulan tampil punya tanggal di masa
     * depan. Di sini barisnya sengaja diberi tanggal ngawur jauh di depan.
     */
    public function test_tanggal_ngawur_dihitung_ulang_dari_kunjungan_terakhir(): void
    {
        $this->dokterPraktikTiapHari();

        // Kunjungan terakhir 20 hari lalu (bawaan fixture), tapi barisnya
        // memegang tanggal 90 hari ke depan — persis pola baris warisan.
        $ctl = $this->pasien(Carbon::today()->addDays(90)->toDateString(), $this->doctorRef);

        $this->jalankan();

        $baru = $ctl->fresh();
        $this->assertNotNull($baru, 'Pasiennya masih aktif — dihitung ulang, bukan dihapus.');

        $harap = OrtControl::tanggalUsulan(
            Carbon::today()->subDays(20),
            $baru->case->median_gap_days
                ? max(OrtCase::IRAMA_MIN_HARI, min(OrtCase::IRAMA_MAX_HARI, $baru->case->median_gap_days))
                : null
        )->toDateString();

        $this->assertSame(
            $harap, $baru->sch_date->toDateString(),
            'Jatuh tempo = kunjungan terakhir + irama, bukan tanggal warisan.'
        );
    }

    /**
     * ⭐ Pasien yang hilang lebih dari 6 bulan: barisnya DIHAPUS.
     *
     * Satu-satunya sebab penghapusan yang tersisa. Dulu dokter yang tidak
     * terpetakan juga menghapus baris; pagar itu sudah dibuang bersama pagar
     * jadwal lainnya — ketersediaan dokter urusan modul Reservasi, bukan
     * urusan pengingat.
     */
    public function test_pasien_hilang_lebih_enam_bulan_dihapus(): void
    {
        $this->dokterPraktikTiapHari();
        $ctl = $this->pasien(
            Carbon::today()->addDays(10)->toDateString(),
            $this->doctorRef,
            [],
            kunjunganLalu: OrtCase::HILANG_HARI + 30
        );

        $this->jalankan();

        $this->assertNull($ctl->fresh(), 'Pasien yang hilang berbulan-bulan tidak diberi jatuh tempo.');
    }

    /** Pasiennya tidak ikut hilang: ia pindah ke antrean "Belum punya jadwal". */
    public function test_pasien_yang_barisnya_dihapus_tetap_terlihat(): void
    {
        $this->dokterPraktikTiapHari();
        $ctl   = $this->pasien(
            Carbon::today()->addDays(10)->toDateString(),
            $this->doctorRef,
            [],
            kunjunganLalu: OrtCase::HILANG_HARI + 30
        );
        $kasus = $ctl->case;

        $this->jalankan();

        $kasus->refresh();
        $this->assertNull($kasus->open_control_id, 'Ringkasan kasus harus ikut disegarkan.');
        $this->assertNull($kasus->next_sch_date);
        $this->assertSame(
            OrtCase::STATUS_AKTIF, $kasus->status,
            'Kasusnya tetap aktif — ia masuk antrean penjadwalan, bukan diarsipkan.'
        );
        $this->assertFalse(
            OrtControl::where('ort_case_id', $kasus->id)->whereNull('closed_at')->exists(),
            'Tanpa jadwal terbuka, pasien ini muncul di kartu "Belum punya jadwal".'
        );
    }

    // ── Yang TIDAK boleh disentuh ───────────────────────────────────────────

    /**
     * ⭐ Jadwal yang sudah DIKONFIRMASI Sales adalah kesepakatan dengan pasien,
     *   bukan tebakan sistem. Sengaja ditaruh di hari Senin: kalau perintah ini
     *   menyentuh apa pun yang bukan usulan, di sinilah ketahuannya.
     */
    public function test_jadwal_terkonfirmasi_tidak_pernah_disentuh(): void
    {
        $this->dokterPraktikTiapHari();
        $senin = $this->seninBerikutnya();
        $ctl   = $this->pasien($senin, $this->doctorRef, ['is_proposal' => 0, 'sch_source' => OrtControl::SCH_MANUAL]);

        $this->jalankan();

        $this->assertNotNull($ctl->fresh(), 'Jadwal terkonfirmasi tidak boleh dihapus.');
        $this->assertSame($senin, $ctl->fresh()->sch_date->toDateString(), 'Dan tanggalnya tidak boleh digeser.');
    }

    /** Baris yang sudah punya kunjungan itu FAKTA, sekalipun masih bertanda usulan. */
    public function test_baris_yang_sudah_dikunjungi_tidak_disentuh(): void
    {
        $this->dokterPraktikTiapHari();
        $senin = $this->seninBerikutnya();
        $ctl   = $this->pasien($senin, $this->doctorRef, [
            'visit_date' => $senin, 'visit_source' => 'rekam_medis', 'visit_confidence' => 'pasti',
        ]);

        $this->jalankan();

        $this->assertNotNull($ctl->fresh());
        $this->assertSame($senin, $ctl->fresh()->sch_date->toDateString());
    }

    // ── Pengaman tulis & pengulangan ────────────────────────────────────────

    public function test_tanpa_commit_tidak_menulis(): void
    {
        $ctl = $this->pasien(Carbon::today()->addDays(10)->toDateString(), 'NIHIL-DOC');

        $this->jalankan(commit: false);

        $this->assertNotNull($ctl->fresh(), 'Pratinjau tidak boleh menghapus sebaris pun.');
    }

    /** ⭐ Aman diulang: jalanan kedua tidak menemukan apa pun untuk dikerjakan. */
    public function test_aman_dijalankan_berulang(): void
    {
        $this->dokterPraktikTiapHari();
        $ctl = $this->pasien($this->seninBerikutnya(), $this->doctorRef);

        $this->jalankan();
        $sesudahSekali = $ctl->fresh()->sch_date->toDateString();

        $this->jalankan();

        $this->assertSame(
            $sesudahSekali, $ctl->fresh()->sch_date->toDateString(),
            'Yang sudah sah tidak boleh digeser-geser tiap perintah dijalankan.'
        );
    }

    /** Usulan yang digeser ikut mencatat dasarnya, seperti usulan yang baru lahir. */
    public function test_usulan_yang_digeser_mencatat_dasarnya(): void
    {
        $this->dokterPraktikTiapHari();
        $ctl = $this->pasien($this->seninBerikutnya(), $this->doctorRef);

        $this->jalankan();

        $baru = $ctl->fresh();
        $this->assertContains(
            $baru->sch_basis,
            [OrtCase::BASIS_RAPAT, OrtCase::BASIS_LONGGAR, OrtCase::BASIS_DEFAULT],
            'Dasar jatuh tempo harus terisi, bukan dibiarkan NULL.'
        );
        $this->assertSame('ort:rapikan-usulan', $baru->updated_by, 'Jejak siapa yang mengubah harus tertinggal.');
    }

}
