<?php

namespace App\Services\Ortho;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Pengumpul KUNJUNGAN ortho dari cermin rekam medis (`omnicare_sales_detail`).
 *
 * Dipakai BERSAMA oleh tarikan malam (`ort:pull-visits`) dan penyemai
 * (`ort:seed-cases`, menyusul). Keduanya harus menghasilkan kunjungan yang
 * PERSIS sama dari baris cermin yang sama — bukan "kira-kira sama".
 *
 * KENAPA HARUS SATU KELAS
 * -----------------------
 * Pagar anti-dobel modul ini adalah `ort_control.omnicare_trx_id` UNIQUE.
 * Satu kunjungan biasanya terdiri dari beberapa BARIS cermin (Control + Kawat
 * + Orthokit di tanggal yang sama), dan hanya SATU trx_id yang disimpan
 * sebagai wakilnya. Kalau penyemai memilih wakil A dan tarikan malam memilih
 * wakil B untuk kunjungan yang sama, pagarnya tidak berbunyi: satu kunjungan,
 * dua baris riwayat, kehadiran 200%. Menyalin logikanya ke dua berkas adalah
 * jaminan mereka menyimpang suatu hari. Maka logikanya cuma hidup di sini.
 *
 * WAKIL DIPILIH DETERMINISTIK — bukan "baris pertama menurut urutan"
 * ---------------------------------------------------------------------
 * Versi lama memakai baris pertama hasil ORDER BY trx_date, trx_id. Itu
 * bocor ketika baris cermin datang MENYUSUL: malam pertama baru ada "Kawat"
 * (trx 900) → wakil = 900; malam kedua baris "Control"-nya masuk (trx 100)
 * → wakil = 100 → pagar trx tidak mengenalinya → baris kedua lahir. Aturan
 * di sini stabil terhadap kedatangan susulan:
 *
 *   1. prioritas KLASIFIKASI: pasang > lepas > kontrol > penyerta
 *      (baris yang paling bermakna mewakili kunjungan — juga yang paling
 *      berguna untuk penyemai: nama item pemasangan → jenis bracket)
 *   2. lalu trx_id TERKECIL (numerik bila keduanya angka)
 *
 * Tetap ada celah yang tak bisa ditutup dari sini: baris berprioritas lebih
 * tinggi yang datang susulan MENGUBAH wakil. Itu sebabnya penarik punya pagar
 * KEDUA `(ort_case_id, visit_date)` — lihat OrtPullVisits::terapkan().
 *
 * KUNCI DEDUP: pid | tanggal | cabang
 * ----------------------------------
 * Satu tanggal di satu cabang = satu kunjungan. Pasien yang (jarang) datang
 * di dua cabang pada hari yang sama menghasilkan dua kunjungan di sini;
 * pagar kedua di penarik yang memutuskan hanya satu yang jadi riwayat.
 *
 * Kelas ini TIDAK menyentuh ort_* dan tidak tahu apa itu kasus. Ia cuma
 * mengubah baris cermin menjadi daftar kunjungan.
 */
final class OrtVisits
{
    /** Kolom cermin yang dibutuhkan — satu daftar untuk semua pemakai. */
    public const KOLOM = [
        'trx_id', 'trx_date', 'p_id', 'p_name', 'place_id',
        'item_name', 'item_extra_name', 'item_code',
        'encounter_id', 'doctor', 'doc_reff_id',
    ];

    /** Semakin kecil semakin utama. */
    private const PRIORITAS = [
        OrtItemRules::PASANG   => 0,
        OrtItemRules::LEPAS    => 1,
        OrtItemRules::KONTROL  => 2,
        OrtItemRules::PENYERTA => 3,
    ];

    public function __construct(private OrtItemRules $rules)
    {
    }

    /**
     * Kueri baku ke cermin: kolom dan saringan yang sama untuk semua pemakai.
     * Memakai index (place_id, trx_date) yang sudah ada. Urutan tidak
     * ditentukan di sini — kumpulkan() tidak bergantung pada urutan masuk.
     *
     * @param  list<string>  $places
     */
    public function kueri(array $places, string $sejak, string $sampai): Builder
    {
        return DB::table('omnicare_sales_detail')
            ->whereIn('place_id', $places)
            ->whereBetween('trx_date', [$sejak, $sampai])
            ->select(self::KOLOM);
    }

    /**
     * Baris cermin → daftar kunjungan, terdedup, dengan wakil deterministik.
     *
     * Baris ber-`p_id` kosong TIDAK dibuang — dikembalikan dengan `pid` = ''
     * supaya pemanggil bisa MENGHITUNGNYA (rekam medis tanpa nomor pasien
     * adalah temuan, bukan sampah). Pemanggil yang memutuskan.
     *
     * @param  iterable<object>  $baris  objek dengan kolom self::KOLOM, urutan bebas
     * @return list<array{
     *   pid:string, tanggal:string, place:string,
     *   trx_id:string, encounter:?string, dokter:?string, doc_reff:?string,
     *   item:?string, kode:?string, p_name:?string,
     *   jenis:list<string>, lepas:bool, pasang:bool
     * }>
     */
    public function kumpulkan(iterable $baris): array
    {
        $kunjungan = [];

        foreach ($baris as $r) {
            $jenis = $this->rules->klasifikasi($r->item_name, $r->item_extra_name ?? null, $r->item_code ?? null);
            if ($jenis === null) {
                continue;
            }

            $pid   = self::pid($r->p_id ?? null);
            $tgl   = substr((string) $r->trx_date, 0, 10);   // 'YYYY-MM-DD' dari date maupun datetime
            $place = (string) $r->place_id;
            $kunci = $pid . '|' . $tgl . '|' . $place;

            $calon = [
                'trx_id'    => (string) $r->trx_id,
                'jenis'     => $jenis,
                'encounter' => filled($r->encounter_id ?? null) ? (string) $r->encounter_id : null,
                'dokter'    => $r->doctor ?? null,
                'doc_reff'  => filled($r->doc_reff_id ?? null) ? (string) $r->doc_reff_id : null,
                'item'      => $r->item_name,
                'kode'      => $r->item_code ?? null,
                // Nama pasien apa adanya di baris penjualan. Cadangan identitas
                // untuk penyemai ketika omnicare_patient belum tersinkron —
                // kasus tetap punya nama, bukan "Pasien B-2410-1037".
                'p_name'    => $r->p_name ?? null,
            ];

            if (! isset($kunjungan[$kunci])) {
                $kunjungan[$kunci] = [
                    'pid' => $pid, 'tanggal' => $tgl, 'place' => $place,
                    'jenis' => [], 'lepas' => false, 'pasang' => false, 'wakil' => null,
                ];
            }

            $k = &$kunjungan[$kunci];
            $k['jenis'][] = $jenis;
            if ($jenis === OrtItemRules::LEPAS)  { $k['lepas']  = true; }
            if ($jenis === OrtItemRules::PASANG) { $k['pasang'] = true; }
            if ($k['wakil'] === null || self::lebihUtama($calon, $k['wakil'])) {
                $k['wakil'] = $calon;
            }
            unset($k);
        }

        // Ratakan wakil ke tingkat atas, lalu urutkan — keluaran harus sama
        // persis apa pun urutan masuknya.
        $out = [];
        foreach ($kunjungan as $k) {
            $w = $k['wakil'];
            unset($k['wakil'], $w['jenis']);
            sort($k['jenis']);   // bebas urutan masuk, sama seperti wakilnya
            $out[] = $k + $w;
        }
        usort($out, fn ($a, $b) => [$a['tanggal'], $a['pid'], $a['place']] <=> [$b['tanggal'], $b['pid'], $b['place']]);

        return $out;
    }

    /** Nomor pasien yang dinormalkan; '' berarti tidak ada. */
    public static function pid($mentah): string
    {
        $pid = trim((string) $mentah);

        return in_array($pid, ['', '0', '-', 'null', 'NULL'], true) ? '' : $pid;
    }

    private static function lebihUtama(array $a, array $b): bool
    {
        $pa = self::PRIORITAS[$a['jenis']] ?? PHP_INT_MAX;
        $pb = self::PRIORITAS[$b['jenis']] ?? PHP_INT_MAX;
        if ($pa !== $pb) {
            return $pa < $pb;
        }

        return self::bandingTrx($a['trx_id'], $b['trx_id']) < 0;
    }

    /**
     * trx_id ASC. Numerik kalau keduanya angka — supaya "99" < "100" — tanpa
     * cast ke int (trx_id Omnicare bisa lebih panjang dari 64 bit); selain
     * itu leksikal.
     */
    public static function bandingTrx(string $a, string $b): int
    {
        if (ctype_digit($a) && ctype_digit($b)) {
            return (strlen($a) <=> strlen($b)) ?: strcmp($a, $b);
        }

        return strcmp($a, $b);
    }
}
