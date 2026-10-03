<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtJadwalDokter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — hari praktik dokter untuk SEKUMPULAN baris sekaligus.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG MELAHIRKAN BERKAS INI
 * ────────────────────────────────────────────────────────────────────────────
 * prompt_jadwal_kontrol.md menaruh hari praktik dokter sebagai KOLOM TABEL,
 * bukan lagi isi lembar satu pasien — dan mewajibkan: "Pastikan query efisien
 * (eager load / satu query agregat per dokter per minggu), jangan N+1 per
 * baris. List harus tetap cepat untuk ribuan pasien."
 *
 * Versi naif (memanggil `untuk()` per baris) membayar ±27 kueri per baris:
 * pencarian dokter + berjadwalDi + computeAvailableDates + computeTimeSlots
 * enam kali. Untuk 50 baris itu ~1.350 kueri untuk satu muat halaman.
 *
 * ⚠ Dan gerbang H-3 TIDAK menyelamatkan — ini yang paling mudah disalahpahami.
 *   "Hari ini >= H-3 sebelum jatuh tempo" juga BENAR untuk setiap baris yang
 *   jatuh temponya sudah lewat, jadi di sub-tab Terlewat 100% baris lolos
 *   gerbang. Yang menekan kuerinya adalah pra-muat, bukan gerbangnya.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG DIJAGA DI SINI
 * ────────────────────────────────────────────────────────────────────────────
 *   • ⭐ jumlah kueri TIDAK NAIK saat barisnya berlipat — pagar utamanya
 *   • ⭐ angkanya IDENTIK dengan `untuk()` untuk tanggal yang sama. Ini janji
 *        yang paling mahal kalau dilanggar: tabel dan lembar reservasi dilihat
 *        Sales berurutan dalam satu napas, dan dua angka berbeda untuk hari
 *        yang sama membuat keduanya tidak bisa dipercaya.
 *   • hari CUTI ditandai, tidak dihilangkan diam-diam
 *   • pra-muat DIBERSIHKAN sesudahnya — kalau tidak, lembar reservasi yang
 *     dirender sesudah tabel kehilangan hari praktik ke-4 sampai ke-6 tanpa
 *     satu pun pesan galat
 */
class OrtoJadwalDokterMassalTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-JMASSAL';

    private string $placeId;
    private string $branchId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ort_case', 'raw_doctor', 'raw_doctor_schedule', 'raw_cus_reservation', 'raw_doctor_leave'] as $t) {
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

    /** @param list<array{hari:int,mulai:string,selesai:string,type?:string}> $jadwal */
    private function dokter(string $kode, array $jadwal): string
    {
        $id = 'uji-jmas-' . Str::random(8);
        DB::table('raw_doctor')->insert([
            'id' => $id, 'name' => 'drg. Massal ' . $kode,
            'omnicare_doc_id' => $kode, 'is_active' => 1,
        ]);

        foreach ($jadwal as $j) {
            DB::table('raw_doctor_schedule')->insert([
                'id' => (string) Str::uuid(), 'raw_doctor_id' => $id,
                'raw_branch_id' => $this->branchId, 'day' => $j['hari'],
                'start_hour' => $j['mulai'], 'end_hour' => $j['selesai'],
                'type' => $j['type'] ?? 'work',
            ]);
        }

        return $id;
    }

    /**
     * Satu baris seperti yang dikeluarkan OrtQuery::daftar() — stdClass, bukan
     * model. Itu memang bentuk yang diterima untukBanyak(), dan membedakannya
     * penting: kolom yang tidak ikut di-select melempar "Undefined property",
     * bukan null diam-diam.
     */
    private function baris(?string $kodeDokter, ?string $lastVisit, ?int $median = 30): object
    {
        return (object) [
            'ort_case_id'     => (string) Str::uuid(),
            'doctor_ref_id'   => $kodeDokter,
            'last_visit_date' => $lastVisit,
            'median_gap_days' => $median,
        ];
    }

    private function pasien(?string $kodeDokter, ?string $lastVisit): OrtCase
    {
        $pid = self::TANDA . '-' . Str::random(6);

        return OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji Massal ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => Carbon::today()->subDays(300)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF, 'doctor_ref_id' => $kodeDokter,
            'last_visit_date' => $lastVisit, 'median_gap_days' => 30,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);
    }

    private function cuti(string $doctorId, string $dari, string $sampai): void
    {
        DB::table('raw_doctor_leave')->insert([
            'id' => 'uji-jmas-cuti-' . Str::random(8),
            'raw_doctor_id' => $doctorId,
            'start_date' => $dari, 'end_date' => $sampai,
            'reason' => self::TANDA, 'created_at' => now(),
        ]);
    }

    private function reservasi(string $doctorId, string $tanggal, string $mulai, string $selesai): void
    {
        DB::table('raw_cus_reservation')->insert([
            'id' => 'uji-jmas-rsv-' . Str::random(10),
            'raw_branch_id' => $this->branchId, 'raw_doctor_id' => $doctorId,
            'date' => $tanggal, 'start_hour' => $mulai, 'end_hour' => $selesai,
            'status' => 'confirmed', 'promo_klaim' => 0,
        ]);
    }

    /** Jalankan untukBanyak() sambil menghitung kueri yang dipakainya. */
    private function ukur(array $baris): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $hasil = app(OrtJadwalDokter::class)->untukBanyak($baris, $this->placeId);
        $n     = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$hasil, $n];
    }

    /** Hari ISO yang SELALU ada di dalam jendela 7 hari mulai hari ini. */
    private function isoDalamJendela(int $geser = 2): int
    {
        return Carbon::today()->addDays($geser)->dayOfWeekIso;
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⭐ Pagar utama: kueri tidak naik mengikuti baris
    // ════════════════════════════════════════════════════════════════════════

    public function test_kueri_tidak_naik_saat_baris_berlipat(): void
    {
        $iso  = $this->isoDalamJendela();
        $kode = 'JMASS-' . Str::random(5);
        $this->dokter($kode, [['hari' => $iso, 'mulai' => '09:00:00', 'selesai' => '17:00:00']]);

        $lv = Carbon::today()->subDays(40)->toDateString();   // sudah lewat jatuh tempo

        $sepuluh  = array_map(fn () => $this->baris($kode, $lv), range(1, 10));
        $empatpuluh = array_map(fn () => $this->baris($kode, $lv), range(1, 40));

        [$h10, $n10] = $this->ukur($sepuluh);
        [$h40, $n40] = $this->ukur($empatpuluh);

        $this->assertCount(10, $h10);
        $this->assertCount(40, $h40);

        // Inilah klaimnya: empat kali lebih banyak baris, kueri yang sama.
        $this->assertSame(
            $n10,
            $n40,
            "Kueri naik dari {$n10} ke {$n40} saat baris 10 → 40. Pra-muatnya bocor: "
            . 'ada kueri yang masih dijalankan per baris, bukan per dokter.'
        );

        // Dan jumlahnya memang kecil, bukan sekadar "sama-sama besar".
        $this->assertLessThanOrEqual(8, $n40, "Dipakai {$n40} kueri untuk 40 baris; harusnya segelintir agregat.");
    }

    public function test_kueri_tidak_naik_saat_pasien_berbagi_dokter(): void
    {
        $iso = $this->isoDalamJendela();
        $lv  = Carbon::today()->subDays(40)->toDateString();

        $kodeA = 'JMASA-' . Str::random(5);
        $this->dokter($kodeA, [['hari' => $iso, 'mulai' => '09:00:00', 'selesai' => '17:00:00']]);

        // 20 pasien, SATU dokter — hasilnya tidak bergantung pasien sama
        // sekali, hanya pada (cabang, dokter, jendela). Jadi memo-nya harus
        // menekan ini jadi satu perhitungan.
        [, $nSatu] = $this->ukur(array_map(fn () => $this->baris($kodeA, $lv), range(1, 20)));

        $kodeB = 'JMASB-' . Str::random(5);
        $this->dokter($kodeB, [['hari' => $iso, 'mulai' => '09:00:00', 'selesai' => '17:00:00']]);

        // 20 pasien, DUA dokter. Kuerinya tetap agregat yang sama — dokter
        // kedua ikut dalam whereIn yang sudah ada, bukan putaran baru.
        $campur = [];
        foreach (range(1, 20) as $i) {
            $campur[] = $this->baris($i % 2 ? $kodeA : $kodeB, $lv);
        }
        [, $nDua] = $this->ukur($campur);

        $this->assertSame($nSatu, $nDua, 'Dokter kedua menambah kueri; whereIn-nya tidak memuat semua dokter.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⭐ Angkanya wajib sama dengan untuk()
    // ════════════════════════════════════════════════════════════════════════

    public function test_angka_sama_dengan_untuk_satu_pasien(): void
    {
        $iso  = $this->isoDalamJendela();
        $kode = 'JMASC-' . Str::random(5);
        $dok  = $this->dokter($kode, [['hari' => $iso, 'mulai' => '09:00:00', 'selesai' => '12:00:00']]);

        // Satu reservasi supaya angka terisi-nya bukan nol — nol akan lolos
        // walau rumusnya salah.
        $tgl = Carbon::today();
        while ($tgl->dayOfWeekIso !== $iso) {
            $tgl->addDay();
        }
        $this->reservasi($dok, $tgl->toDateString(), '09:00:00', '09:30:00');

        $lv     = Carbon::today()->subDays(40)->toDateString();
        $kasus  = $this->pasien($kode, $lv);

        $satu   = app(OrtJadwalDokter::class)->untuk($kasus, $this->placeId);
        [$banyak] = $this->ukur([$this->baris($kode, $lv)]);
        $massal = reset($banyak);

        $this->assertSame($satu['dokter'], $massal['dokter']);
        $this->assertNotEmpty($satu['hari']);
        $this->assertNotEmpty($massal['hari']);

        // Daftar harinya memang beda panjang — untuk() menjelajah 70 hari dan
        // mengambil 6 hari PRAKTIK, untukBanyak() hanya 7 hari KALENDER.
        // Yang wajib sama adalah angka untuk tanggal yang SAMA.
        $perTanggal = [];
        foreach ($satu['hari'] as $h) {
            $perTanggal[$h['tanggal']] = $h;
        }

        $diperiksa = 0;
        foreach ($massal['hari'] as $h) {
            if ($h['cuti'] || ! isset($perTanggal[$h['tanggal']])) {
                continue;
            }
            $a = $perTanggal[$h['tanggal']];
            $this->assertSame($a['total'], $h['total'], "total beda di {$h['tanggal']}");
            $this->assertSame($a['terisi'], $h['terisi'], "terisi beda di {$h['tanggal']}");
            $this->assertSame($a['penuh'], $h['penuh'], "penuh beda di {$h['tanggal']}");
            $this->assertSame($a['jam'], $h['jam'], "jam beda di {$h['tanggal']}");
            $diperiksa++;
        }

        $this->assertGreaterThan(0, $diperiksa, 'Tidak ada tanggal yang beririsan — perbandingannya kosong.');
        $this->assertGreaterThan(0, $perTanggal[$tgl->toDateString()]['terisi'] ?? 0, 'Reservasinya tidak mengurangi slot.');
    }

    public function test_istirahat_tetap_dipotong_di_jalur_massal(): void
    {
        $iso  = $this->isoDalamJendela();
        $kode = 'JMASD-' . Str::random(5);
        $this->dokter($kode, [
            ['hari' => $iso, 'mulai' => '09:00:00', 'selesai' => '13:00:00'],
            ['hari' => $iso, 'mulai' => '11:00:00', 'selesai' => '12:00:00', 'type' => 'break'],
        ]);

        [$hasil] = $this->ukur([$this->baris($kode, Carbon::today()->subDays(40)->toDateString())]);
        $hari = reset($hasil)['hari'];

        $this->assertNotEmpty($hari);
        // 4 jam dikurangi 1 jam istirahat = 3 jam = 6 slot 30 menit.
        $this->assertSame(6, $hari[0]['total'], 'Jam istirahat tidak dipotong di jalur pra-muat.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // Gerbang H-3
    // ════════════════════════════════════════════════════════════════════════

    public function test_belum_masuk_h3_tidak_muncul_di_map(): void
    {
        $kode = 'JMASE-' . Str::random(5);
        $this->dokter($kode, [['hari' => $this->isoDalamJendela(), 'mulai' => '09:00:00', 'selesai' => '17:00:00']]);

        // Baru kontrol kemarin, irama 30 hari: jatuh tempo masih 29 hari lagi.
        $baru = $this->baris($kode, Carbon::today()->subDay()->toDateString(), 30);
        // Kontrol 27 hari lalu: jatuh tempo 3 hari lagi = tepat H-3, lolos.
        $h3   = $this->baris($kode, Carbon::today()->subDays(27)->toDateString(), 30);

        [$hasil] = $this->ukur([$baru, $h3]);

        $this->assertArrayNotHasKey($baru->ort_case_id, $hasil, 'Baris yang belum H-3 ikut dihitung.');
        $this->assertArrayHasKey($h3->ort_case_id, $hasil, 'Baris tepat H-3 justru dilewati.');
    }

    public function test_belum_pernah_berkunjung_dianggap_sudah_waktunya(): void
    {
        $kode = 'JMASF-' . Str::random(5);
        $this->dokter($kode, [['hari' => $this->isoDalamJendela(), 'mulai' => '09:00:00', 'selesai' => '17:00:00']]);

        // Bracket terpasang, nol kunjungan tercatat. Menyembunyikan hari
        // praktiknya justru menyembunyikan pasien yang paling perlu ditelepon.
        $b = $this->baris($kode, null, null);
        [$hasil] = $this->ukur([$b]);

        $this->assertArrayHasKey($b->ort_case_id, $hasil);
        $this->assertNotEmpty($hasil[$b->ort_case_id]['hari']);
    }

    public function test_tidak_ada_yang_lolos_gerbang_berarti_nol_kueri(): void
    {
        $kode = 'JMASG-' . Str::random(5);
        $this->dokter($kode, [['hari' => $this->isoDalamJendela(), 'mulai' => '09:00:00', 'selesai' => '17:00:00']]);

        $segar = array_map(
            fn () => $this->baris($kode, Carbon::today()->toDateString(), 30),
            range(1, 30)
        );

        [$hasil, $n] = $this->ukur($segar);

        $this->assertSame([], $hasil);
        $this->assertSame(0, $n, "Nol baris lolos gerbang tapi {$n} kueri tetap dijalankan.");
    }

    // ════════════════════════════════════════════════════════════════════════
    // Cuti — ditandai, bukan dihilangkan
    // ════════════════════════════════════════════════════════════════════════

    public function test_hari_cuti_ditandai_bukan_dihilangkan(): void
    {
        $iso  = $this->isoDalamJendela();
        $kode = 'JMASH-' . Str::random(5);
        $dok  = $this->dokter($kode, [['hari' => $iso, 'mulai' => '09:00:00', 'selesai' => '17:00:00']]);

        $tgl = Carbon::today();
        while ($tgl->dayOfWeekIso !== $iso) {
            $tgl->addDay();
        }
        $this->cuti($dok, $tgl->toDateString(), $tgl->toDateString());

        [$hasil] = $this->ukur([$this->baris($kode, Carbon::today()->subDays(40)->toDateString())]);
        $hari = reset($hasil)['hari'];

        $cocok = array_values(array_filter($hari, fn ($h) => $h['tanggal'] === $tgl->toDateString()));

        $this->assertCount(1, $cocok, 'Hari cuti hilang dari daftar — Sales tidak akan tahu kenapa harinya tidak ada.');
        $this->assertTrue($cocok[0]['cuti']);
        $this->assertSame('faint', $cocok[0]['nada']);
        $this->assertFalse($cocok[0]['penuh'], 'Cuti bukan "penuh" — tidak ada slot untuk diisi.');
    }

    public function test_lembar_satu_pasien_tetap_menyembunyikan_hari_cuti(): void
    {
        $iso  = $this->isoDalamJendela();
        $kode = 'JMASI-' . Str::random(5);
        $dok  = $this->dokter($kode, [['hari' => $iso, 'mulai' => '09:00:00', 'selesai' => '17:00:00']]);

        $tgl = Carbon::today();
        while ($tgl->dayOfWeekIso !== $iso) {
            $tgl->addDay();
        }
        $this->cuti($dok, $tgl->toDateString(), $tgl->toDateString());

        // untuk() TIDAK boleh ikut berubah: lembarnya menawarkan hari untuk
        // DIKLIK, dan hari cuti yang bisa diklik adalah janji yang salah.
        $hasil = app(OrtJadwalDokter::class)->untuk($this->pasien($kode, null), $this->placeId);

        foreach ($hasil['hari'] as $h) {
            $this->assertNotSame($tgl->toDateString(), $h['tanggal'], 'Hari cuti muncul di lembar reservasi.');
            $this->assertArrayNotHasKey('cuti', $h, 'Bentuk keluaran untuk() berubah — pemanggil lamanya ikut kena.');
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // Sebab yang bisa dibaca
    // ════════════════════════════════════════════════════════════════════════

    public function test_empat_sebab_punya_kalimat(): void
    {
        $svc = app(OrtJadwalDokter::class);

        foreach (['tanpa_dokter', 'tak_terpetakan', 'tak_praktik', 'tak_ada_hari', 'tak_ada_hari_pekan'] as $sebab) {
            $this->assertNotEmpty(
                $svc->penjelasan($sebab, 'drg. Contoh'),
                "Sebab '{$sebab}' tidak punya kalimat — selnya akan kosong tanpa penjelasan."
            );
        }
    }

    public function test_sebab_jendela_pekan_tidak_menyebut_sepuluh_minggu(): void
    {
        $svc = app(OrtJadwalDokter::class);

        // Kalimat 'tak_ada_hari' menyebut "10 minggu" karena lembar reservasi
        // memang menjelajah 70 hari. Memakainya untuk sel tabel yang hanya
        // melihat 7 hari adalah klaim yang salah.
        $this->assertStringContainsString('10 minggu', $svc->penjelasan('tak_ada_hari', 'drg. X'));
        $this->assertStringNotContainsString('10 minggu', $svc->penjelasan('tak_ada_hari_pekan', 'drg. X'));
        $this->assertStringContainsString('7 hari', $svc->penjelasan('tak_ada_hari_pekan', 'drg. X'));
    }

    public function test_kode_tak_terpetakan_dan_tanpa_kode_dibedakan(): void
    {
        $lv = Carbon::today()->subDays(40)->toDateString();

        $tanpa    = $this->baris(null, $lv);
        $ngawur   = $this->baris('JMAS-TIDAK-ADA-' . Str::random(4), $lv);

        [$hasil] = $this->ukur([$tanpa, $ngawur]);

        $this->assertSame('tanpa_dokter', $hasil[$tanpa->ort_case_id]['sebab']);
        $this->assertSame('tak_terpetakan', $hasil[$ngawur->ort_case_id]['sebab']);
    }

    public function test_dokter_terpetakan_tanpa_jadwal_di_cabang_ini(): void
    {
        $kode = 'JMASJ-' . Str::random(5);
        $this->dokter($kode, []);   // ada di raw_doctor, nol baris jadwal

        [$hasil] = $this->ukur([$this->baris($kode, Carbon::today()->subDays(40)->toDateString())]);
        $satu = reset($hasil);

        $this->assertSame('tak_praktik', $satu['sebab']);
        // Namanya tetap dibawa: "drg. X belum punya jadwal di cabang ini"
        // memberi tahu siapa yang harus diisikan jadwalnya.
        $this->assertNotNull($satu['dokter']);
    }

    // ════════════════════════════════════════════════════════════════════════
    // Nada pil
    // ════════════════════════════════════════════════════════════════════════

    public function test_hari_penuh_bernada_bad(): void
    {
        $iso  = $this->isoDalamJendela();
        $kode = 'JMASK-' . Str::random(5);
        $dok  = $this->dokter($kode, [['hari' => $iso, 'mulai' => '09:00:00', 'selesai' => '10:00:00']]);

        $tgl = Carbon::today();
        while ($tgl->dayOfWeekIso !== $iso) {
            $tgl->addDay();
        }
        // 1 jam = 2 slot 30 menit; dua reservasi menutup keduanya.
        $this->reservasi($dok, $tgl->toDateString(), '09:00:00', '09:30:00');
        $this->reservasi($dok, $tgl->toDateString(), '09:30:00', '10:00:00');

        [$hasil] = $this->ukur([$this->baris($kode, Carbon::today()->subDays(40)->toDateString())]);
        $hari = array_values(array_filter(reset($hasil)['hari'], fn ($h) => $h['tanggal'] === $tgl->toDateString()));

        $this->assertCount(1, $hari);
        $this->assertTrue($hari[0]['penuh']);
        $this->assertSame('bad', $hari[0]['nada']);
    }

    public function test_hari_longgar_bernada_ok(): void
    {
        $iso  = $this->isoDalamJendela();
        $kode = 'JMASL-' . Str::random(5);
        $this->dokter($kode, [['hari' => $iso, 'mulai' => '09:00:00', 'selesai' => '17:00:00']]);

        [$hasil] = $this->ukur([$this->baris($kode, Carbon::today()->subDays(40)->toDateString())]);
        $hari = reset($hasil)['hari'];

        $this->assertNotEmpty($hari);
        $this->assertSame('ok', $hari[0]['nada']);
        $this->assertFalse($hari[0]['penuh']);
    }

    public function test_ambang_hampir_penuh_dari_konstanta(): void
    {
        // Ambangnya harus bisa dibaca dari satu tempat, bukan tersebar di Blade.
        $this->assertSame(20, OrtJadwalDokter::AMBANG_HAMPIR_PENUH_PCT);
        $this->assertSame(7, OrtJadwalDokter::JENDELA_TABEL_HARI);
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⭐ Pra-muat wajib dibersihkan
    // ════════════════════════════════════════════════════════════════════════

    public function test_pra_muat_dibersihkan_supaya_lembar_tidak_teracuni(): void
    {
        $iso  = $this->isoDalamJendela();
        $kode = 'JMASM-' . Str::random(5);

        // Dua hari praktik: satu di dalam jendela 7 hari, satu jauh di luarnya
        // supaya selisih perilakunya kelihatan.
        $this->dokter($kode, [
            ['hari' => $iso, 'mulai' => '09:00:00', 'selesai' => '17:00:00'],
            ['hari' => ($iso % 7) + 1, 'mulai' => '09:00:00', 'selesai' => '17:00:00'],
        ]);

        $svc   = app(OrtJadwalDokter::class);
        $kasus = $this->pasien($kode, Carbon::today()->subDays(40)->toDateString());

        $sebelum = $svc->untuk($kasus, $this->placeId);
        $svc->untukBanyak([$this->baris($kode, Carbon::today()->subDays(40)->toDateString())], $this->placeId);
        $sesudah = $svc->untuk($kasus, $this->placeId);

        $this->assertSame(
            count($sebelum['hari']),
            count($sesudah['hari']),
            'untuk() menghasilkan jumlah hari berbeda sesudah untukBanyak() dipanggil pada instance yang sama — '
            . 'pra-muat jendela 7 hari tidak dibersihkan, dan lembar reservasi kehilangan hari praktik tanpa pesan galat.'
        );
    }

    public function test_instance_yang_sama_boleh_dipakai_dua_kali(): void
    {
        $iso  = $this->isoDalamJendela();
        $kode = 'JMASN-' . Str::random(5);
        $this->dokter($kode, [['hari' => $iso, 'mulai' => '09:00:00', 'selesai' => '17:00:00']]);

        $svc = app(OrtJadwalDokter::class);
        $lv  = Carbon::today()->subDays(40)->toDateString();

        $a = $svc->untukBanyak([$this->baris($kode, $lv)], $this->placeId);
        $b = $svc->untukBanyak([$this->baris($kode, $lv)], $this->placeId);

        $this->assertSame(
            count(reset($a)['hari']),
            count(reset($b)['hari']),
            'Pemanggilan kedua memberi hasil berbeda — ada keadaan yang menetap antar pemanggilan.'
        );
    }
}
