<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Uji UJUNG-KE-UJUNG perbaikan penjodohan Omnicare 2026-09-16, di atas SQLite
 * in-memory dengan skema minimal yang dibuat di sini.
 *
 * ⚠️ Kenapa bukan data nyata: mysqld LOKAL mesin ini mati (ada `data_corrupt`
 *    di direktori XAMPP) dan DEV memakai Omnicare yang basi. Uji ini karena itu
 *    membuktikan PERILAKU KODE, bukan angka prod. Angka sebelum/sesudah yang
 *    diminta kriteria terima harus datang dari menjalankan `--dry-run` di
 *    lingkungan yang datanya nyata — jangan kutip apa pun dari sini sebagai
 *    angka produksi.
 *
 * ⚠️ Skemanya dibuat TANPA `uq_col_customer`, meniru keadaan sesudah
 *    `database/sql/2026_09_16_customer_omnicare_link_multi_pid.sql` dijalankan.
 *    `uq_col_omnicare` DIPERTAHANKAN — pagar "1 pid = 1 pasien" ikut diuji.
 *
 *   vendor/bin/phpunit tests/Feature/PenjodohanOmnicareTest.php
 */
class PenjodohanOmnicareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'uji_sqlite',
            'database.connections.uji_sqlite' => [
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
            ],
        ]);
        DB::purge('uji_sqlite');

        $this->bangunSkema();
        $this->isiData();
    }

    // ────────────────────────────────────────────────────────────────────────

    private function bangunSkema(): void
    {
        $ddl = [
            'CREATE TABLE raw_customer (
                id VARCHAR(255) PRIMARY KEY, name VARCHAR(255), phone VARCHAR(50),
                tanggal_lahir DATE NULL, created_at DATETIME NULL)',

            'CREATE TABLE omnicare_patient (
                pid VARCHAR(50) PRIMARY KEY, full_name VARCHAR(255), dob DATE NULL,
                mobile_number VARCHAR(50) NULL, whatsapp_number VARCHAR(50) NULL)',

            // ⛔ TANPA unique di raw_customer_id — itulah inti Langkah 2.
            //    unique di omnicare_pid TETAP ADA — itulah pagar keselamatan.
            'CREATE TABLE customer_omnicare_link (
                id VARCHAR(36) PRIMARY KEY,
                raw_customer_id VARCHAR(255) NOT NULL,
                omnicare_pid VARCHAR(50) NOT NULL UNIQUE,
                match_confidence VARCHAR(20) NOT NULL DEFAULT "medium",
                match_method VARCHAR(60) NULL, match_score INTEGER NULL,
                matched_at DATETIME NULL, matched_by VARCHAR(255) NOT NULL DEFAULT "system",
                review_flag VARCHAR(40) NULL, notes TEXT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL)',

            'CREATE TABLE raw_branch (
                id VARCHAR(255) PRIMARY KEY, name VARCHAR(255),
                omnicare_place_id VARCHAR(20) NULL)',

            'CREATE TABLE raw_cus_reservation (
                id VARCHAR(255) PRIMARY KEY, raw_customer_id VARCHAR(255) NULL,
                raw_branch_id VARCHAR(255) NULL, raw_doctor_id VARCHAR(255) NULL,
                date DATE NULL, status VARCHAR(30) NULL)',

            'CREATE TABLE omnicare_sales_detail (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                p_id VARCHAR(50), encounter_id VARCHAR(40), trx_date DATE,
                place_id VARCHAR(20), branch_name VARCHAR(255), doctor VARCHAR(255),
                net_sell_price DECIMAL(15,2), item_type VARCHAR(5),
                item_name VARCHAR(255), qty_sold DECIMAL(10,2), payment_status VARCHAR(20))',

            'CREATE TABLE reservation_omnicare_link (
                id VARCHAR(36) PRIMARY KEY,
                raw_cus_reservation_id VARCHAR(255) NULL,
                omnicare_record_id VARCHAR(40) NULL,
                place_id VARCHAR(20) NULL,
                match_confidence VARCHAR(20) NOT NULL DEFAULT "no_match",
                match_method VARCHAR(50) NULL,
                service_match VARCHAR(20) NOT NULL DEFAULT "unknown",
                expected_revenue DECIMAL(15,2) NULL, actual_revenue DECIMAL(15,2) NULL,
                revenue_variance DECIMAL(15,2) NULL, payment_status VARCHAR(20) NULL,
                encounter_date DATE NULL, reconciled_at DATETIME NULL,
                reconciled_by VARCHAR(255) NOT NULL DEFAULT "system",
                notes TEXT NULL, created_at DATETIME NULL, updated_at DATETIME NULL)',
        ];

        foreach ($ddl as $sql) {
            DB::statement($sql);
        }
    }

    private function tgl(int $mundur): string
    {
        return now()->subDays($mundur)->toDateString();
    }

    private function isiData(): void
    {
        DB::table('raw_branch')->insert([
            ['id' => 'BR-BINTARO', 'name' => 'Bintaro', 'omnicare_place_id' => 'P-BIN'],
            ['id' => 'BR-SUNTER',  'name' => 'Sunter',  'omnicare_place_id' => 'P-SUN'],
        ]);

        DB::table('raw_customer')->insert([
            // Satu orang, dua pid cabang berbeda.
            ['id' => 'C-ISMU', 'name' => 'ISMU RAHARJO', 'phone' => '081111111111', 'tanggal_lahir' => '1975-03-04'],
            // Ibu & anak berbagi satu nomor.
            ['id' => 'C-SITI', 'name' => 'SITI AMINAH',  'phone' => '082222222222', 'tanggal_lahir' => '1990-05-05'],
            // Dua rekam bernama persis sama, tanggal lahir bertentangan → ambigu.
            ['id' => 'C-AYU',  'name' => 'AYU LESTARI',  'phone' => '083333333333', 'tanggal_lahir' => null],
        ]);

        DB::table('omnicare_patient')->insert([
            ['pid' => 'B-2007-2758', 'full_name' => 'ISMU RAHARJO', 'dob' => '1975-03-04', 'mobile_number' => '081111111111', 'whatsapp_number' => null],
            ['pid' => 'S-2105-0300', 'full_name' => 'ISMU RAHARJO', 'dob' => '1975-03-04', 'mobile_number' => '081111111111', 'whatsapp_number' => null],
            ['pid' => 'B-2007-0001', 'full_name' => 'SITI AMINAH',       'dob' => '1990-05-05', 'mobile_number' => '082222222222', 'whatsapp_number' => null],
            ['pid' => 'B-2007-0002', 'full_name' => 'SITI AMINAH PUTRI', 'dob' => '2015-05-05', 'mobile_number' => '082222222222', 'whatsapp_number' => null],
            ['pid' => 'B-2007-0010', 'full_name' => 'AYU LESTARI', 'dob' => '1990-01-01', 'mobile_number' => '083333333333', 'whatsapp_number' => null],
            ['pid' => 'B-2007-0011', 'full_name' => 'AYU LESTARI', 'dob' => '2015-01-01', 'mobile_number' => '083333333333', 'whatsapp_number' => null],
        ]);

        // Keadaan awal: ISMU sudah tertaut ke pid Bintaro SAJA — persis bentuk
        // cacat yang diperbaiki (pid Sunter tak akan pernah ketemu).
        DB::table('customer_omnicare_link')->insert([
            'id' => 'L-1', 'raw_customer_id' => 'C-ISMU', 'omnicare_pid' => 'B-2007-2758',
            'match_confidence' => 'exact', 'match_method' => 'phone+dob+name', 'match_score' => 100,
            'matched_at' => now(), 'matched_by' => 'system', 'created_at' => now(),
        ]);
    }

    private function jalankan(string $cmd, array $args = []): string
    {
        Artisan::call($cmd, $args);
        return Artisan::output();
    }

    // ══════════════════════════════════════════════════════════════════════
    // LANGKAH 2 — banyak pid per pasien
    // ══════════════════════════════════════════════════════════════════════

    /** ⭐ Kunjungan di cabang kedua akhirnya punya pegangan. */
    public function test_pid_cabang_kedua_ditambahkan_ke_pasien_yang_sudah_tertaut(): void
    {
        $this->jalankan('omnicare:match-patients');

        $pids = DB::table('customer_omnicare_link')
            ->where('raw_customer_id', 'C-ISMU')->orderBy('omnicare_pid')->pluck('omnicare_pid')->all();

        $this->assertSame(['B-2007-2758', 'S-2105-0300'], $pids);
    }

    /** ⛔ Tautan LAMA tidak boleh berubah pasangannya (kriteria terima #4). */
    public function test_tautan_lama_tidak_berubah(): void
    {
        $sebelum = DB::table('customer_omnicare_link')->where('id', 'L-1')->first();
        $this->jalankan('omnicare:match-patients');
        $sesudah = DB::table('customer_omnicare_link')->where('id', 'L-1')->first();

        $this->assertSame($sebelum->raw_customer_id, $sesudah->raw_customer_id);
        $this->assertSame($sebelum->omnicare_pid, $sesudah->omnicare_pid);
    }

    /** ⛔ Kriteria terima #3 — nol pid menempel ke lebih dari satu pasien. */
    public function test_satu_pid_tetap_milik_satu_pasien(): void
    {
        $this->jalankan('omnicare:match-patients');

        $bocor = DB::table('customer_omnicare_link')
            ->select('omnicare_pid')
            ->groupBy('omnicare_pid')
            ->havingRaw('COUNT(DISTINCT raw_customer_id) > 1')
            ->get();

        $this->assertCount(0, $bocor);
    }

    /** ⛔ Anak yang menumpang nomor ibunya tidak ikut tertaut. */
    public function test_anak_di_nomor_yang_sama_tidak_ikut_tertaut(): void
    {
        $this->jalankan('omnicare:match-patients');

        $pids = DB::table('customer_omnicare_link')
            ->where('raw_customer_id', 'C-SITI')->pluck('omnicare_pid')->all();

        $this->assertSame(['B-2007-0001'], $pids, 'hanya ibunya yang boleh tertaut');
    }

    /** ⛔ Penjaga ambiguitas masih menahan: nama sama persis, DOB bertengkar. */
    public function test_nama_kembar_dob_bertengkar_tetap_ditahan(): void
    {
        $out = $this->jalankan('omnicare:match-patients');

        $this->assertSame(0, DB::table('customer_omnicare_link')
            ->where('raw_customer_id', 'C-AYU')->count());
        $this->assertStringContainsString('AMBIGU', $out);
    }

    /** Antrean ambigu harus BISA DIBACA manusia, bukan cuma jadi angka. */
    public function test_daftar_ambigu_bisa_dicetak(): void
    {
        $out = $this->jalankan('omnicare:match-patients', ['--dry-run' => true, '--daftar-ambigu' => true]);

        $this->assertStringContainsString('AYU LESTARI', $out);
        $this->assertStringContainsString('B-2007-0010', $out);
    }

    public function test_dry_run_tidak_menulis_apa_pun(): void
    {
        $this->jalankan('omnicare:match-patients', ['--dry-run' => true]);
        $this->assertSame(1, DB::table('customer_omnicare_link')->count());
    }

    /** Idempoten: jalan kedua tidak menambah baris. */
    public function test_jalan_kedua_tidak_menambah_apa_pun(): void
    {
        $this->jalankan('omnicare:match-patients');
        $n = DB::table('customer_omnicare_link')->count();

        $this->jalankan('omnicare:match-patients');
        $this->assertSame($n, DB::table('customer_omnicare_link')->count());
    }

    // ══════════════════════════════════════════════════════════════════════
    // REKONSILIASI — lintas cabang, pembatalan, rerun
    // ══════════════════════════════════════════════════════════════════════

    private function reservasi(string $id, string $cust, string $branch, int $mundur, string $status): void
    {
        DB::table('raw_cus_reservation')->insert([
            'id' => $id, 'raw_customer_id' => $cust, 'raw_branch_id' => $branch,
            'date' => $this->tgl($mundur), 'status' => $status,
        ]);
    }

    private function kunjungan(string $pid, string $enc, int $mundur, string $place): void
    {
        DB::table('omnicare_sales_detail')->insert([
            'p_id' => $pid, 'encounter_id' => $enc, 'trx_date' => $this->tgl($mundur),
            'place_id' => $place, 'branch_name' => 'x', 'doctor' => 'drg. A',
            'net_sell_price' => 500000, 'item_type' => 'T', 'item_name' => 'Scaling',
            'qty_sold' => 1, 'payment_status' => 'paid',
        ]);
    }

    private function vonis(string $resId): ?object
    {
        return DB::table('reservation_omnicare_link')
            ->where('raw_cus_reservation_id', $resId)->first();
    }

    /**
     * ⭐ INTI PERBAIKAN. Pasien booking di Bintaro, datang tercatat di bawah pid
     * SUNTER. Sebelum perbaikan, kunjungan ini tak terlihat dan reservasinya
     * dicap tidak hadir.
     */
    public function test_kunjungan_di_bawah_pid_cabang_lain_kini_terjodoh(): void
    {
        $this->jalankan('omnicare:match-patients');   // C-ISMU kini punya 2 pid
        $this->reservasi('R-1', 'C-ISMU', 'BR-BINTARO', 10, 'confirmed');
        $this->kunjungan('S-2105-0300', 'ENC-1', 10, 'P-BIN');

        $this->jalankan('omnicare:reconcile', ['--days' => 30]);

        $v = $this->vonis('R-1');
        $this->assertNotNull($v);
        $this->assertSame('ENC-1', $v->omnicare_record_id);
        $this->assertSame('link_date_branch', $v->match_method);
    }

    /** ⛔ Pasien ber-2 pid tidak boleh menghasilkan DUA baris vonis (fan-out). */
    public function test_pasien_dua_pid_tetap_satu_baris_vonis(): void
    {
        $this->jalankan('omnicare:match-patients');
        $this->reservasi('R-1', 'C-ISMU', 'BR-BINTARO', 10, 'confirmed');
        $this->kunjungan('S-2105-0300', 'ENC-1', 10, 'P-BIN');

        $this->jalankan('omnicare:reconcile', ['--days' => 30]);

        $this->assertSame(1, DB::table('reservation_omnicare_link')
            ->where('raw_cus_reservation_id', 'R-1')->count());
    }

    /** ⛔ LANGKAH 3a — pembatalan BUKAN ketidakhadiran. */
    public function test_reservasi_dibatalkan_tidak_dicap_tidak_hadir(): void
    {
        $this->jalankan('omnicare:match-patients');
        $this->reservasi('R-BATAL', 'C-SITI', 'BR-BINTARO', 10, 'cancelled');
        $this->reservasi('R-ALPA',  'C-SITI', 'BR-BINTARO', 10, 'confirmed');

        $this->jalankan('omnicare:reconcile', ['--days' => 30]);

        $this->assertSame('cancelled',     $this->vonis('R-BATAL')->match_method);
        $this->assertSame('orphan_noshow', $this->vonis('R-ALPA')->match_method);
    }

    /** Pembatalan yang TETAP datang tetap di-link — yang berubah cuma vonis kosong. */
    public function test_pembatalan_yang_tetap_datang_tetap_terjodoh(): void
    {
        $this->jalankan('omnicare:match-patients');
        $this->reservasi('R-BATAL', 'C-SITI', 'BR-BINTARO', 10, 'cancelled');
        $this->kunjungan('B-2007-0001', 'ENC-9', 10, 'P-BIN');

        $this->jalankan('omnicare:reconcile', ['--days' => 30]);

        $this->assertSame('ENC-9', $this->vonis('R-BATAL')->omnicare_record_id);
    }

    /** LANGKAH 3a untuk baris LAMA yang terlanjur bercap no-show. */
    public function test_perbaiki_batal_melepas_cap_lama(): void
    {
        $this->reservasi('R-LAMA', 'C-SITI', 'BR-BINTARO', 20, 'cancelled');
        DB::table('reservation_omnicare_link')->insert([
            'id' => 'V-LAMA', 'raw_cus_reservation_id' => 'R-LAMA', 'place_id' => 'P-BIN',
            'match_confidence' => 'no_match', 'match_method' => 'orphan_noshow',
            'service_match' => 'unknown', 'reconciled_at' => now(), 'reconciled_by' => 'system',
            'created_at' => now(),
        ]);

        $this->jalankan('omnicare:reconcile', ['--perbaiki-batal' => true]);

        $this->assertSame('cancelled', $this->vonis('R-LAMA')->match_method);
    }

    /**
     * ⚠️ KONSISTENSI. Alur harian mengenali status lewat `dibatalkan()` yang
     * meng-huruf-kecilkan dulu; `--perbaiki-batal` menyaring lewat SQL. Kalau
     * keduanya tidak sepakat, satu jalur membetulkan dan jalur lain tidak —
     * dan selisihnya tak akan pernah kelihatan.
     */
    public function test_perbaiki_batal_mengenali_status_huruf_besar(): void
    {
        $this->reservasi('R-BESAR', 'C-SITI', 'BR-BINTARO', 20, 'Cancelled');
        DB::table('reservation_omnicare_link')->insert([
            'id' => 'V-BESAR', 'raw_cus_reservation_id' => 'R-BESAR', 'place_id' => 'P-BIN',
            'match_confidence' => 'no_match', 'match_method' => 'orphan_noshow',
            'service_match' => 'unknown', 'reconciled_at' => now(), 'reconciled_by' => 'system',
            'created_at' => now(),
        ]);

        $this->jalankan('omnicare:reconcile', ['--perbaiki-batal' => true]);

        $this->assertSame('cancelled', $this->vonis('R-BESAR')->match_method);
    }

    /** ⛔ --perbaiki-batal tidak boleh menyentuh vonis yang disentuh MANUSIA. */
    public function test_perbaiki_batal_tidak_menimpa_keputusan_manusia(): void
    {
        $this->reservasi('R-MAN', 'C-SITI', 'BR-BINTARO', 20, 'cancelled');
        DB::table('reservation_omnicare_link')->insert([
            'id' => 'V-MAN', 'raw_cus_reservation_id' => 'R-MAN', 'place_id' => 'P-BIN',
            'match_confidence' => 'no_match', 'match_method' => 'orphan_noshow',
            'service_match' => 'unknown', 'reconciled_at' => now(),
            'reconciled_by' => 'user-123', 'created_at' => now(),
        ]);

        $this->jalankan('omnicare:reconcile', ['--perbaiki-batal' => true]);

        $this->assertSame('orphan_noshow', $this->vonis('R-MAN')->match_method);
    }

    /** ⭐ LANGKAH 3 — vonis `no_customer_link` lama dinilai ulang sesudah tertaut. */
    public function test_revisit_menilai_ulang_vonis_no_customer_link(): void
    {
        $this->reservasi('R-ULANG', 'C-ISMU', 'BR-BINTARO', 10, 'confirmed');
        $this->kunjungan('S-2105-0300', 'ENC-7', 10, 'P-BIN');
        DB::table('reservation_omnicare_link')->insert([
            'id' => 'V-ULANG', 'raw_cus_reservation_id' => 'R-ULANG', 'place_id' => 'P-BIN',
            'match_confidence' => 'no_match', 'match_method' => 'no_customer_link',
            'service_match' => 'unknown', 'reconciled_at' => now(), 'reconciled_by' => 'system',
            'created_at' => now(),
        ]);

        $this->jalankan('omnicare:match-patients');
        $this->jalankan('omnicare:reconcile', ['--revisit' => true, '--days' => 30]);

        $v = $this->vonis('R-ULANG');
        $this->assertSame('ENC-7', $v->omnicare_record_id);
        $this->assertSame(1, DB::table('reservation_omnicare_link')
            ->where('raw_cus_reservation_id', 'R-ULANG')->count());
    }

    /** ⛔ PAGAR RERUN — baris yang sudah ter-link TIDAK BOLEH disentuh. */
    public function test_revisit_tidak_menyentuh_baris_yang_sudah_terjodoh(): void
    {
        $this->reservasi('R-AMAN', 'C-ISMU', 'BR-BINTARO', 10, 'confirmed');
        DB::table('reservation_omnicare_link')->insert([
            'id' => 'V-AMAN', 'raw_cus_reservation_id' => 'R-AMAN',
            'omnicare_record_id' => 'ENC-LAMA', 'place_id' => 'P-BIN',
            'match_confidence' => 'exact', 'match_method' => 'link_date_branch',
            'service_match' => 'unknown', 'actual_revenue' => 750000,
            'reconciled_at' => now(), 'reconciled_by' => 'system', 'created_at' => now(),
        ]);

        $this->jalankan('omnicare:reconcile', ['--revisit' => true, '--days' => 30]);

        $v = $this->vonis('R-AMAN');
        $this->assertSame('V-AMAN', $v->id, 'baris ter-link tidak boleh dibuat ulang');
        $this->assertSame('ENC-LAMA', $v->omnicare_record_id);
        $this->assertEquals(750000, (float) $v->actual_revenue);
    }

    /** --dry-run pada --revisit tidak boleh menghapus apa pun. */
    public function test_revisit_dry_run_tidak_menghapus(): void
    {
        $this->reservasi('R-ULANG', 'C-ISMU', 'BR-BINTARO', 10, 'confirmed');
        DB::table('reservation_omnicare_link')->insert([
            'id' => 'V-ULANG', 'raw_cus_reservation_id' => 'R-ULANG', 'place_id' => 'P-BIN',
            'match_confidence' => 'no_match', 'match_method' => 'no_customer_link',
            'service_match' => 'unknown', 'reconciled_at' => now(), 'reconciled_by' => 'system',
            'created_at' => now(),
        ]);

        $this->jalankan('omnicare:reconcile', ['--revisit' => true, '--dry-run' => true, '--days' => 30]);

        $this->assertNotNull(DB::table('reservation_omnicare_link')->where('id', 'V-ULANG')->first());
    }

    /**
     * ⛔ DRY RUN HARUS MERAMAL, BUKAN DIAM. Runbook menyuruh dry-run dulu;
     * kalau ia melapor "0 linked" padahal jalan sungguhannya akan menaut,
     * orang menyimpulkan perintahnya tidak berguna dan tidak menjalankannya.
     */
    public function test_revisit_dry_run_meramalkan_jodoh_yang_akan_didapat(): void
    {
        $this->reservasi('R-ULANG', 'C-ISMU', 'BR-BINTARO', 10, 'confirmed');
        $this->kunjungan('S-2105-0300', 'ENC-7', 10, 'P-BIN');
        DB::table('reservation_omnicare_link')->insert([
            'id' => 'V-ULANG', 'raw_cus_reservation_id' => 'R-ULANG', 'place_id' => 'P-BIN',
            'match_confidence' => 'no_match', 'match_method' => 'no_customer_link',
            'service_match' => 'unknown', 'reconciled_at' => now(), 'reconciled_by' => 'system',
            'created_at' => now(),
        ]);
        $this->jalankan('omnicare:match-patients');

        $kering = $this->jalankan('omnicare:reconcile', ['--revisit' => true, '--dry-run' => true, '--days' => 30]);
        $basah  = $this->jalankan('omnicare:reconcile', ['--revisit' => true, '--days' => 30]);

        // Yang diramalkan dry-run harus sama dengan yang benar-benar terjadi.
        $this->assertSame(
            $this->angkaLinked($kering),
            $this->angkaLinked($basah),
            'ramalan dry-run harus cocok dengan hasil sungguhan'
        );
        $this->assertSame(1, $this->angkaLinked($basah));
    }

    /** Ambil angka baris "LINKED (encounter ketemu)" dari tabel laporan. */
    private function angkaLinked(string $out): int
    {
        return preg_match('/LINKED \(encounter ketemu\)\s*\|?\s*([\d,.]+)/u', $out, $m)
            ? (int) str_replace([',', '.'], '', $m[1])
            : -1;
    }

    // ══════════════════════════════════════════════════════════════════════
    // LANGKAH 3b — tautan rapuh ditandai, TIDAK dihapus
    // ══════════════════════════════════════════════════════════════════════

    public function test_tautan_dob_bertentangan_ditandai_bukan_dihapus(): void
    {
        // Tautan lama dibuat phone+name saja; DOB kita kini bertentangan.
        DB::table('raw_customer')->where('id', 'C-AYU')->update(['tanggal_lahir' => '1988-08-08']);
        DB::table('customer_omnicare_link')->insert([
            'id' => 'L-RAPUH', 'raw_customer_id' => 'C-AYU', 'omnicare_pid' => 'B-2007-0010',
            'match_confidence' => 'medium', 'match_method' => 'phone+name', 'match_score' => 65,
            'matched_at' => now(), 'matched_by' => 'system', 'created_at' => now(),
        ]);

        $this->jalankan('omnicare:tinjau-tautan', ['--dob' => true, '--tulis' => true]);

        $l = DB::table('customer_omnicare_link')->where('id', 'L-RAPUH')->first();
        $this->assertNotNull($l, 'tautan TIDAK boleh dihapus');
        $this->assertSame('dob_bertentangan', $l->review_flag);
    }

    public function test_tinjau_tautan_tanpa_tulis_tidak_menandai(): void
    {
        DB::table('raw_customer')->where('id', 'C-AYU')->update(['tanggal_lahir' => '1988-08-08']);
        DB::table('customer_omnicare_link')->insert([
            'id' => 'L-RAPUH', 'raw_customer_id' => 'C-AYU', 'omnicare_pid' => 'B-2007-0010',
            'match_confidence' => 'medium', 'match_method' => 'phone+name', 'match_score' => 65,
            'matched_at' => now(), 'matched_by' => 'system', 'created_at' => now(),
        ]);

        $this->jalankan('omnicare:tinjau-tautan', ['--dob' => true]);

        $this->assertNull(DB::table('customer_omnicare_link')->where('id', 'L-RAPUH')->value('review_flag'));
    }

    /** Tautan yang DOB-nya cocok tidak boleh ikut ditandai. */
    public function test_tautan_dob_cocok_tidak_ditandai(): void
    {
        $this->jalankan('omnicare:tinjau-tautan', ['--dob' => true, '--tulis' => true]);

        $this->assertNull(DB::table('customer_omnicare_link')->where('id', 'L-1')->value('review_flag'));
    }

    // ══════════════════════════════════════════════════════════════════════
    // KONSISTENSI MESIN ↔ LAYAR
    // ══════════════════════════════════════════════════════════════════════

    /**
     * ⛔ PENJAGA HANYUT. Tiap vonis "tanpa encounter" harus punya ember di
     * /reconcile. Kalau tidak, reservasinya lenyap dari layar tanpa jejak —
     * dan itu sudah TERJADI pada vonis `cancelled` saat pertama ditambahkan:
     * baris yang dulu tampil sebagai no-show hilang sama sekali.
     *
     * ⚠️ Ini memeriksa SUMBER, bukan hasil render — ia menangkap "vonis baru
     *    lupa disambungkan ke layar", bukan salah tata letak.
     */
    public function test_setiap_vonis_tanpa_encounter_punya_ember_di_layar(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/ReconciliationController.php'));

        foreach (\App\Console\Commands\OmnicareReconcile::VONIS_TANPA_ENCOUNTER as $vonis) {
            if ($vonis === 'conflict_multi') {
                // Ember conflict dipilih lewat match_confidence 'low', bukan nama vonis.
                $this->assertStringContainsString("'low'", $src);
                continue;
            }
            $this->assertStringContainsString(
                "'{$vonis}'", $src,
                "vonis '{$vonis}' tidak punya ember di ReconciliationController — "
                . 'reservasinya akan lenyap dari layar'
            );
        }
    }

    /** ⛔ Baris DIBATALKAN tidak boleh dilabeli NO-SHOW di layar. */
    public function test_layar_membedakan_dibatalkan_dari_no_show(): void
    {
        $blade = file_get_contents(resource_path('views/report/reconcile.blade.php'));

        $this->assertStringContainsString('DIBATALKAN', $blade);
        $this->assertStringContainsString("match_method === 'cancelled'", $blade);
    }

    /** Antrean harus mengakui terus terang bahwa layarnya belum ada. */
    public function test_antrean_mengakui_layarnya_belum_ada(): void
    {
        $out = $this->jalankan('omnicare:tinjau-tautan', ['--antrean' => true]);

        $this->assertStringContainsString('DAFTAR, bukan layar keputusan', $out);
    }
}
