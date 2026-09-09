<?php

namespace Tests\Unit;

use App\Http\Controllers\Concerns\ResolvesDoctorAvailability;
use PHPUnit\Framework\TestCase;

/**
 * Penggabungan rentang jam praktik yang bertindih — bagian MURNI (tanpa DB).
 *
 * Cacat aslinya: computeTimeSlots() mencetak deret slot PER rentang lalu
 * menempelkannya, jadi dua rentang yang bertindih membuat jam yang sama muncul
 * dua kali di layar pemesanan. Terlihat di prod 10 Sep 2026 (Dharma, Cinere)
 * dan diam-diam berulang tiap Sabtu (Panji, Bintaro).
 */
class SlotRentangBertindihTest extends TestCase
{
    /** Pembungkus tipis supaya method protected bisa diuji langsung. */
    private function gabung(array $ranges): array
    {
        $o = new class {
            use ResolvesDoctorAvailability;

            public function jalan(array $r): array
            {
                return $this->gabungRentangBertindih($r);
            }
        };

        return $o->jalan($ranges);
    }

    private function r(string $s, string $e): array
    {
        return ['start_hour' => $s, 'end_hour' => $e];
    }

    /** Kasus yang memicu laporan: 'add' seluruhnya termuat di jadwal rutin. */
    public function test_add_di_dalam_jadwal_rutin_jadi_satu(): void
    {
        $hasil = $this->gabung([
            $this->r('17:00:00', '20:00:00'),   // rutin Kamis Cinere
            $this->r('17:00:00', '18:30:00'),   // Hari Tambahan 10 Sep
        ]);

        $this->assertCount(1, $hasil, '9 kotak untuk 6 jam adalah cacatnya');
        $this->assertSame('17:00:00', $hasil[0]['start_hour']);
        $this->assertSame('20:00:00', $hasil[0]['end_hour'], 'jam 18:30-20:00 tidak boleh ikut hilang');
    }

    /** Dua baris jadwal RUTIN yang tumpang tindih — Panji, Sabtu, Bintaro. */
    public function test_dua_baris_rutin_bertindih_jadi_satu(): void
    {
        $hasil = $this->gabung([
            $this->r('10:00:00', '20:00:00'),
            $this->r('15:00:00', '20:00:00'),
        ]);

        $this->assertCount(1, $hasil);
        $this->assertSame(['start_hour' => '10:00:00', 'end_hour' => '20:00:00'], $hasil[0]);
    }

    /** 'add' yang MEMPERPANJANG harus melebarkan rentang, bukan sekadar hilang. */
    public function test_add_memperpanjang_melebarkan_rentang(): void
    {
        $hasil = $this->gabung([
            $this->r('17:00:00', '20:00:00'),
            $this->r('19:00:00', '21:00:00'),
        ]);

        $this->assertCount(1, $hasil);
        $this->assertSame('21:00:00', $hasil[0]['end_hour'], 'jam tambahan 20:00-21:00 wajib terbuka');
    }

    /** Rentang bersarang penuh tidak boleh memendekkan induknya. */
    public function test_rentang_bersarang_tidak_memendekkan(): void
    {
        $hasil = $this->gabung([
            $this->r('17:00:00', '20:00:00'),
            $this->r('18:00:00', '19:00:00'),
        ]);

        $this->assertSame([['start_hour' => '17:00:00', 'end_hour' => '20:00:00']], $hasil);
    }

    /** Keputusan sadar: yang cuma BERSENTUHAN tetap dua sesi terpisah. */
    public function test_bersentuhan_TIDAK_digabung(): void
    {
        $hasil = $this->gabung([
            $this->r('10:00:00', '12:00:00'),
            $this->r('12:00:00', '15:00:00'),
        ]);

        $this->assertCount(2, $hasil, 'menggabung yang bersentuhan menggeser titik grid slot');
    }

    /** Sesi pagi & sore yang benar-benar terpisah tak boleh disatukan. */
    public function test_sesi_terpisah_tetap_dua(): void
    {
        $hasil = $this->gabung([
            $this->r('10:00:00', '12:00:00'),
            $this->r('17:00:00', '20:00:00'),
        ]);

        $this->assertCount(2, $hasil);
        $this->assertSame('10:00:00', $hasil[0]['start_hour'], 'hasil wajib urut menaik');
        $this->assertSame('17:00:00', $hasil[1]['start_hour']);
    }

    /** Urutan masuk tidak boleh mengubah hasil (jadwal + exception tak terurut). */
    public function test_urutan_masuk_tidak_berpengaruh(): void
    {
        $acak = $this->gabung([
            $this->r('19:00:00', '21:00:00'),
            $this->r('10:00:00', '12:00:00'),
            $this->r('17:00:00', '20:00:00'),
        ]);

        $this->assertCount(2, $acak);
        $this->assertSame(['start_hour' => '10:00:00', 'end_hour' => '12:00:00'], $acak[0]);
        $this->assertSame(['start_hour' => '17:00:00', 'end_hour' => '21:00:00'], $acak[1]);
    }

    /** Tiga rentang beruntun harus runtuh jadi satu (rantai, bukan pasangan). */
    public function test_rantai_tiga_rentang_runtuh_jadi_satu(): void
    {
        $hasil = $this->gabung([
            $this->r('10:00:00', '13:00:00'),
            $this->r('12:00:00', '16:00:00'),
            $this->r('15:00:00', '18:00:00'),
        ]);

        $this->assertSame([['start_hour' => '10:00:00', 'end_hour' => '18:00:00']], $hasil);
    }

    /**
     * Rentang nol/terbalik TIDAK boleh dibuang. AttendanceReconciler menilai
     * "dokter ini dijadwalkan praktik hari ini?" dari koleksi rentang KOSONG
     * atau tidak, jadi membuangnya diam-diam menggeser hasil absensi dokter.
     * Prod memang punya satu baris begini (Minggu 22:00-20:00).
     */
    public function test_rentang_nol_tidak_dibuang_agar_absensi_tak_bergeser(): void
    {
        $this->assertCount(
            1,
            $this->gabung([$this->r('22:00:00', '20:00:00')]),
            'dibuang = dokternya mendadak dianggap tidak dijadwalkan'
        );
    }

    /** Tapi ia juga tak boleh menyerap atau memanjangkan rentang yang sah. */
    public function test_rentang_terbalik_tidak_merusak_tetangganya(): void
    {
        $hasil = $this->gabung([
            $this->r('17:00:00', '20:00:00'),
            $this->r('18:00:00', '18:00:00'),   // nol, di dalam rentang sah
            $this->r('22:00:00', '21:00:00'),   // terbalik
        ]);

        $sah = array_values(array_filter($hasil, fn ($r) => $r['end_hour'] > $r['start_hour']));
        $this->assertSame(
            [['start_hour' => '17:00:00', 'end_hour' => '20:00:00']],
            $sah,
            'jam praktik yang sah wajib utuh — tak memanjang ke 21:00/22:00'
        );
    }

    /**
     * 'add' yang melintasi jam istirahat menutup istirahat itu.
     *
     * Ini BUKAN regresi dari penggabungan: rentang 'add' memang tak pernah
     * dipotong istirahat, jadi menit-menit itu sudah bisa dipesan sejak dulu
     * lewat deret slot 'add' sendiri. Yang hilang hanya kotak kembarnya.
     * Diuji supaya perilaku ini tercatat, bukan ditemukan orang lain nanti.
     */
    public function test_add_melintasi_istirahat_menutup_istirahatnya(): void
    {
        $hasil = $this->gabung([
            $this->r('10:00:00', '12:00:00'),   // sebelum istirahat
            $this->r('13:00:00', '20:00:00'),   // sesudah istirahat
            $this->r('11:00:00', '14:00:00'),   // Hari Tambahan melintasi 12-13
        ]);

        $this->assertSame([['start_hour' => '10:00:00', 'end_hour' => '20:00:00']], $hasil);
    }

    /** Format H:i dan H:i:s bercampur (exception vs jadwal) tetap sepadan. */
    public function test_format_jam_campur_tetap_sepadan(): void
    {
        $hasil = $this->gabung([
            $this->r('17:00', '20:00'),
            $this->r('17:00:00', '18:30:00'),
        ]);

        $this->assertSame([['start_hour' => '17:00:00', 'end_hour' => '20:00:00']], $hasil);
    }

    /** Satu rentang tunggal lewat apa adanya (jalur paling umum, jangan rusak). */
    public function test_satu_rentang_lewat_apa_adanya(): void
    {
        $this->assertSame(
            [['start_hour' => '17:00:00', 'end_hour' => '20:00:00']],
            $this->gabung([$this->r('17:00:00', '20:00:00')])
        );
    }

    /** Nol rentang (dokter tak praktik) tetap nol — bukan galat. */
    public function test_kosong_tetap_kosong(): void
    {
        $this->assertSame([], $this->gabung([]));
    }
}
