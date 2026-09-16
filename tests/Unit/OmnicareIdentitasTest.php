<?php

namespace Tests\Unit;

use App\Console\Commands\OmnicareReconcile;
use App\Support\OmnicareIdentitas as Ident;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Uji keputusan paling berbahaya di integrasi Omnicare: "dua rekam ini orang
 * yang sama?" — plus "reservasi ini dibatalkan?".
 *
 * Sengaja TANPA database. Keputusan ini menentukan siapa berbagi rekam medis
 * dengan siapa; ia tidak boleh hanya teruji lewat perintah artisan yang butuh
 * 16.500 baris untuk dijalankan.
 *
 *   vendor/bin/phpunit tests/Unit/OmnicareIdentitasTest.php
 */
class OmnicareIdentitasTest extends TestCase
{
    private function orang(?string $nama, ?string $dob): array
    {
        return ['tokens' => Ident::tokenSet($nama), 'dob' => Ident::validDob($dob)];
    }

    // ── YANG HARUS DIANGGAP SATU ORANG ──────────────────────────────────────

    /** Kasus inti: satu orang, dua pid cabang berbeda. ISMU RAHARJO nyata di prod. */
    public function test_nama_dan_dob_sama_adalah_orang_yang_sama(): void
    {
        $this->assertTrue(Ident::samaOrang(
            $this->orang('ISMU RAHARJO', '1975-03-04'),   // B-2007-2758
            $this->orang('ISMU RAHARJO', '1975-03-04')    // B-2008-2793
        ));
    }

    /** 54,5% tautan lama dibuat tanpa DOB — satu sisi kosong tidak boleh memblokir. */
    public function test_dob_kosong_di_satu_sisi_tidak_dianggap_bertentangan(): void
    {
        $this->assertTrue(Ident::samaOrang(
            $this->orang('ISMU RAHARJO', '1975-03-04'),
            $this->orang('ISMU RAHARJO', null)
        ));
        $this->assertTrue(Ident::samaOrang(
            $this->orang('ISMU RAHARJO', null),
            $this->orang('ISMU RAHARJO', null)
        ));
    }

    /** Urutan kata berbeda bukan orang berbeda. */
    public function test_urutan_nama_berbeda_tetap_orang_yang_sama(): void
    {
        $this->assertTrue(Ident::samaOrang(
            $this->orang('RAHARJO ISMU', '1975-03-04'),
            $this->orang('ISMU RAHARJO', '1975-03-04')
        ));
    }

    /** ~32% omnicare_patient punya first_name == last_name → nama tertulis dua kali. */
    public function test_nama_ganda_omnicare_diruntuhkan(): void
    {
        $this->assertSame('nazwa sabrina', Ident::normName('Nazwa Sabrina Nazwa Sabrina'));
        $this->assertTrue(Ident::samaOrang(
            $this->orang('Nazwa Sabrina Nazwa Sabrina', '2001-01-02'),
            $this->orang('NAZWA SABRINA', '2001-01-02')
        ));
    }

    /** Tanda baca & spasi ganda tidak boleh memisahkan orang yang sama. */
    public function test_tanda_baca_diabaikan(): void
    {
        $this->assertTrue(Ident::samaOrang(
            $this->orang("SITI  AMINAH", '1990-05-05'),
            $this->orang("Siti, Aminah.", '1990-05-05')
        ));
    }

    // ── YANG TIDAK BOLEH DIANGGAP SATU ORANG ────────────────────────────────

    /**
     * ⛔ PAGAR UTAMA. 682 kontak punya 2–7 pasien di satu nomor — ibu & anak.
     * Nama mirip TAPI tidak identik harus GAGAL, walaupun skor penjodohan
     * memberi 25 untuk subset dan itu cukup untuk menaut salah satunya.
     */
    public function test_nama_subset_bukan_orang_yang_sama(): void
    {
        $this->assertFalse(Ident::samaOrang(
            $this->orang('SITI AMINAH', null),
            $this->orang('SITI AMINAH PUTRI', null)
        ));
    }

    /** ⛔ Nama sama persis tapi tanggal lahir berbeda = dua orang. Anak senama. */
    public function test_dob_bertentangan_bukan_orang_yang_sama(): void
    {
        $this->assertFalse(Ident::samaOrang(
            $this->orang('SITI AMINAH', '1990-05-05'),
            $this->orang('SITI AMINAH', '2015-05-05')
        ));
    }

    /** Tanpa nama tidak ada dasar menyatakan "orang yang sama". */
    public function test_nama_kosong_selalu_ditolak(): void
    {
        $this->assertFalse(Ident::samaOrang($this->orang('', '1990-05-05'), $this->orang('', '1990-05-05')));
        $this->assertFalse(Ident::samaOrang($this->orang(null, null), $this->orang('SITI AMINAH', null)));
    }

    /** Nama sekadar mirip (fuzzy ≥82% cukup untuk skor 15) tetap bukan satu orang. */
    public function test_nama_mirip_tapi_beda_ditolak(): void
    {
        $this->assertFalse(Ident::samaOrang(
            $this->orang('MUHAMAD RIZKI', null),
            $this->orang('MUHAMMAD RIZKI', null)
        ));
    }

    // ── DOB ─────────────────────────────────────────────────────────────────

    public function test_dob_sampah_dibuang(): void
    {
        $this->assertNull(Ident::validDob('0000-00-00'));
        $this->assertNull(Ident::validDob(null));
        $this->assertNull(Ident::validDob(''));
        $this->assertNull(Ident::validDob('1800-01-01'));            // terlalu tua
        $this->assertNull(Ident::validDob((date('Y') + 1) . '-01-01')); // masa depan
        $this->assertSame('1975-03-04', Ident::validDob('1975-03-04 00:00:00'));
    }

    // ── TRIASE SELISIH DOB ──────────────────────────────────────────────────

    /**
     * Diukur di data nyata: dari 16 tautan yang DOB-nya berbeda, 2 ternyata
     * hari↔bulan tertukar (`1963-01-06` vs `1963-06-01`) dan 3 beda satu hari.
     * Menumpuk semuanya sebagai "bertentangan" melebih-lebihkan masalahnya.
     */
    public function test_hari_bulan_tertukar_dikenali(): void
    {
        $this->assertSame('tertukar', Ident::bedaDob('1963-01-06', '1963-06-01'));
        $this->assertSame('tertukar', Ident::bedaDob('2005-03-08', '2005-08-03'));
    }

    public function test_selisih_sehari_dikenali(): void
    {
        $this->assertSame('selisih_sehari', Ident::bedaDob('2004-03-18', '2004-03-17'));
        $this->assertSame('selisih_sehari', Ident::bedaDob('1988-10-02', '1988-10-03'));
    }

    /** ⛔ Selisih besar tetap harus disebut BERTENTANGAN — itu calon salah orang. */
    public function test_selisih_besar_tetap_bertentangan(): void
    {
        $this->assertSame('bertentangan', Ident::bedaDob('2013-12-25', '1981-06-04'));
        $this->assertSame('bertentangan', Ident::bedaDob('1990-03-24', '1999-03-24'));
        // Beda 2 hari sudah bukan "selisih sehari".
        $this->assertSame('bertentangan', Ident::bedaDob('2004-03-18', '2004-03-20'));
    }

    public function test_dob_sama_atau_kosong_bukan_selisih(): void
    {
        $this->assertSame('sama', Ident::bedaDob('1990-05-05', '1990-05-05'));
        $this->assertSame('sama', Ident::bedaDob(null, '1990-05-05'));
        $this->assertSame('sama', Ident::bedaDob('1990-05-05', '0000-00-00'));
    }

    // ── DOB BERCABANG ───────────────────────────────────────────────────────

    /**
     * ⛔ Rekam tanpa DOB menjadi JEMBATAN antara dua orang senama: A↔B benar,
     * B↔C benar, tapi A↔C salah. Pemeriksaan berpasangan tak pernah
     * menangkapnya — kelompoknya yang harus diperiksa.
     */
    public function test_dob_bercabang_menangkap_jembatan_tanpa_dob(): void
    {
        $kumpulan = [
            $this->orang('AYU LESTARI', null),
            $this->orang('AYU LESTARI', '1990-01-01'),
            $this->orang('AYU LESTARI', '2015-01-01'),
        ];
        $this->assertTrue(Ident::dobBercabang($kumpulan));

        // Pasangan mana pun terlihat baik-baik saja — inilah sebabnya
        // pemeriksaan berpasangan tidak cukup.
        $this->assertTrue(Ident::samaOrang($kumpulan[0], $kumpulan[1]));
        $this->assertTrue(Ident::samaOrang($kumpulan[0], $kumpulan[2]));
    }

    public function test_dob_tunggal_atau_kosong_tidak_bercabang(): void
    {
        $this->assertFalse(Ident::dobBercabang([
            $this->orang('A B', null), $this->orang('A B', '1990-01-01'),
        ]));
        $this->assertFalse(Ident::dobBercabang([
            $this->orang('A B', null), $this->orang('A B', null),
        ]));
        $this->assertFalse(Ident::dobBercabang([]));
    }

    public function test_kekuatan_membedakan_dob_dan_nama_saja(): void
    {
        $this->assertSame('dob', Ident::kekuatan(
            $this->orang('A B', '1990-01-01'), $this->orang('A B', '1990-01-01')
        ));
        $this->assertSame('nama', Ident::kekuatan(
            $this->orang('A B', '1990-01-01'), $this->orang('A B', null)
        ));
    }

    // ── PEMBATALAN BUKAN KETIDAKHADIRAN (Langkah 3a) ────────────────────────

    /**
     * 385 reservasi `cancelled` ikut divonis orphan_noshow, menaikkan angka
     * no-show dari 18,2% ke 26,8%. Uji ini menjaga pembedanya.
     */
    public function test_status_batal_dikenali(): void
    {
        $m = new ReflectionMethod(OmnicareReconcile::class, 'dibatalkan');
        $m->setAccessible(true);
        $cmd = new OmnicareReconcile();

        foreach (['cancelled', 'CANCELLED', ' cancelled ', 'canceled', 'batal'] as $s) {
            $this->assertTrue($m->invoke($cmd, $s), "status '{$s}' harus dianggap dibatalkan");
        }
        foreach (['pending', 'confirmed', 'completed', 'no_show', '', null] as $s) {
            $this->assertFalse($m->invoke($cmd, $s), "status '{$s}' TIDAK boleh dianggap dibatalkan");
        }
    }

    /**
     * ⛔ Pagar rerun: hanya dua vonis yang boleh dinilai ulang. Kalau daftar
     * ini pernah bertambah `link_*`, atribusi pendapatan yang sudah dipakai
     * orang akan dibongkar diam-diam.
     */
    public function test_hanya_vonis_gagal_yang_boleh_ditinjau_ulang(): void
    {
        $r = new \ReflectionClass(OmnicareReconcile::class);
        $daftar = $r->getConstant('VONIS_BISA_DITINJAU');

        $this->assertSame(['no_customer_link', 'conflict_multi'], $daftar);
        foreach ($daftar as $v) {
            $this->assertStringStartsNotWith('link_', $v);
        }
    }

    /**
     * ⛔ MATCH_WINDOW tidak boleh dilebarkan: 469 dari 594 kegagalan terjadi
     * di hari yang PERSIS SAMA. Melebarkannya cuma menambah jodoh palsu.
     */
    public function test_match_window_tetap_tiga_hari(): void
    {
        $r = new \ReflectionClass(OmnicareReconcile::class);
        $this->assertSame(3, $r->getConstant('MATCH_WINDOW'));

        $c = new \ReflectionClass(\App\Http\Controllers\ReconciliationController::class);
        $this->assertSame(
            $r->getConstant('MATCH_WINDOW'),
            $c->getConstant('WINDOW'),
            'WINDOW di ReconciliationController harus sama dengan MATCH_WINDOW'
        );
    }
}
