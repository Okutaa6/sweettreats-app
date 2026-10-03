<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use Tests\TestCase;

/**
 * Tracking Ortho — badge umur kunjungan di kolom "Last Visit".
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG MELAHIRKAN BERKAS INI
 * ────────────────────────────────────────────────────────────────────────────
 * prompt_jadwal_kontrol.md (Phase 1, kolom 3) meminta badge tiga warna dengan
 * ambang TETAP: hijau <=30 hari, kuning 31–50, merah >50 — plus
 * "Batas jadwal kontrol berikutnya = last visit + 30 hari".
 *
 * Angka tetap itu TIDAK dipakai, dan keputusannya diambil sadar (Reza,
 * 3 Okt 2026): ambangnya relatif terhadap irama pasien masing-masing lewat
 * OrtCase::intervalKontrol(). Alasannya satu dan konkret — pasien yang
 * dokternya memang minta kontrol tiap 45 hari akan ditandai MERAH pada hari
 * ke-51 padahal ia justru belum jatuh tempo. Badge yang menyalakan alarm untuk
 * pasien yang tepat waktu adalah badge yang akan diabaikan orang.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG DIJAGA DI SINI
 * ────────────────────────────────────────────────────────────────────────────
 *   • ⭐ pada irama 30 hari, batasnya PERSIS 30 dan 50 seperti prompt. Ini yang
 *        membuktikan rumus relatif itu MEMPERUMUM permintaan prompt, bukan
 *        menyimpang darinya.
 *   • pasien berirama panjang tidak ditandai merah palsu
 *   • irama dijepit 21–60, dan jepitannya sama dengan OrtCase::intervalKontrol()
 *   • belum pernah berkunjung BUKAN hijau — itu bukan kabar baik
 */
class OrtoBadgeLastVisitTest extends TestCase
{
    private const HARI_INI = '2026-10-03';

    /** Tanggal kunjungan terakhir sekian hari sebelum HARI_INI. */
    private function lv(int $umurHari): string
    {
        return \Illuminate\Support\Carbon::parse(self::HARI_INI)->subDays($umurHari)->toDateString();
    }

    private function nada(int $umurHari, ?int $median): string
    {
        return OrtCase::nadaLastVisit($this->lv($umurHari), $median, self::HARI_INI);
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⭐ Permintaan prompt, kata per kata — pada irama 30 hari
    // ════════════════════════════════════════════════════════════════════════

    /** ⭐ "Hijau: kurang dari atau sama dengan 30 hari." */
    public function test_irama_tiga_puluh_hijau_sampai_hari_ke_30(): void
    {
        $this->assertSame('ok', $this->nada(0, 30), 'Baru datang hari ini.');
        $this->assertSame('ok', $this->nada(29, 30));
        $this->assertSame('ok', $this->nada(30, 30), 'Hari ke-30 masih hijau — batas prompt.');
    }

    /** ⭐ "Kuning: lebih dari 30 hari sampai 50 hari." */
    public function test_irama_tiga_puluh_kuning_31_sampai_50(): void
    {
        $this->assertSame('warn', $this->nada(31, 30), 'Hari ke-31 pindah kuning — batas prompt.');
        $this->assertSame('warn', $this->nada(40, 30));
        $this->assertSame('warn', $this->nada(50, 30), 'Hari ke-50 masih kuning — batas prompt.');
    }

    /** ⭐ "Merah: lebih dari 50 hari." */
    public function test_irama_tiga_puluh_merah_lewat_50(): void
    {
        $this->assertSame('bad', $this->nada(51, 30), 'Hari ke-51 pindah merah — batas prompt.');
        $this->assertSame('bad', $this->nada(200, 30));
    }

    /**
     * ⭐ Dan inilah alasan rumusnya relatif: pasien TANPA irama tercatat jatuh
     * ke OrtControl::USULAN_MAJU_HARI = 30, jadi ia berperilaku PERSIS seperti
     * yang diminta prompt. Angka tetapnya tidak hilang — ia jadi cadangan.
     */
    public function test_tanpa_irama_berperilaku_seperti_tiga_puluh(): void
    {
        $this->assertSame(OrtControl::USULAN_MAJU_HARI, OrtCase::intervalKontrol(null));

        foreach ([[30, 'ok'], [31, 'warn'], [50, 'warn'], [51, 'bad']] as [$umur, $harap]) {
            $this->assertSame($harap, $this->nada($umur, null), "umur {$umur} hari tanpa irama");
            $this->assertSame($harap, $this->nada($umur, 0), "umur {$umur} hari, median 0 = tak ada data");
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // Yang justru diperbaiki oleh rumus relatif
    // ════════════════════════════════════════════════════════════════════════

    /**
     * ⭐ KASUS YANG MEMBATALKAN ANGKA TETAP.
     *
     * Pasien berirama 45 hari, terakhir datang 51 hari lalu. Ambang tetap akan
     * menandainya MERAH; sebenarnya ia baru 6 hari lewat jatuh tempo — kuning.
     */
    public function test_irama_panjang_tidak_merah_palsu(): void
    {
        $this->assertSame('ok', $this->nada(45, 45), 'Tepat jatuh tempo, belum lewat.');
        $this->assertSame('warn', $this->nada(51, 45), 'Angka tetap akan bilang merah di sini.');
        $this->assertSame('warn', $this->nada(65, 45));
        $this->assertSame('bad', $this->nada(66, 45), 'Baru merah sesudah 20 hari lewat jatuh tempo.');
    }

    /**
     * Sebaliknya, pasien berirama RAPAT dinyalakan LEBIH CEPAT — dan itu benar.
     * Pasien yang biasanya datang tiap 21 hari dan sudah 30 hari tidak datang
     * sudah lewat jatuh tempo, padahal ambang tetap masih menyebutnya hijau.
     */
    public function test_irama_rapat_menyala_lebih_cepat(): void
    {
        $this->assertSame('warn', $this->nada(30, 21), 'Ambang tetap akan bilang hijau di sini.');
        $this->assertSame('ok', $this->nada(21, 21));
    }

    /** Jepitannya WAJIB sama dengan intervalKontrol() — 21 di bawah, 60 di atas. */
    public function test_jepitan_irama_sama_dengan_interval_kontrol(): void
    {
        $this->assertSame(OrtCase::IRAMA_MIN_HARI, OrtCase::intervalKontrol(7), 'Median 7 hari dijepit ke bawah.');
        $this->assertSame(OrtCase::IRAMA_MAX_HARI, OrtCase::intervalKontrol(365), 'Median 365 hari dijepit ke atas.');

        // Dan badge-nya ikut jepitan itu, bukan median mentahnya.
        $this->assertSame($this->nada(21, 7), $this->nada(21, OrtCase::IRAMA_MIN_HARI));
        $this->assertSame($this->nada(60, 365), $this->nada(60, OrtCase::IRAMA_MAX_HARI));
    }

    // ════════════════════════════════════════════════════════════════════════
    // Belum pernah berkunjung
    // ════════════════════════════════════════════════════════════════════════

    /**
     * ⭐ Bracket terpasang, nol kunjungan tercatat. Ini BUKAN hijau.
     *
     * Hijau berarti "tepat waktu", dan pasien yang belum pernah datang sama
     * sekali bukan tepat waktu — ia tidak punya dasar untuk dinilai. Memberinya
     * hijau akan menyembunyikan justru keadaan terburuk di himpunan ini.
     * OrtCase::isHilang() sudah memperlakukan NULL sebagai hilang.
     */
    public function test_belum_pernah_berkunjung_bukan_hijau(): void
    {
        $this->assertSame('faint', OrtCase::nadaLastVisit(null, 30, self::HARI_INI));
        $this->assertSame('faint', OrtCase::nadaLastVisit('', 30, self::HARI_INI));
        $this->assertNull(OrtCase::hariLewatJatuhTempo(null, 30, self::HARI_INI));
    }

    // ════════════════════════════════════════════════════════════════════════
    // Angka hari lewat
    // ════════════════════════════════════════════════════════════════════════

    public function test_hari_lewat_negatif_sebelum_jatuh_tempo(): void
    {
        $this->assertSame(-20, OrtCase::hariLewatJatuhTempo($this->lv(10), 30, self::HARI_INI));
        $this->assertSame(0, OrtCase::hariLewatJatuhTempo($this->lv(30), 30, self::HARI_INI));
        $this->assertSame(5, OrtCase::hariLewatJatuhTempo($this->lv(35), 30, self::HARI_INI));
    }

    /**
     * Ambang merah SENGAJA berbeda dari OrtControl::TERLEWAT_PARAH_HARI.
     * Jangkarnya lain — yang itu dari sch_date (tanggal yang DIJADWALKAN), yang
     * ini dari last_visit_date (kunjungan TERAKHIR). Uji ini ada supaya tidak
     * ada yang "menyatukan" keduanya dan menggeser dua layar sekaligus tanpa
     * sadar.
     */
    public function test_ambang_merah_bukan_terlewat_parah(): void
    {
        $this->assertSame(20, OrtCase::BADGE_MERAH_LEWAT);
        $this->assertSame(14, OrtControl::TERLEWAT_PARAH_HARI);
        $this->assertNotSame(
            OrtCase::BADGE_MERAH_LEWAT,
            OrtControl::TERLEWAT_PARAH_HARI,
            'Kalau keduanya disamakan, pastikan itu memang disengaja — jangkar ukurannya berbeda.'
        );
    }

    /** Gerbang H-3 milik kolom Jadwal Dokter dibaca dari satu tempat. */
    public function test_gerbang_h3_dari_konstanta(): void
    {
        $this->assertSame(3, OrtCase::HARI_PRAKTIK_MUNCUL);
    }

    /** Kamus penilaian Sales — nilainya dipakai SQL, jadi tidak boleh bergeser. */
    public function test_kamus_sales_aktif(): void
    {
        $this->assertSame(1, OrtCase::SALES_AKTIF);
        $this->assertSame(0, OrtCase::SALES_PASIF);
    }
}
