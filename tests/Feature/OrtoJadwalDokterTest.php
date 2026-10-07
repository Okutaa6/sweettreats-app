<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtJadwalDokter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — menjadwalkan BUKAN dengan menebak tanggal.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG MELAHIRKAN BERKAS INI
 * ────────────────────────────────────────────────────────────────────────────
 * Lembar "Buat Reservasi" dulu menanyakan satu kotak tanggal kosong, lalu
 * mengoper apa pun yang diketik ke modul Reservasi — di mana Sales ditanyai
 * tanggal lagi, kali ini dengan jadwal dokter yang sebenarnya. Dua kali
 * ditanya, dan yang pertama tanpa tahu apa-apa.
 *
 * Akibatnya Sales menelepon pasien sambil menebak: "Rabu bisa, Bu?" tanpa tahu
 * dokternya praktik hari apa, jam berapa, dan sudah berapa penuh.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG DIJAGA DI SINI
 * ────────────────────────────────────────────────────────────────────────────
 *   • hari yang ditawarkan HANYA hari praktik dokter pasien itu
 *   • jamnya datang dari jadwal sungguhan, dan istirahat ikut dipotong
 *   • "penuh apa belum" dihitung dari reservasi yang benar-benar ada
 *   • ⭐ dan yang paling menentukan bentuk layarnya: DOKTER YANG TIDAK
 *     TERPETAKAN BUKAN KASUS PINGGIRAN. Diukur di DB pengembangan, dari 42
 *     kasus hanya 12 yang bisa dipetakan lewat omnicare_doc_id dan 13 tidak
 *     sama sekali. Jadi lembarnya wajib tetap berguna tanpa dokter, dan
 *     alasannya dikatakan apa adanya — bukan daftar kosong tanpa penjelasan.
 *
 * ⚠ Angka penuh sengaja diambil dari mesin slot modul Reservasi (trait
 *   ResolvesDoctorAvailability), bukan dihitung rumus sendiri. Repo ini sudah
 *   punya TIGA rumus kapasitas yang saling berbeda; yang keempat akan membuat
 *   angka di layar ortho berbeda dari angka di layar Reservasi untuk hari yang
 *   sama, dan Sales melihat keduanya.
 */
class OrtoJadwalDokterTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-JDOK';

    private string $placeId;
    private string $branchId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ort_case', 'raw_doctor', 'raw_doctor_schedule', 'raw_cus_reservation'] as $t) {
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

        /* ⭐ JAM DIBEKUKAN, dan ini bukan kerapian — kelas ini sebelumnya
           PECAH tiap hari Rabu.

           Dokter uji di sini praktik hari Rabu saja, dan hariBerikutnya(3)
           mencari Rabu terdekat MULAI BESOK. Jendela yang dipakai layanannya
           dimulai HARI INI (OrtJadwalDokter::JENDELA_TABEL_HARI — Sales
           menelepon hari ini dan memesan dari hari ini). Jadi begitu hari
           berjalan jatuh di hari Rabu, penolong uji menunjuk Rabu minggu depan
           sementara $hari[0] menunjuk hari ini: dua tanggal berbeda untuk hal
           yang sama, dan dua uji merah.

           Lolos selama berbulan-bulan hanya karena belum pernah dijalankan
           pada hari Rabu — persis jenis uji yang hijau sampai ia tiba-tiba
           tidak, di hari yang tidak ada hubungannya dengan kode yang diubah.
           (Kejadian 7 Okt 2026, saat yang dikerjakan justru berkas JS.)

           Senin dipilih supaya jendela 7 harinya memuat TEPAT SATU hari Rabu,
           dan Rabu itu di masa depan — kalau hari ini sendiri yang dipakai,
           slot yang jamnya sudah lewat ikut terpotong dan jumlah "terisi"
           berubah-ubah mengikuti jam berapa suite dijalankan. */
        Carbon::setTestNow(Carbon::parse('2026-10-05 09:00:00'));   // Senin
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    /**
     * Satu dokter dengan jadwal persis seperti yang diminta.
     *
     * @param  list<array{hari:int, mulai:string, selesai:string, type?:string}>  $jadwal
     * @return string  raw_doctor.id
     */
    private function dokter(string $kode, array $jadwal): string
    {
        $id = 'uji-jdok-' . Str::random(8);
        DB::table('raw_doctor')->insert([
            'id' => $id, 'name' => 'drg. Uji ' . $kode,
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

    private function pasien(?string $kodeDokter): OrtCase
    {
        $pid = self::TANDA . '-' . Str::random(6);

        return OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji JDok ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => Carbon::today()->subDays(300)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF, 'doctor_ref_id' => $kodeDokter,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);
    }

    /**
     * Tanggal terdekat (mulai BESOK) yang jatuh di hari ISO tertentu.
     *
     * ⚠ "Mulai besok" sengaja berbeda dari jendela layanannya, yang mulai hari
     *   ini. Perbedaan itu aman HANYA karena jam dibekukan di setUp() ke hari
     *   Senin; tanpa itu, dijalankan di hari yang sama dengan hari praktik
     *   dokter uji, penolong ini menunjuk minggu depan.
     */
    private function hariBerikutnya(int $iso): Carbon
    {
        $t = Carbon::today()->addDay();
        while ($t->dayOfWeekIso !== $iso) {
            $t->addDay();
        }

        return $t;
    }

    private function reservasi(string $doctorId, string $tanggal, string $mulai, string $selesai, ?string $status = 'confirmed'): void
    {
        DB::table('raw_cus_reservation')->insert([
            'id' => 'uji-jdok-rsv-' . Str::random(10),
            'raw_branch_id' => $this->branchId, 'raw_doctor_id' => $doctorId,
            'date' => $tanggal, 'start_hour' => $mulai, 'end_hour' => $selesai,
            'status' => $status, 'promo_klaim' => 0,
        ]);
    }

    private function hasil(OrtCase $kasus): array
    {
        return app(OrtJadwalDokter::class)->untuk($kasus, $this->placeId);
    }

    // ── Hari yang ditawarkan ────────────────────────────────────────────────

    /** ⭐ Meniru drg. Andy: satu hari praktik saja, Rabu. */
    public function test_hanya_hari_praktik_dokter_yang_ditawarkan(): void
    {
        $this->dokter(self::TANDA . '-RAB', [['hari' => 3, 'mulai' => '15:00:00', 'selesai' => '22:00:00']]);
        $r = $this->hasil($this->pasien(self::TANDA . '-RAB'));

        $this->assertNull($r['sebab']);
        $this->assertNotEmpty($r['hari']);

        foreach ($r['hari'] as $h) {
            $this->assertSame(
                3, Carbon::parse($h['tanggal'])->dayOfWeekIso,
                "{$h['tanggal']} bukan hari praktik dokter ini."
            );
        }
    }

    /** Jamnya datang dari jadwal sungguhan, bukan angka baku. */
    public function test_jam_diambil_dari_jadwal_dokter(): void
    {
        $this->dokter(self::TANDA . '-JAM', [['hari' => 3, 'mulai' => '15:00:00', 'selesai' => '22:00:00']]);
        $r = $this->hasil($this->pasien(self::TANDA . '-JAM'));

        $this->assertStringStartsWith('15:00', $r['hari'][0]['jam']);
        $this->assertStringEndsWith('22:00', $r['hari'][0]['jam']);
    }

    /** ⭐ Istirahat ikut dipotong — itu sebabnya mesin Reservasi dipakai ulang. */
    public function test_jam_istirahat_mengurangi_slot(): void
    {
        $kodeUtuh = self::TANDA . '-UTUH';
        $kodeIsti = self::TANDA . '-ISTI';

        $this->dokter($kodeUtuh, [['hari' => 3, 'mulai' => '10:00:00', 'selesai' => '18:00:00']]);
        $this->dokter($kodeIsti, [
            ['hari' => 3, 'mulai' => '10:00:00', 'selesai' => '18:00:00'],
            ['hari' => 3, 'mulai' => '12:00:00', 'selesai' => '14:00:00', 'type' => 'break'],
        ]);

        $utuh = $this->hasil($this->pasien($kodeUtuh))['hari'][0]['total'];
        $isti = $this->hasil($this->pasien($kodeIsti))['hari'][0]['total'];

        $this->assertGreaterThan(
            $isti, $utuh,
            'Dokter dengan istirahat 2 jam harus punya slot LEBIH SEDIKIT.'
        );
    }

    // ── Penuh apa belum ─────────────────────────────────────────────────────

    /** Reservasi yang benar-benar ada mengurangi slot kosong. */
    public function test_reservasi_mengurangi_slot_kosong(): void
    {
        $kode = self::TANDA . '-ISI';
        $doc  = $this->dokter($kode, [['hari' => 3, 'mulai' => '10:00:00', 'selesai' => '12:00:00']]);
        $rabu = $this->hariBerikutnya(3)->toDateString();

        $sebelum = $this->hasil($this->pasien($kode))['hari'][0]['terisi'];
        $this->reservasi($doc, $rabu, '10:00:00', '10:30:00');
        $sesudah = $this->hasil($this->pasien($kode))['hari'][0];

        $this->assertGreaterThan($sebelum, $sesudah['terisi'], 'Slot terisi harus bertambah.');
        $this->assertSame($rabu, $sesudah['tanggal'], 'Prasyarat: yang diperiksa memang hari itu.');
    }

    /** ⭐ Hari yang habis ditandai PENUH, dan tetap ditampilkan. */
    public function test_hari_penuh_ditandai_bukan_disembunyikan(): void
    {
        $kode = self::TANDA . '-PNH';
        $doc  = $this->dokter($kode, [['hari' => 3, 'mulai' => '10:00:00', 'selesai' => '11:00:00']]);
        $rabu = $this->hariBerikutnya(3)->toDateString();

        $this->reservasi($doc, $rabu, '10:00:00', '11:00:00');
        $hari = $this->hasil($this->pasien($kode))['hari'];

        $this->assertSame($rabu, $hari[0]['tanggal'], 'Hari penuh tetap muncul di daftar.');
        $this->assertTrue($hari[0]['penuh'], 'Dan ditandai penuh.');
    }

    /** Reservasi BATAL tidak menahan slot — aturan modul Reservasi, dipakai ulang. */
    public function test_reservasi_batal_tidak_menahan_slot(): void
    {
        $kode = self::TANDA . '-BTL';
        $doc  = $this->dokter($kode, [['hari' => 3, 'mulai' => '10:00:00', 'selesai' => '11:00:00']]);
        $rabu = $this->hariBerikutnya(3)->toDateString();

        $this->reservasi($doc, $rabu, '10:00:00', '11:00:00', 'cancelled');

        $this->assertFalse(
            $this->hasil($this->pasien($kode))['hari'][0]['penuh'],
            'Reservasi yang dibatalkan membebaskan jamnya kembali.'
        );
    }

    // ── Dokter tidak terpetakan: BUKAN kasus pinggiran ──────────────────────

    /** ⭐ Pasien tanpa kode dokter sama sekali. */
    public function test_tanpa_kode_dokter_memberi_sebab_yang_bisa_dibaca(): void
    {
        $r = $this->hasil($this->pasien(null));

        $this->assertSame('tanpa_dokter', $r['sebab']);
        $this->assertSame([], $r['hari']);
        $this->assertNotEmpty(app(OrtJadwalDokter::class)->penjelasan($r['sebab']));
    }

    /** ⭐ Kodenya ada tapi tidak ketemu di raw_doctor — 31% kasus di dev. */
    public function test_kode_dokter_tak_terpetakan_memberi_sebab(): void
    {
        $r = $this->hasil($this->pasien('KODE-TIDAK-ADA'));

        $this->assertSame('tak_terpetakan', $r['sebab']);
        $this->assertSame([], $r['hari']);
    }

    /** Dokternya ada, tapi tidak punya jadwal di cabang ini. */
    public function test_dokter_tanpa_jadwal_di_cabang_ini(): void
    {
        $kode = self::TANDA . '-NOJD';
        $this->dokter($kode, []);        // terdaftar, nol baris jadwal

        $r = $this->hasil($this->pasien($kode));

        $this->assertSame('tak_praktik', $r['sebab']);
        $this->assertSame([], $r['hari']);
    }

    /**
     * KENDALI ATAS PREMIS, bukan atas perilaku.
     *
     * Saya sempat menulis kode pembelaan untuk "satu kode dokter menunjuk dua
     * baris raw_doctor", dan uji ini yang membongkarnya: `omnicare_doc_id`
     * punya index UNIQUE, jadi keadaan itu MUSTAHIL. Kembar nama memang nyata
     * di dev (drg. Nugroho dan drg. Putri Moortiningsih masing-masing dua
     * baris), tapi kembarannya ber-omnicare_doc_id NULL -- tak pernah
     * terjaring lewat kode.
     *
     * Kalau index itu suatu hari dilepas, uji ini merah dan memberi tahu bahwa
     * pencarian `->first()` di OrtJadwalDokter perlu ditinjau ulang.
     */
    public function test_satu_kode_dokter_tidak_mungkin_menunjuk_dua_baris(): void
    {
        $kode = self::TANDA . '-UNIK';
        $this->dokter($kode, [['hari' => 3, 'mulai' => '10:00:00', 'selesai' => '18:00:00']]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->dokter($kode, []);
    }

    /** Kembar NAMA tidak mengganggu: yang dicari kode, bukan nama. */
    public function test_kembar_nama_tidak_mengganggu_pencarian(): void
    {
        $kode = self::TANDA . '-NAMA';
        $this->dokter($kode, [['hari' => 3, 'mulai' => '10:00:00', 'selesai' => '18:00:00']]);

        // Kembaran bernama sama persis, tanpa kode -- persis pola di dev.
        DB::table('raw_doctor')->insert([
            'id' => 'uji-jdok-' . Str::random(8), 'name' => 'drg. Uji ' . $kode,
            'omnicare_doc_id' => null, 'is_active' => 1,
        ]);

        $r = $this->hasil($this->pasien($kode));

        $this->assertNull($r['sebab'], 'Kembar tanpa kode tidak boleh ikut terjaring.');
        $this->assertNotEmpty($r['hari']);
    }

    // ── Bentuk keluarannya ──────────────────────────────────────────────────

    public function test_jumlah_hari_dibatasi(): void
    {
        $kode = self::TANDA . '-BYK';
        $this->dokter($kode, array_map(
            fn ($h) => ['hari' => $h, 'mulai' => '10:00:00', 'selesai' => '18:00:00'],
            [1, 2, 3, 4, 5, 6, 7]
        ));

        $this->assertLessThanOrEqual(
            6, count($this->hasil($this->pasien($kode))['hari']),
            'Menghitung jam untuk puluhan tanggal berarti puluhan putaran kueri untuk yang tak akan dilihat.'
        );
    }

    public function test_setiap_hari_membawa_tanggal_jam_dan_isi(): void
    {
        $kode = self::TANDA . '-BTK';
        $this->dokter($kode, [['hari' => 3, 'mulai' => '10:00:00', 'selesai' => '18:00:00']]);

        $h = $this->hasil($this->pasien($kode))['hari'][0];

        foreach (['tanggal', 'label', 'jam', 'terisi', 'total', 'penuh'] as $kunci) {
            $this->assertArrayHasKey($kunci, $h);
        }
    }
}
