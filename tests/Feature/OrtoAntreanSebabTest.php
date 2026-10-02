<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — antrean penjadwalan menyebut sebab yang MASIH BISA terjadi.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG MELAHIRKAN BERKAS INI
 * ────────────────────────────────────────────────────────────────────────────
 * Kelompok ketiga antrean ("Baru selesai kontrol — tinggal dijadwalkan") punya
 * satu baris penjelas yang dibaca Sales saat bertanya "kenapa pasien ini tidak
 * punya tanggal". Selama berminggu-minggu ia berbunyi:
 *
 *     "sistem tidak mengusulkan tanggal: dokternya belum terjadwal,
 *      harinya penuh, atau klinik tutup"
 *
 * Ketiga sebab itu adalah pagar keras yang SUDAH DIBUANG. Tak satu pun bisa
 * terjadi lagi. Jadi satu-satunya kalimat di layar yang menjelaskan sebab,
 * menjelaskan tiga sebab yang mustahil — dan Sales yang memercayainya akan
 * mengejar perbaikan yang salah: menanyakan jadwal dokter ke operasional,
 * padahal yang terjadi pasiennya memang sudah hilang lebih dari enam bulan.
 *
 * Ketahuannya bukan dari uji maupun dari layar, melainkan dari audit kepatuhan
 * terhadap handoff — artinya ia bisa saja bertahan sampai prod.
 *
 * ⚠ Teks yang menjelaskan SEBAB adalah teks yang paling cepat basi, karena ia
 *   menggambarkan perilaku kode alih-alih keadaan data. Berkas ini menahannya
 *   dengan dua sisi: sebab yang benar harus disebut, dan sebab yang sudah
 *   mustahil tidak boleh disebut lagi.
 */
class OrtoAntreanSebabTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-SEBAB';

    private string $placeId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_case')) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }

        $this->placeId = (string) array_key_first($peta);
    }

    private function pasien(int $kunjunganTerakhirLalu): OrtCase
    {
        $pid = self::TANDA . '-' . Str::random(6);

        return OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji Sebab ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => Carbon::today()->subDays(500)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF,
            'last_visit_date' => Carbon::today()->subDays($kunjunganTerakhirLalu)->toDateString(),
            'control_count' => 1, 'visit_count' => 1, 'visit_count_180d' => 1,
        ]);
    }

    private function kelompok(array $kasus): array
    {
        return app(OrtQuery::class)->kelompokkanAntrean(collect($kasus));
    }

    // ────────────────────────────────────────────────────────────────────────

    /**
     * ⭐ INTI BERKAS INI: tiga sebab yang sudah mustahil tidak boleh disebut.
     */
    public function test_hint_tidak_menyebut_pagar_yang_sudah_dibuang(): void
    {
        $k = $this->kelompok([$this->pasien(10)]);

        $semuaHint = implode(' ', array_column($k, 'hint'));

        foreach (['klinik tutup', 'harinya penuh', 'belum terjadwal', 'tidak praktik'] as $basi) {
            $this->assertStringNotContainsStringIgnoringCase(
                $basi, $semuaHint,
                "Pagar '{$basi}' sudah dibuang — menyebutnya mengirim Sales mengejar perbaikan yang salah."
            );
        }
    }

    /** Sebab yang MASIH nyata tetap punya tempat: pasien hilang. */
    public function test_kelompok_hilang_menyebut_ambang_yang_benar(): void
    {
        $k = $this->kelompok([$this->pasien(OrtCase::HILANG_HARI + 30)]);

        $this->assertCount(1, $k['hilang']['baris'], 'Pasien hilang > 180 hari masuk kelompok hilang.');
        $this->assertStringContainsString(
            (string) OrtCase::HILANG_HARI, $k['hilang']['hint'],
            'Ambangnya disebut angka, bukan "lama" — supaya bisa diperiksa.'
        );
    }

    /** Tiga kelompok itu saling lepas: satu pasien tepat di satu tempat. */
    public function test_tiga_kelompok_saling_lepas(): void
    {
        $kasus = [
            $this->pasien(OrtCase::HILANG_HARI + 30),   // hilang
            $this->pasien(10),                           // baru selesai kontrol
            $this->pasien(OrtCase::TARGET_GAP + 20),     // jarang
        ];

        $k = $this->kelompok($kasus);
        $total = count($k['hilang']['baris']) + count($k['jarang']['baris']) + count($k['baru']['baris']);

        $this->assertSame(3, $total, 'Tiap pasien mendarat di tepat satu kelompok.');
        $this->assertCount(1, $k['hilang']['baris']);
        $this->assertCount(1, $k['baru']['baris']);
        $this->assertCount(1, $k['jarang']['baris']);
    }

    /** Tiap kelompok punya label dan hint — kelompok tanpa penjelasan tidak menolong. */
    public function test_tiap_kelompok_punya_label_dan_hint(): void
    {
        $k = $this->kelompok([$this->pasien(10)]);

        foreach (['hilang', 'jarang', 'baru'] as $kunci) {
            $this->assertArrayHasKey($kunci, $k);
            $this->assertNotEmpty($k[$kunci]['label'], "Kelompok {$kunci} tanpa label.");
            $this->assertNotEmpty($k[$kunci]['hint'], "Kelompok {$kunci} tanpa penjelasan.");
        }
    }
}
