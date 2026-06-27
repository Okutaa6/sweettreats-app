<?php

namespace App\Support;

/**
 * Derivasi spesialisasi dokter DARI NAMA (raw_doctor tidak punya kolom
 * spesialisasi). Suffix gelar di nama jadi sumber: "Sp. Ort" → Orthodonti,
 * "Sp. Pros" → Prosthodonti, "Sp. BM" → Bedah Mulut, "Sp. Kg" → Konservasi,
 * tanpa Sp. → Dokter Gigi Umum. Suffix Sp.* lain → "Spesialis" generik.
 *
 * Dipakai di halaman Doctor (list/detail/master) untuk badge, warna blok
 * jadwal, segment, dan filter. Warna ikut design handoff doctor.
 */
class DoctorSpec
{
    /** @return array{key:string,label:string,color:string,bg:string} */
    public static function forName(?string $name): array
    {
        $n = (string) $name;

        // Butuh "Sp." (dengan titik) atau "Sp " (spasi) lalu huruf gelar,
        // supaya "sp" di tengah kata lain tidak ketangkep.
        if (preg_match('/\bSp(?:\.\s*|\s+)([A-Za-z]+)/i', $n, $m)) {
            $code = strtoupper($m[1]);

            if (str_starts_with($code, 'ORT'))  return self::pack('ortho',      'Orthodonti',      '#6A53C9', '#EFEAFB');
            if (str_starts_with($code, 'PROS')) return self::pack('prostho',    'Prosthodonti',    '#B5701A', '#FBEFD9');
            if ($code === 'BM')                 return self::pack('bedah',      'Bedah Mulut',     '#C0392B', '#FBE3DE');
            if (str_starts_with($code, 'KG'))   return self::pack('konservasi', 'Konservasi Gigi', '#3C5FA8', '#E4ECFB');

            return self::pack('spesialis', 'Spesialis', '#7A5B43', '#F1E9DF');
        }

        return self::pack('umum', 'Dokter Gigi Umum', '#2E7D74', '#DFEDE9');
    }

    private static function pack(string $key, string $label, string $color, string $bg): array
    {
        return ['key' => $key, 'label' => $label, 'color' => $color, 'bg' => $bg];
    }
}
