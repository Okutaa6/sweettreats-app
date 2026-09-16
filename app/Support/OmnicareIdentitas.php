<?php

namespace App\Support;

/**
 * Perbandingan identitas orang untuk penjodohan Omnicare.
 *
 * Dipisah dari MatchOmnicarePatients supaya bisa diuji TANPA database —
 * keputusan paling berbahaya di seluruh integrasi ini ("dua baris ini orang
 * yang sama?") tidak boleh hanya teruji lewat perintah artisan yang butuh
 * 16.500 baris untuk dijalankan.
 *
 * ⭐ Kenapa kelas ini ada sama sekali: Omnicare menerbitkan `p_id` PER CABANG,
 *    jadi satu orang yang berobat di dua cabang punya dua pid. Untuk menaut
 *    keduanya ke satu pasien kita, kita perlu jawaban yang bisa
 *    dipertanggungjawabkan atas "apakah dua pid ini orang yang sama" — dan
 *    jawaban itu harus BERBEDA dari "apakah pid ini cocok dengan pasien kita".
 *    Yang pertama membandingkan dua rekam Omnicare; yang kedua memberi skor.
 */
class OmnicareIdentitas
{
    /**
     * Apakah dua rekam Omnicare ini orang yang SAMA?
     *
     * Sengaja KETAT, jauh lebih ketat daripada skor penjodohan:
     *   - Nama harus cocok sebagai HIMPUNAN TOKEN. Bukan fuzzy, bukan subset.
     *     "SITI AMINAH" dan "SITI AMINAH PUTRI" BUKAN orang yang sama di sini,
     *     walaupun skor subset 25 cukup untuk menaut salah satunya ke pasien
     *     kita. Ibu & anak di satu nomor persis berbentuk seperti itu.
     *   - Tanggal lahir tidak boleh BERTENTANGAN. Salah satu kosong dianggap
     *     tidak bertentangan — itu kasus "satu rekam dibuat tanpa DOB", yang
     *     memang jamak di data kita (54,5% tautan lama tanpa DOB).
     *
     * ⚠️ Nomor telepon TIDAK diperiksa di sini. Pemanggilnya sudah bekerja di
     *    dalam satu ember nomor yang sama — memeriksanya lagi cuma akan
     *    menyembunyikan kalau suatu hari asumsi itu berubah.
     *
     * @param array{tokens:array,dob:?string} $a
     * @param array{tokens:array,dob:?string} $b
     */
    public static function samaOrang(array $a, array $b): bool
    {
        $ta = $a['tokens'] ?? [];
        $tb = $b['tokens'] ?? [];

        // Tanpa nama, tidak ada dasar untuk menyatakan "orang yang sama".
        if (empty($ta) || empty($tb)) {
            return false;
        }
        if ($ta !== $tb) {
            return false;
        }

        $da = $a['dob'] ?? null;
        $db = $b['dob'] ?? null;
        if ($da !== null && $db !== null && $da !== $db) {
            return false; // nama sama, lahir beda → dua orang, bukan satu
        }

        return true;
    }

    /**
     * ⛔ Apakah sekumpulan rekam yang mau disatukan punya LEBIH DARI SATU
     *    tanggal lahir? Kalau ya, yang mau disatukan itu bukan satu orang.
     *
     * Kenapa ini perlu terpisah dari `samaOrang()`: `samaOrang()` memaafkan DOB
     * kosong (harus — 54,5% tautan lama memang tanpa DOB). Tapi sifat "maaf"
     * itu TIDAK MENURUN. Rekam tanpa DOB cocok dengan ayah MAUPUN anak yang
     * senama, jadi ia menjadi JEMBATAN: A↔B benar, B↔C benar, tapi A↔C salah.
     * Memeriksa pasangan satu per satu tidak akan pernah menangkapnya —
     * keseluruhan kelompoknya yang harus diperiksa.
     *
     * Terbukti nyata, bukan kehati-hatian berlebih: tanpa pemeriksaan ini,
     * tiga rekam "AYU LESTARI" (satu tanpa DOB, satu lahir 1990, satu lahir
     * 2015) menyatu menjadi satu pasien. Lihat
     * tests/Unit/PenjodohanPidTest.php.
     *
     * @param array<int,array{tokens:array,dob:?string}> $kumpulan
     */
    public static function dobBercabang(array $kumpulan): bool
    {
        $dob = [];
        foreach ($kumpulan as $k) {
            $d = $k['dob'] ?? null;
            if ($d !== null) {
                $dob[$d] = true;
            }
        }

        return count($dob) > 1;
    }

    /**
     * Jenis perselisihan antara dua tanggal lahir — untuk MEN-TRIASE antrean
     * manusia, bukan untuk mengambil keputusan.
     *
     * ⭐ Kenapa dibedakan. Diukur di data nyata, dari 16 tautan yang DOB-nya
     *    bertentangan, beberapa ternyata `1963-01-06` vs `1963-06-01` — hari
     *    dan bulan TERTUKAR, artefak format d/m/Y yang sudah dikenal di API
     *    Omnicare. Yang lain cuma beda satu hari. Menumpuk semuanya sebagai
     *    "bertentangan" membuat orang menyimpulkan 16 tautan menunjuk orang
     *    yang salah, padahal sebagian besar cuma salah ketik tanggal.
     *    Salah-tanggal dan salah-orang butuh tindakan yang berbeda.
     *
     * @return string 'sama' | 'tertukar' | 'selisih_sehari' | 'bertentangan'
     */
    public static function bedaDob(?string $a, ?string $b): string
    {
        $a = self::validDob($a);
        $b = self::validDob($b);

        if ($a === null || $b === null || $a === $b) {
            return 'sama';   // tak ada yang bisa dibandingkan, atau memang sama
        }

        // Hari & bulan tertukar: 1963-01-06 ↔ 1963-06-01
        [$ta, $ba, $ha] = array_map('intval', explode('-', $a));
        [$tb, $bb, $hb] = array_map('intval', explode('-', $b));
        if ($ta === $tb && $ba === $hb && $ha === $bb) {
            return 'tertukar';
        }

        if (abs(strtotime($a) - strtotime($b)) <= 86400) {
            return 'selisih_sehari';
        }

        return 'bertentangan';
    }

    /**
     * Sekuat apa pernyataan "orang yang sama" itu — untuk pelaporan, bukan
     * untuk keputusan. `dob` = kedua rekam punya DOB dan keduanya sama.
     * `nama` = cocok nama saja, DOB salah satunya kosong.
     */
    public static function kekuatan(array $a, array $b): string
    {
        return (($a['dob'] ?? null) !== null && ($b['dob'] ?? null) !== null)
            ? 'dob'
            : 'nama';
    }

    /**
     * Normalisasi nama: lowercase, buang non-alfanumerik, rapikan spasi.
     * Sekaligus runtuhkan nama ganda "X X" — ~32% omnicare_patient punya
     * first_name == last_name sehingga full_name tertulis dua kali
     * ("Nazwa Sabrina Nazwa Sabrina"). Tanpa ini, fuzzy/subset rusak.
     */
    public static function normName(?string $name): string
    {
        $s = strtolower(trim((string) $name));
        $s = preg_replace('/[^a-z0-9\s]/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);
        if ($s === '') {
            return '';
        }

        $w = explode(' ', $s);
        $n = count($w);
        if ($n >= 2 && $n % 2 === 0) {
            $half = $n / 2;
            if (array_slice($w, 0, $half) === array_slice($w, $half)) {
                $s = implode(' ', array_slice($w, 0, $half));
            }
        }

        return $s;
    }

    /** Token nama unik & terurut — perbandingan tahan-urutan. */
    public static function tokenSet(?string $name): array
    {
        $norm = self::normName($name);
        if ($norm === '') {
            return [];
        }
        $tokens = array_unique(explode(' ', $norm));
        sort($tokens);
        return $tokens;
    }

    /**
     * Tanggal lahir valid → string Y-m-d, atau null.
     * Buang 0000-00-00 dan tahun absurd.
     *
     * ⚠️ TIDAK menguraikan format d/m/Y. `omnicare_patient.dob` sudah bertipe
     *    DATE (diurai SyncOmnicare lewat N::dateFromDmy). Menguraikan ulang di
     *    sini justru membuka jebakan "Carbon::parse menukar hari↔bulan".
     */
    public static function validDob($dob): ?string
    {
        if (empty($dob)) {
            return null;
        }
        $s = substr((string) $dob, 0, 10);
        if ($s === '0000-00-00') {
            return null;
        }
        $year = (int) substr($s, 0, 4);
        if ($year < 1900 || $year > (int) date('Y')) {
            return null;
        }
        return $s;
    }
}
