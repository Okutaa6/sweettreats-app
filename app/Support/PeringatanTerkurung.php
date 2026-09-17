<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Peringatan yang tidak boleh ditulis berulang-ulang.
 *
 * Dipakai untuk kejadian yang dipicu dari LUAR — perangkap bot di form publik,
 * webhook tanpa verifikasi. Kejadian seperti itu bisa datang ribuan kali dalam
 * semenit, dan tiap barisnya menambah berkas yang sama.
 *
 * Kenapa ini penting, bukan sekadar kerapian:
 *   • `laravel.log` prod **tidak berotasi** — satu berkas yang tumbuh selamanya
 *     (per 16 Sep 2026 sudah 312 MB);
 *   • berkas itu juga yang dibuka orang ketika ada yang rusak. Satu penyerang
 *     yang menulisi 360 ribu baris sehari tidak cuma membengkakkan disk, ia
 *     **menenggelamkan kejadian lain** — jadi alat pemantau berubah jadi tempat
 *     bersembunyi.
 *
 * Satu tempat, bukan tiga salinan: pola ini sebelumnya ditulis ulang di tiap
 * controller, dan tiga salinan pasti berbeda cepat atau lambat.
 *
 * ⚠️ Kurungan ini bersandar pada cache yang MELINTASI request. Prod & dev pakai
 * driver `file` → bekerja. Kalau suatu hari cache diubah ke `array` (per-request,
 * mis. di uji), kurungannya lumpuh dan tiap kejadian tercatat lagi. Arah
 * gagalnya sengaja begitu: kehilangan peringatan jauh lebih mahal daripada
 * menulis terlalu banyak.
 */
class PeringatanTerkurung
{
    /** Sekali saja per kunci dalam rentang ini. */
    public const MENIT = 60;

    /**
     * Tulis peringatan paling banyak sekali per $kunci per jam.
     *
     * @param  string  $kunci    pembeda kurungan — biasanya "<jenis>:<alamat asal>"
     * @return bool    true kalau barisnya benar-benar ditulis
     */
    public static function catat(string $kunci, string $pesan, array $konteks = []): bool
    {
        if (! Cache::add('peringatan:' . $kunci, 1, now()->addMinutes(self::MENIT))) {
            return false;
        }

        Log::warning($pesan, $konteks + [
            // Pembaca log harus tahu bahwa angka sebenarnya bisa jauh lebih besar
            // daripada jumlah baris yang dilihatnya.
            'dikurung' => 'sekali per ' . self::MENIT . ' menit untuk kunci yang sama',
        ]);

        return true;
    }
}
