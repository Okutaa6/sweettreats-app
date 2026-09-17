<?php

namespace Tests\Feature;

use App\Http\Controllers\Crud\CustomerController;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * HASIL AKHIR yang dilihat orang: pasien yang berobat di DUA cabang harus
 * melihat riwayat KEDUA cabang di halamannya.
 *
 * ⭐ Kenapa uji ini ada — dan kenapa `RiwayatRekamMedisTest` TIDAK cukup.
 *    Kelas itu menguji `buildRiwayat()`, yang cuma memformat koleksi yang SUDAH
 *    ditarik controller: nol kueri. Ia membuktikan teks rekam medis tidak
 *    mundur, dan itu berharga — tapi ia **tidak bisa** menangkap regresi pada
 *    `value()` → `pluck()` dan `where` → `whereIn`, satu-satunya baris yang
 *    gerbong multi-pid ubah di `CustomerController`.
 *
 *    Kalau baris itu salah, tautan cabang keduanya ADA di database tapi halaman
 *    pasien tetap menampilkan satu cabang — seluruh gerbong ini terlihat seperti
 *    tidak berbuat apa-apa, dan tak ada uji yang berteriak. Menjalankan uji yang
 *    hijau untuk hal LAIN adalah rasa aman yang paling mahal.
 *
 * ⚠️ TIDAK lewat route, disengaja. `show()` memanggil `Schema::hasTable`, dan
 *    itu terbukti MEMATIKAN mysqld LOKAL mesin ini. Di SQLite in-memory
 *    `Schema::hasTable` membaca `sqlite_master` — aman.
 *
 *   vendor/bin/phpunit tests/Feature/RiwayatMultiPidTest.php
 */
class RiwayatMultiPidTest extends TestCase
{
    /** pid Bintaro & Sunter milik SATU orang — bentuk nyata di prod. */
    private const PID_BINTARO = 'B-2007-2758';
    private const PID_SUNTER  = 'S-2105-0300';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'uji_sqlite',
            'database.connections.uji_sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('uji_sqlite');

        DB::statement('CREATE TABLE customer_omnicare_link (
            id VARCHAR(36) PRIMARY KEY, raw_customer_id VARCHAR(255) NOT NULL,
            omnicare_pid VARCHAR(50) NOT NULL UNIQUE)');

        DB::statement('CREATE TABLE omnicare_medical_record (
            id INTEGER PRIMARY KEY AUTOINCREMENT, p_id VARCHAR(50),
            date DATE, time_in TIME, branch_id VARCHAR(50), procedures TEXT)');

        DB::statement('CREATE TABLE omnicare_sales_detail (
            id INTEGER PRIMARY KEY AUTOINCREMENT, p_id VARCHAR(50),
            trx_date DATE, net_sell_price DECIMAL(15,2))');

        // Satu pasien, DUA pid — satu per cabang.
        DB::table('customer_omnicare_link')->insert([
            ['id' => 'L-1', 'raw_customer_id' => 'C-ISMU', 'omnicare_pid' => self::PID_BINTARO],
            ['id' => 'L-2', 'raw_customer_id' => 'C-ISMU', 'omnicare_pid' => self::PID_SUNTER],
        ]);

        DB::table('omnicare_medical_record')->insert([
            ['p_id' => self::PID_BINTARO, 'date' => '2026-03-01', 'time_in' => '10:00:00',
             'branch_id' => 'BIN', 'procedures' => 'Scaling Bintaro'],
            ['p_id' => self::PID_SUNTER,  'date' => '2026-08-20', 'time_in' => '14:00:00',
             'branch_id' => 'SUN', 'procedures' => 'Tambal Sunter'],
        ]);

        DB::table('omnicare_sales_detail')->insert([
            ['p_id' => self::PID_BINTARO, 'trx_date' => '2026-03-01', 'net_sell_price' => 300000],
            ['p_id' => self::PID_SUNTER,  'trx_date' => '2026-08-20', 'net_sell_price' => 700000],
        ]);
    }

    /** Panggil metode privat controller tanpa lewat HTTP. */
    private function panggil(string $metode, ...$args)
    {
        $m = new ReflectionMethod(CustomerController::class, $metode);
        $m->setAccessible(true);
        return $m->invoke(new CustomerController(), ...$args);
    }

    // ────────────────────────────────────────────────────────────────────────

    public function test_semua_pid_pasien_terkumpul(): void
    {
        $pids = $this->panggil('pidOmnicare', 'C-ISMU');
        sort($pids);

        $this->assertSame([self::PID_BINTARO, self::PID_SUNTER], $pids,
            'value() hanya memungut satu pid — harus pluck() SEMUA');
    }

    /** ⭐ INTI: riwayat kedua cabang muncul, bukan cuma satu. */
    public function test_riwayat_menampilkan_kedua_cabang(): void
    {
        $pids   = $this->panggil('pidOmnicare', 'C-ISMU');
        $rekam  = $this->panggil('rekamMedisOmnicare', $pids);
        $cabang = $rekam->pluck('branch_id')->sort()->values()->all();

        $this->assertCount(2, $rekam, 'kunjungan cabang kedua hilang dari halaman pasien');
        $this->assertSame(['BIN', 'SUN'], $cabang);
    }

    /** Urutan tetap terbaru dulu walau datang dari pid berbeda. */
    public function test_riwayat_lintas_pid_tetap_terurut_terbaru_dulu(): void
    {
        $pids  = $this->panggil('pidOmnicare', 'C-ISMU');
        $rekam = $this->panggil('rekamMedisOmnicare', $pids);

        $this->assertSame('2026-08-20', $rekam->first()->date);
        $this->assertSame('2026-03-01', $rekam->last()->date);
    }

    /** LTV & kunjungan diakumulasi lintas cabang — bukan satu cabang saja. */
    public function test_ltv_menjumlahkan_seluruh_cabang(): void
    {
        $pids = $this->panggil('pidOmnicare', 'C-ISMU');
        $r    = $this->panggil('ringkasanOmnicare', $pids);

        $this->assertSame(1000000, $r['ltv'], '300rb Bintaro + 700rb Sunter');
        $this->assertSame(2, $r['visits']);
        $this->assertSame('2026-08-20', $r['last_visit']);
    }

    /**
     * ⛔ KENDALI NEGATIF, dijalankan di dalam uji — bukan sekadar dijanjikan
     *    di komentar. Ini meniru persis cacat lamanya: pakai SATU pid saja.
     *    Kalau ini hijau, uji-uji di atas tidak membuktikan apa pun.
     */
    public function test_kendali_negatif_satu_pid_saja_kehilangan_satu_cabang(): void
    {
        $pids = $this->panggil('pidOmnicare', 'C-ISMU');

        // Cacat lama: value('omnicare_pid') → satu pid sembarang.
        $caraLama = DB::table('omnicare_medical_record')
            ->where('p_id', $pids[0])
            ->get();

        $this->assertCount(1, $caraLama,
            'cara lama HARUS kehilangan satu cabang — kalau tidak, uji di atas tak membuktikan apa pun');
        $this->assertLessThan(
            $this->panggil('rekamMedisOmnicare', $pids)->count(),
            $caraLama->count()
        );
    }

    // ── Pagar pasien yang memang tak punya apa-apa ──────────────────────────

    public function test_pasien_tanpa_tautan_tidak_error(): void
    {
        $pids = $this->panggil('pidOmnicare', 'C-TANPA-TAUTAN');

        $this->assertSame([], $pids);
        $this->assertCount(0, $this->panggil('rekamMedisOmnicare', $pids));
        $this->assertSame(
            ['ltv' => 0, 'visits' => 0, 'last_visit' => null],
            $this->panggil('ringkasanOmnicare', $pids)
        );
    }

    public function test_id_pasien_kosong_tidak_mengembalikan_pid_siapa_pun(): void
    {
        $this->assertSame([], $this->panggil('pidOmnicare', null));
        $this->assertSame([], $this->panggil('pidOmnicare', ''));
    }
}
