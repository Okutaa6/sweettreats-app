<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — menautkan reservasi yang SUDAH ADA ke baris kontrol.
 *
 * TEMUAN YANG MELAHIRKAN BERKAS INI (audit HUB, 1 Okt 2026). Diukur di prod,
 * 91% kunjungan ortho punya reservasi — kontrol ortho BUKAN walk-in. Tapi
 * `ort_control.reservation_id` terisi 0 dari 35.317, karena satu-satunya yang
 * pernah mengisinya adalah Observer, dan ia cuma bekerja untuk reservasi yang
 * dibuat LEWAT modul ortho, ke depan. Kolom RESERVASI menampilkan X untuk
 * semua orang — membuang data milik kita sendiri.
 *
 * Yang dijaga berkas ini, dan yang pertama paling mudah salah:
 *
 *   ⚠ Dicocokkan ke `raw_cus_reservation.date` (tanggal JANJI), BUKAN
 *     `created_at`. Pasien memesan tanggal 13 untuk janji tanggal 20, lalu
 *     datang tanggal 20. Mencocokkan ke tanggal pesan meleset sistematis
 *     sebanyak jeda pemesanan — dan melesetnya rapi, jadi tidak kelihatan
 *     seperti bug. Uji "jebakan created_at" di bawah yang menahannya.
 *
 *   • toleransi ±3 hari, terbukti pas di prod
 *   • pasien tanpa tautan raw_customer DIBIARKAN kosong, tidak ditebak
 *   • satu reservasi hanya menempel pada SATU kontrol
 *   • menulis butuh --commit
 *
 * Fixture dibuat sendiri: DB pengembangan punya 138 reservasi, tapi pasien
 * ortho-nya cuma memiliki 1 di antaranya — data nyata di sini tidak bisa
 * membuktikan apa pun. Semuanya ikut rollback DatabaseTransactions.
 */
class OrtoTautReservasiTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-TAUT';

    private string $placeId;
    private string $branchId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ort_control', 'ort_case', 'raw_cus_reservation'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("Tabel {$t} belum ada.");
            }
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }

        $this->placeId  = (string) array_key_first($peta);
        $this->branchId = (string) $peta[$this->placeId]['branch_id'];
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    /** Satu kontrol yang sudah dikunjungi, milik pasien ber-raw_customer. */
    private function kunjungan(string $visitDate, ?string $customerId): OrtControl
    {
        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => self::TANDA . '-' . Str::random(6),
            'patient_name' => 'Uji Taut', 'raw_customer_id' => $customerId,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => Carbon::parse($visitDate)->subDays(200)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);

        return OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 1,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date' => $visitDate, 'sch_source' => OrtControl::SCH_REKAM_MEDIS,
            'visit_date' => $visitDate, 'visit_source' => 'rekam_medis',
            'visit_confidence' => 'pasti', 'outcome' => OrtControl::OUT_HADIR,
            'closed_at' => now(), 'closed_by' => 'uji', 'created_by' => 'uji',
        ]);
    }

    private function pelanggan(): string
    {
        $id = 'uji-cust-' . Str::random(10);
        DB::table('raw_customer')->insert([
            'id' => $id, 'name' => 'Uji Pelanggan ' . $id,
        ]);

        return $id;
    }

    /** @param string|null $createdAt sengaja bisa dibuat jauh dari tanggal janji */
    private function reservasi(string $customerId, string $date, string $status = 'confirmed', ?string $createdAt = null): string
    {
        $id = 'uji-rsv-' . Str::random(10);
        DB::table('raw_cus_reservation')->insert([
            'id' => $id, 'raw_customer_id' => $customerId, 'raw_branch_id' => $this->branchId,
            'date' => $date, 'status' => $status, 'promo_klaim' => 0,
            'created_at' => $createdAt ?: $date . ' 09:00:00',
        ]);

        return $id;
    }

    private function jalankan(array $opsi = []): void
    {
        Artisan::call('ort:tautkan-reservasi', array_merge(['--cabang' => $this->placeId], $opsi));
    }

    // ── Penjodohan dasar ────────────────────────────────────────────────────

    public function test_tanggal_sama_persis_tertaut(): void
    {
        $cust = $this->pelanggan();
        $tgl  = Carbon::today()->subDays(20)->toDateString();
        $ctl  = $this->kunjungan($tgl, $cust);
        $rsv  = $this->reservasi($cust, $tgl);

        $this->jalankan(['--commit' => true]);

        $ctl->refresh();
        $this->assertSame($rsv, $ctl->reservation_id);
        $this->assertSame($tgl, $ctl->rsv_date->toDateString());
        $this->assertSame('confirmed', $ctl->rsv_status);
    }

    public function test_dalam_toleransi_tertaut_di_luar_toleransi_tidak(): void
    {
        $tgl = Carbon::today()->subDays(20);

        $cDekat = $this->pelanggan();
        $kDekat = $this->kunjungan($tgl->toDateString(), $cDekat);
        $rDekat = $this->reservasi($cDekat, $tgl->copy()->subDays(2)->toDateString());

        $cJauh = $this->pelanggan();
        $kJauh = $this->kunjungan($tgl->toDateString(), $cJauh);
        $this->reservasi($cJauh, $tgl->copy()->subDays(9)->toDateString());

        $this->jalankan(['--commit' => true]);

        $this->assertSame($rDekat, $kDekat->refresh()->reservation_id, 'Selisih 2 hari harus tertaut.');
        $this->assertNull($kJauh->refresh()->reservation_id, 'Selisih 9 hari jauh di luar toleransi ±3.');
    }

    /**
     * ⭐ JEBAKAN YANG PALING MAHAL.
     *
     * Reservasi dipesan jauh-jauh hari untuk janji yang dekat. Kalau
     * penjodohnya memakai `created_at`, pasangan ini TERLEWAT — dan melesetnya
     * rapi sebanyak jeda pemesanan, jadi tidak terlihat seperti bug.
     */
    public function test_dicocokkan_ke_tanggal_janji_bukan_tanggal_pesan(): void
    {
        $cust = $this->pelanggan();
        $tgl  = Carbon::today()->subDays(20);

        $ctl = $this->kunjungan($tgl->toDateString(), $cust);
        // Dipesan 30 hari sebelum janjinya — persis pola yang dikonfirmasi
        // Ikhsan (pesan tgl 13, janji tgl 20, datang tgl 20).
        $rsv = $this->reservasi(
            $cust, $tgl->toDateString(), 'confirmed',
            $tgl->copy()->subDays(30)->toDateString() . ' 10:00:00'
        );

        $this->jalankan(['--commit' => true]);

        $this->assertSame(
            $rsv, $ctl->refresh()->reservation_id,
            'Reservasi yang dipesan jauh hari tetap harus tertaut — yang dicocokkan tanggal JANJI.'
        );
    }

    public function test_calon_terdekat_yang_dipilih(): void
    {
        $cust = $this->pelanggan();
        $tgl  = Carbon::today()->subDays(20);

        $ctl    = $this->kunjungan($tgl->toDateString(), $cust);
        $jauh   = $this->reservasi($cust, $tgl->copy()->subDays(3)->toDateString());
        $dekat  = $this->reservasi($cust, $tgl->copy()->subDay()->toDateString());

        $this->jalankan(['--commit' => true]);

        $this->assertSame($dekat, $ctl->refresh()->reservation_id, 'Yang selisihnya terkecil yang menang.');
        $this->assertNotSame($jauh, $ctl->reservation_id);
    }

    // ── Yang tidak boleh ditebak ────────────────────────────────────────────

    /** 15% pasien belum tertaut raw_customer — dibiarkan kosong, bukan ditebak. */
    public function test_pasien_tanpa_customer_dibiarkan_kosong(): void
    {
        $tgl = Carbon::today()->subDays(20)->toDateString();
        $ctl = $this->kunjungan($tgl, null);

        // Ada reservasi di tanggal itu, tapi milik orang LAIN.
        $this->reservasi($this->pelanggan(), $tgl);

        $this->jalankan(['--commit' => true]);

        $this->assertNull(
            $ctl->refresh()->reservation_id,
            'Tanpa tautan customer, reservasi orang lain tidak boleh menempel.'
        );
    }

    /** ⭐ Satu reservasi hanya boleh menempel pada SATU kontrol. */
    public function test_satu_reservasi_tidak_dipakai_dua_kali(): void
    {
        $cust = $this->pelanggan();
        $tgl  = Carbon::today()->subDays(20);

        // Dua kunjungan berdekatan, satu reservasi saja.
        $a = $this->kunjungan($tgl->toDateString(), $cust);
        $b = $this->kunjungan($tgl->copy()->addDay()->toDateString(), $cust);
        $rsv = $this->reservasi($cust, $tgl->toDateString());

        $this->jalankan(['--commit' => true]);

        $terpakai = collect([$a->refresh()->reservation_id, $b->refresh()->reservation_id])
            ->filter()->values();

        $this->assertCount(1, $terpakai, 'Reservasi yang sama tidak boleh menempel di dua kontrol.');
        $this->assertSame($rsv, $terpakai[0]);
    }

    // ── Pengaman tulis ──────────────────────────────────────────────────────

    public function test_tanpa_commit_tidak_menulis(): void
    {
        $cust = $this->pelanggan();
        $tgl  = Carbon::today()->subDays(20)->toDateString();
        $ctl  = $this->kunjungan($tgl, $cust);
        $this->reservasi($cust, $tgl);

        $this->jalankan();   // sengaja TANPA --commit

        $this->assertNull(
            $ctl->refresh()->reservation_id,
            'Menulis harus butuh --commit; pratinjau tidak boleh menyentuh sebaris pun.'
        );
    }

    /** Yang sudah tertaut tidak disentuh ulang — perintah ini aman diulang. */
    public function test_tidak_menimpa_tautan_yang_sudah_ada(): void
    {
        $cust = $this->pelanggan();
        $tgl  = Carbon::today()->subDays(20)->toDateString();
        $ctl  = $this->kunjungan($tgl, $cust);
        $lama = $this->reservasi($cust, $tgl, 'completed');

        $ctl->update(['reservation_id' => $lama, 'rsv_date' => $tgl, 'rsv_status' => 'completed']);
        $this->reservasi($cust, $tgl, 'confirmed');   // calon lain di tanggal sama

        $this->jalankan(['--commit' => true]);

        $this->assertSame($lama, $ctl->refresh()->reservation_id, 'Tautan yang sudah ada tidak boleh berubah.');
    }
}
