<?php

namespace App\Support;

class Phone
{
    /**
     * Normalisasi nomor telepon ke format lokal 08xxxxxxxxx.
     * Tangani +62 / 62 / 0062 / spasi / strip / kurung. Null bila tak valid.
     *
     * PENTING: logika ini HARUS identik dengan
     * App\Http\Controllers\Api\ApiCekatController::normalizePhone()
     * supaya data dari webhook/Cekat (yang sudah ada) konsisten dengan
     * data dari form reservasi/admin. Jangan ubah salah satu saja.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $raw);
        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '0062')) {
            $digits = '0' . substr($digits, 4);
        } elseif (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        } elseif ($digits[0] !== '0') {
            $digits = '0' . $digits;
        }

        return preg_match('/^0[0-9]{9,14}$/', $digits) ? $digits : null;
    }
}
