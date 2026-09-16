<?php

namespace Tests\Unit;

use App\Console\Commands\MatchOmnicarePatients;
use App\Support\OmnicareIdentitas as Ident;
use PHPUnit\Framework\TestCase;

/**
 * Uji INTI perbaikan 2026-09-16: pid mana saja yang diambil untuk satu pasien.
 *
 * Tanpa database — `pilihKandidat()` sengaja dibuat murni supaya keputusan
 * "siapa berbagi rekam medis dengan siapa" bisa diperiksa satu per satu.
 *
 *   vendor/bin/phpunit tests/Unit/PenjodohanPidTest.php
 */
class PenjodohanPidTest extends TestCase
{
    private MatchOmnicarePatients $cmd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cmd = new MatchOmnicarePatients();
    }

    /** Bangun satu kandidat berskor, seperti yang dihasilkan score() + index. */
    private function kandidat(string $pid, int $skor, string $nama, ?string $dob): array
    {
        return [
            'pid'    => $pid,
            'score'  => $skor,
            'method' => 'phone+name',
            'ident'  => ['tokens' => Ident::tokenSet($nama), 'dob' => Ident::validDob($dob)],
        ];
    }

    private function ident(string $nama, ?string $dob): array
    {
        return ['tokens' => Ident::tokenSet($nama), 'dob' => Ident::validDob($dob)];
    }

    private function pidDiambil(array $putusan): array
    {
        return array_map(fn($a) => $a['kandidat']['pid'], $putusan['ambil']);
    }

    /**
     * Pembungkus: `$dobKita` (tanggal lahir pasien KITA) sengaja WAJIB di
     * produksi supaya tak ada pemanggil yang diam-diam kehilangan pagarnya.
     * Di uji, mayoritas kasus tidak peduli pada nilainya.
     */
    private function pilih(array $layak, array $identLama, ?string $dobKita = null): array
    {
        return $this->cmd->pilihKandidat($layak, $identLama, $dobKita);
    }

    // ── JALUR B — pasien belum tertaut sama sekali ───────────────────────────

    /** Kasus paling biasa: satu kandidat, langsung diambil. */
    public function test_satu_kandidat_diambil(): void
    {
        $p = $this->pilih(
            [$this->kandidat('B-2007-2758', 100, 'ISMU RAHARJO', '1975-03-04')],
            []
        );

        $this->assertNull($p['ambigu']);
        $this->assertSame(['B-2007-2758'], $this->pidDiambil($p));
        $this->assertFalse($p['ambil'][0]['tambahan']);
        $this->assertNull($p['ambil'][0]['tanda']);
    }

    /**
     * ⭐ CACAT YANG DIPERBAIKI. Dua pid berskor IDENTIK milik orang yang sama.
     * Versi lama menyebutnya ambigu dan tidak menaut apa pun — memblokir
     * justru kasus yang mau kita perbaiki (1.797 pid kembar di prod).
     */
    public function test_dua_pid_orang_yang_sama_diambil_keduanya(): void
    {
        $p = $this->pilih([
            $this->kandidat('B-2007-2758', 100, 'ISMU RAHARJO', '1975-03-04'),
            $this->kandidat('B-2008-2793', 100, 'ISMU RAHARJO', '1975-03-04'),
        ], []);

        $this->assertNull($p['ambigu'], 'pid kembar TIDAK boleh dianggap ambigu');
        $this->assertSame(['B-2007-2758', 'B-2008-2793'], $this->pidDiambil($p));
        $this->assertFalse($p['ambil'][0]['tambahan']);
        $this->assertTrue($p['ambil'][1]['tambahan']);
        $this->assertSame('pid_kembar_dob', $p['ambil'][1]['tanda']);
        $this->assertSame('dob', $p['ambil'][1]['kuat']);
    }

    /**
     * ⛔ PAGAR YANG TIDAK BOLEH HILANG. Dua kandidat berhimpit yang BUKAN orang
     * yang sama → tidak menaut apa pun. 682 kontak punya 2–7 pasien di satu
     * nomor; ini yang menahan ibu & anak agar tak tertukar.
     */
    public function test_kandidat_berhimpit_beda_orang_ditahan(): void
    {
        $p = $this->pilih([
            $this->kandidat('B-2007-0001', 65, 'SITI AMINAH', null),
            $this->kandidat('B-2007-0002', 60, 'SITI AMINAH PUTRI', null),
        ], []);

        $this->assertSame([], $p['ambil'], 'tidak boleh menaut apa pun saat ambigu');
        $this->assertNotNull($p['ambigu']);
        $this->assertSame('B-2007-0001', $p['ambigu']['best']['pid']);
        $this->assertSame('B-2007-0002', $p['ambigu']['penantang']['pid']);
    }

    /**
     * ⛔ REGRESI YANG DITUTUP. Versi lama hanya memeriksa kandidat #2.
     * Di sini #2 adalah pid kembar (orang yang sama) dan #3 adalah orang LAIN
     * yang sama dekatnya — versi lama akan lolos dan menaut ibu ke anak.
     */
    public function test_penantang_di_urutan_ketiga_tetap_menahan(): void
    {
        $p = $this->pilih([
            $this->kandidat('B-2007-0001', 65, 'SITI AMINAH', null),
            $this->kandidat('B-2008-0009', 65, 'SITI AMINAH', null),        // kembar
            $this->kandidat('B-2007-0002', 60, 'SITI AMINAH PUTRI', null),  // orang lain
        ], []);

        $this->assertSame([], $p['ambil']);
        $this->assertNotNull($p['ambigu']);
        $this->assertSame('B-2007-0002', $p['ambigu']['penantang']['pid']);
    }

    /** Penantang yang jaraknya ≥ margin (10) tidak menahan apa pun. */
    public function test_penantang_jauh_tidak_menahan(): void
    {
        $p = $this->pilih([
            $this->kandidat('B-2007-0001', 100, 'SITI AMINAH', '1990-05-05'),
            $this->kandidat('B-2007-0002', 65,  'SITI AMINAH PUTRI', null),
        ], []);

        $this->assertNull($p['ambigu']);
        $this->assertSame(['B-2007-0001'], $this->pidDiambil($p));
    }

    public function test_tanpa_kandidat_tidak_mengambil_apa_pun(): void
    {
        $p = $this->pilih([], []);
        $this->assertSame([], $p['ambil']);
        $this->assertNull($p['ambigu']);
    }

    // ── JALUR A — pasien SUDAH tertaut, mencari pid tambahan ─────────────────

    /**
     * ⭐ KASUS 150 KUNJUNGAN. Pasien sudah tertaut ke pid cabang Bintaro;
     * kunjungannya di Sunter memakai pid lain. Versi lama MELEWATI pasien ini
     * sepenuhnya, jadi kunjungan cabang kedua tak akan pernah terjodoh.
     */
    public function test_pid_cabang_kedua_ditambahkan(): void
    {
        $p = $this->pilih(
            [$this->kandidat('S-2105-0300', 100, 'ISMU RAHARJO', '1975-03-04')],
            [$this->ident('ISMU RAHARJO', '1975-03-04')]   // pid Bintaro yang sudah tertaut
        );

        $this->assertNull($p['ambigu']);
        $this->assertSame(['S-2105-0300'], $this->pidDiambil($p));
        $this->assertTrue($p['ambil'][0]['tambahan']);
        $this->assertSame('pid_kembar_dob', $p['ambil'][0]['tanda']);
    }

    /**
     * ⛔ PAGAR JALUR A. Anggota keluarga di nomor yang sama TIDAK ikut
     * tertarik ke pasien yang sudah tertaut, walau skornya lolos ambang.
     */
    public function test_anggota_keluarga_tidak_ikut_tertaut(): void
    {
        $p = $this->pilih([
            $this->kandidat('S-2105-0300', 100, 'SITI AMINAH', '1990-05-05'),       // dia sendiri
            $this->kandidat('S-2105-0301', 100, 'SITI AMINAH PUTRI', '2015-05-05'), // anaknya
        ], [$this->ident('SITI AMINAH', '1990-05-05')]);

        $this->assertSame(['S-2105-0300'], $this->pidDiambil($p));
    }

    /** ⛔ Nama sama, tanggal lahir bertentangan → bukan pid tambahan. */
    public function test_dob_bertentangan_tidak_ditambahkan(): void
    {
        $p = $this->pilih(
            [$this->kandidat('S-2105-0301', 100, 'SITI AMINAH', '2015-05-05')],
            [$this->ident('SITI AMINAH', '1990-05-05')]
        );

        $this->assertSame([], $this->pidDiambil($p));
    }

    /** DOB kosong di salah satu sisi masih boleh — tapi ditandai lebih lemah. */
    public function test_tambahan_tanpa_dob_ditandai_lebih_lemah(): void
    {
        $p = $this->pilih(
            [$this->kandidat('S-2105-0300', 65, 'ISMU RAHARJO', null)],
            [$this->ident('ISMU RAHARJO', '1975-03-04')]
        );

        $this->assertSame(['S-2105-0300'], $this->pidDiambil($p));
        $this->assertSame('pid_kembar_nama', $p['ambil'][0]['tanda']);
        $this->assertSame('nama', $p['ambil'][0]['kuat']);
    }

    /** Pasien sudah tertaut, kandidat orang lain semua → tidak ada tambahan. */
    public function test_tidak_ada_tambahan_bila_semua_orang_lain(): void
    {
        $p = $this->pilih(
            [$this->kandidat('S-2105-0301', 100, 'BUDI SANTOSO', '1988-01-01')],
            [$this->ident('ISMU RAHARJO', '1975-03-04')]
        );

        $this->assertSame([], $p['ambil']);
        $this->assertNull($p['ambigu']);
    }

    /** Pasien dengan TIGA pid: dua sudah dipegang, satu lagi ditemukan. */
    public function test_pid_ketiga_ditemukan_lewat_salah_satu_pid_lama(): void
    {
        $p = $this->pilih(
            [$this->kandidat('C-2201-0044', 100, 'ISMU RAHARJO', '1975-03-04')],
            [
                $this->ident('ISMU RAHARJO', null),          // pid lama tanpa DOB
                $this->ident('ISMU RAHARJO', '1975-03-04'),  // pid lama dengan DOB
            ]
        );

        $this->assertSame(['C-2201-0044'], $this->pidDiambil($p));
        $this->assertTrue($p['ambil'][0]['tambahan']);
    }

    // ── DOB PASIEN KITA SENDIRI (temuan audit HUB 16 Sep) ───────────────────

    /**
     * ⛔ TEMUAN HUB 1. Seluruh pagar DOB bekerja di sisi OMNICARE saja; tanggal
     * lahir yang KITA pegang tak pernah ikut diperiksa. Pid lama tanpa DOB jadi
     * jembatan — kelas bug yang sama dengan dobBercabang(), lewat pintu lain.
     *
     * `score()` pun tidak MENGHUKUM DOB yang bertentangan, ia cuma tidak memberi
     * bonus: phone 30 + nama persis 35 = 65 ≥ 60 → tetap auto-link.
     */
    public function test_kandidat_yang_dob_nya_bertentangan_dengan_pasien_kita_ditolak(): void
    {
        $p = $this->pilih(
            [$this->kandidat('S-2026-999', 65, 'SITI AMINAH', '2015-03-01')],
            [$this->ident('SITI AMINAH', null)],   // pid lama TANPA DOB = jembatan
            '1990-05-05'                           // DOB pasien KITA
        );

        $this->assertSame([], $this->pidDiambil($p), 'DOB kita bertentangan — jangan ditaut');
        $this->assertCount(1, $p['ditolak']);
        $this->assertSame('S-2026-999', $p['ditolak'][0]['kandidat']['pid']);
    }

    /**
     * Kandidat yang ditolak TIDAK selalu berarti tautan yang dicegah: kalau
     * masih ada kandidat lain yang benar, pasiennya tetap tertaut.
     *
     * ⚠️ Diukur di data nyata: 8 kandidat ditolak, **nol** hasil berubah —
     * semuanya toh gugur di pagar berikutnya. Karena itu laporan menghitung
     * "yang mengubah hasil" dengan mengadu putusan ini melawan tandingannya
     * tanpa pagar, bukan dengan menebak dari sini.
     */
    public function test_penolakan_tidak_selalu_mencegah_tautan(): void
    {
        $p = $this->pilih([
            $this->kandidat('S-BENAR', 100, 'SITI AMINAH', '1990-05-05'),
            $this->kandidat('S-SALAH', 65,  'SITI AMINAH', '2015-03-01'),
        ], [], '1990-05-05');

        $this->assertSame(['S-BENAR'], $this->pidDiambil($p));
        $this->assertCount(1, $p['ditolak']);
    }

    /**
     * Tandingan tanpa pagar: kandidat yang ditolak di sini memang TIDAK akan
     * tertaut walau pagarnya dimatikan (gugur di `samaOrang` jalur A). Inilah
     * bentuk yang membuat "8 ditolak" menjadi "0 berubah".
     */
    public function test_kandidat_ditolak_yang_toh_gugur_di_pagar_berikutnya(): void
    {
        $layak     = [$this->kandidat('S-LAIN', 65, 'BUDI SANTOSO', '2015-03-01')];
        $identLama = [$this->ident('SITI AMINAH', null)];

        $denganPagar = $this->pilih($layak, $identLama, '1990-05-05');
        $tanpaPagar  = $this->pilih($layak, $identLama, null);

        $this->assertCount(1, $denganPagar['ditolak']);
        $this->assertSame(
            $this->pidDiambil($tanpaPagar),
            $this->pidDiambil($denganPagar),
            'hasilnya sama — penolakan ini tidak mengubah apa pun'
        );
    }

    /** Jalur B kena pagar yang sama — pasien belum tertaut pun tetap dijaga. */
    public function test_jalur_b_juga_menolak_dob_yang_bertentangan(): void
    {
        $p = $this->pilih(
            [$this->kandidat('S-2026-999', 65, 'SITI AMINAH', '2015-03-01')],
            [],
            '1990-05-05'
        );

        $this->assertSame([], $this->pidDiambil($p));
        $this->assertCount(1, $p['ditolak']);
    }

    /**
     * ⛔ JANGAN menolak hari↔bulan TERTUKAR. Impor massal 12 Mei 2026 menulis
     * ~472 tanggal lahir kita dengan hari & bulan tertukar (diukur HUB di prod).
     * Menolak kategori itu akan membuang ratusan tautan yang BENAR hanya karena
     * tanggal di sisi kita salah ketik.
     */
    public function test_dob_tertukar_hari_bulan_tetap_diterima(): void
    {
        $p = $this->pilih(
            [$this->kandidat('S-2105-0300', 65, 'SOEJANTO HARTONO', '1963-06-01')],
            [],
            '1963-01-06'                           // hari & bulan tertukar
        );

        $this->assertSame(['S-2105-0300'], $this->pidDiambil($p));
        $this->assertSame([], $p['ditolak']);
    }

    /** Selisih sehari juga bukan alasan menolak — salah ketik, bukan salah orang. */
    public function test_dob_selisih_sehari_tetap_diterima(): void
    {
        $p = $this->pilih(
            [$this->kandidat('S-2105-0300', 65, 'EDRIC ARISTYO', '1988-10-03')],
            [],
            '1988-10-02'
        );

        $this->assertSame(['S-2105-0300'], $this->pidDiambil($p));
    }

    /** DOB kita kosong → tidak ada yang bisa dibandingkan, jangan menghalangi. */
    public function test_dob_kita_kosong_tidak_menolak_apa_pun(): void
    {
        $p = $this->pilih(
            [$this->kandidat('S-2105-0300', 65, 'SITI AMINAH', '2015-03-01')],
            [$this->ident('SITI AMINAH', null)],
            null
        );

        $this->assertSame(['S-2105-0300'], $this->pidDiambil($p));
    }

    // ── DOB BERCABANG — dua orang senama merapat lewat rekam ber-DOB kosong ──

    /**
     * ⛔ JALUR A. Tautan lama tanpa DOB (54,5% tautan memang begitu) menjadi
     * JEMBATAN: ayah & anak senama sama-sama "cocok" dengannya, dan keduanya
     * ikut tertaut ke satu pasien. Rekam medis dua orang jadi menyatu.
     */
    public function test_jalur_a_dua_dob_bertengkar_lewat_tautan_tanpa_dob_ditahan(): void
    {
        $p = $this->pilih([
            $this->kandidat('B-2007-0010', 65, 'AYU LESTARI', '1990-01-01'),
            $this->kandidat('B-2007-0011', 65, 'AYU LESTARI', '2015-01-01'),
        ], [$this->ident('AYU LESTARI', null)]);   // tautan lama TANPA DOB

        $this->assertSame([], $this->pidDiambil($p), 'dua DOB berbeda tak boleh menyatu');
        $this->assertNotNull($p['ambigu']);
    }

    /**
     * ⛔ JALUR A, bentuk yang lebih halus: tautan lama punya DUA baris — satu
     * ber-DOB, satu tanpa. Kandidat ber-DOB LAIN merapat lewat yang kosong.
     */
    public function test_jalur_a_kandidat_merapat_lewat_baris_lama_yang_kosong(): void
    {
        $p = $this->pilih(
            [$this->kandidat('B-2007-0011', 65, 'AYU LESTARI', '2015-01-01')],
            [
                $this->ident('AYU LESTARI', null),
                $this->ident('AYU LESTARI', '1990-01-01'),
            ]
        );

        $this->assertSame([], $this->pidDiambil($p));
        $this->assertNotNull($p['ambigu']);
    }

    /**
     * ⛔ JALUR B, cacat yang sama: kandidat TERBAIK kebetulan tanpa DOB, jadi
     * dua orang ber-DOB berbeda sama-sama lolos sebagai "kembar" dan ketiganya
     * tertaut ke satu pasien. Penjaga ambiguitas tidak menangkapnya karena
     * ketiganya dianggap satu orang.
     */
    public function test_jalur_b_kembar_tanpa_dob_tidak_boleh_menyatukan_dua_orang(): void
    {
        $p = $this->pilih([
            $this->kandidat('B-2007-0009', 65, 'AYU LESTARI', null),
            $this->kandidat('B-2007-0010', 65, 'AYU LESTARI', '1990-01-01'),
            $this->kandidat('B-2007-0011', 65, 'AYU LESTARI', '2015-01-01'),
        ], []);

        $this->assertSame([], $this->pidDiambil($p));
        $this->assertNotNull($p['ambigu']);
    }

    /** Kontrol positif: DOB kosong + SATU DOB saja tetap boleh menyatu. */
    public function test_satu_dob_saja_dengan_baris_kosong_tetap_boleh(): void
    {
        $p = $this->pilih([
            $this->kandidat('B-2007-0009', 65, 'AYU LESTARI', null),
            $this->kandidat('B-2007-0010', 65, 'AYU LESTARI', '1990-01-01'),
        ], []);

        $this->assertSame(['B-2007-0009', 'B-2007-0010'], $this->pidDiambil($p));
        $this->assertNull($p['ambigu']);
    }

    /**
     * Penanda pid tambahan harus SELALU menyebut kekuatannya — di kedua jalur.
     * Dua ejaan untuk satu konsep membuat laporan tak bisa membedakan pid yang
     * dikuatkan DOB dari yang cuma bermodal nama.
     */
    public function test_penanda_kembar_selalu_menyebut_kekuatan(): void
    {
        $jalurB = $this->pilih([
            $this->kandidat('B-1', 65, 'AYU LESTARI', null),
            $this->kandidat('B-2', 65, 'AYU LESTARI', null),
        ], []);
        $this->assertSame('pid_kembar_nama', $jalurB['ambil'][1]['tanda']);

        $jalurA = $this->pilih(
            [$this->kandidat('B-3', 100, 'AYU LESTARI', '1990-01-01')],
            [$this->ident('AYU LESTARI', '1990-01-01')]
        );
        $this->assertSame('pid_kembar_dob', $jalurA['ambil'][0]['tanda']);
    }

    /**
     * Satu pid hanya boleh diambil SEKALI walaupun cocok dengan beberapa pid
     * lama — kalau tidak, satu baris link disisipkan berkali-kali.
     */
    public function test_pid_yang_sama_tidak_diambil_dua_kali(): void
    {
        $p = $this->pilih(
            [$this->kandidat('C-2201-0044', 100, 'ISMU RAHARJO', '1975-03-04')],
            [
                $this->ident('ISMU RAHARJO', '1975-03-04'),
                $this->ident('ISMU RAHARJO', '1975-03-04'),
                $this->ident('ISMU RAHARJO', null),
            ]
        );

        $this->assertCount(1, $p['ambil']);
    }
}
