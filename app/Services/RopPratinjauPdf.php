<?php

namespace App\Services;

use Imagick;

/**
 * RopPratinjauPdf — mengubah lampiran PDF jadi gambar supaya bisa IKUT
 * TERCETAK di lembar bayar.
 *
 * Kenapa ada: rencana semula menyerah pada PDF ("tak bisa ditempel di halaman
 * cetak → kirim terpisah"), dan itu menyisakan kerepotan yang justru mau
 * dihapus fitur ini — Finance tetap harus mengirim berkas kedua, bos tetap
 * harus mencocokkan sendiri. Ternyata server punya Ghostscript + ekstensi
 * Imagick (dev DAN prod, dicek 14 Agu 2026), jadi PDF bisa dirender jadi
 * gambar dan diperlakukan persis seperti foto.
 *
 * Hasil render DISIMPAN (cache) di public/uploads/rop/pratinjau: mencetak
 * ulang lembar yang sama tidak memanggil Ghostscript lagi. Kunci cache memuat
 * mtime berkas asal, jadi lampiran yang diganti otomatis dirender ulang.
 *
 * ⚠️ Gagal render TIDAK boleh menjatuhkan halaman cetak. Semua kegagalan
 * (ekstensi tidak ada, PDF rusak, Ghostscript diblokir kebijakan) pulang
 * sebagai array kosong, dan pemanggilnya kembali ke perilaku lama: lampiran
 * disebut "dikirim terpisah". Lembar tetap terbit.
 */
class RopPratinjauPdf
{
    /** Cukup untuk invoice; PDF tebal tidak boleh menyandera halaman cetak. */
    public const MAKS_HALAMAN = 5;

    /** 150 dpi: tulisan invoice terbaca, ukuran berkas masih wajar (~20-60 KB/halaman). */
    private const DPI = 150;

    private const DIR = 'uploads/rop/pratinjau';

    /**
     * Kembalikan daftar path gambar (relatif dari public/) hasil render tiap
     * halaman PDF. Array KOSONG artinya "tidak bisa dirender" — pemanggil
     * wajib menyediakan jalan mundur.
     */
    public function halaman(?string $relAsli, ?int &$totalAsli = null): array
    {
        $totalAsli = 0;
        if (! $relAsli) {
            return [];
        }

        $abs = public_path($relAsli);
        if (! is_file($abs) || ! class_exists(Imagick::class)) {
            return [];
        }

        $absDir = public_path(self::DIR);
        if (! is_dir($absDir) && ! @mkdir($absDir, 0775, true) && ! is_dir($absDir)) {
            return [];
        }

        // mtime ikut jadi kunci: lampiran yang ditimpa dirender ulang sendiri.
        $kunci = substr(sha1($relAsli . '|' . filemtime($abs)), 0, 16);

        $sudah = glob($absDir . '/' . $kunci . '-*.png') ?: [];
        if ($sudah) {
            sort($sudah);
            // Jumlah halaman ASLI disimpan di berkas pendamping. Tanpa ini,
            // saat hasil render dipakai ulang kita kehilangan informasi
            // "PDF-nya sebenarnya berapa halaman" — dan pemotongan jadi
            // senyap, persis yang tidak boleh terjadi di dokumen pembayaran.
            $catatan   = $absDir . DIRECTORY_SEPARATOR . $kunci . '-total.txt';
            $totalAsli = is_file($catatan) ? (int) file_get_contents($catatan) : count($sudah);
            return array_map(fn ($f) => self::DIR . '/' . basename($f), $sudah);
        }

        try {
            $im = new Imagick();
            $im->setResolution(self::DPI, self::DPI);
            $im->readImage($abs);

            // Halaman diambil SATU per SATU lewat setIteratorIndex + getImage().
            // Sebelumnya dipakai mergeImageLayers() di dalam foreach, dan itu
            // berbahaya: fungsi itu meratakan SELURUH urutan gambar, bukan
            // frame yang sedang ditunjuk — untuk PDF berhalaman banyak, tiap
            // halaman berisiko ditulis sebagai gabungan semua halaman.
            // getImage() mengembalikan salinan frame ini saja, jadi tak ambigu.
            $totalAsli = $im->getNumberImages();
            $jumlah    = min($totalAsli, self::MAKS_HALAMAN);
            $hasil     = [];

            file_put_contents($absDir . DIRECTORY_SEPARATOR . $kunci . '-total.txt', (string) $totalAsli);

            for ($i = 0; $i < $jumlah; $i++) {
                $im->setIteratorIndex($i);
                $frame = $im->getImage();

                // PDF sering transparan; tanpa latar putih hasilnya hitam pekat
                // saat diratakan.
                $frame->setImageFormat('png');
                $frame->setImageBackgroundColor('white');
                $rata = $frame->flattenImages();

                $nama = sprintf('%s-%02d.png', $kunci, $i + 1);
                $rata->writeImage($absDir . DIRECTORY_SEPARATOR . $nama);

                $rata->clear();
                $frame->clear();

                $hasil[] = self::DIR . '/' . $nama;
            }
            $im->clear();

            return $hasil;
        } catch (\Throwable $e) {
            // Dicatat, bukan dilempar: satu lampiran bermasalah tidak boleh
            // membuat seluruh lembar gagal dicetak.
            report($e);
            return [];
        }
    }
}
