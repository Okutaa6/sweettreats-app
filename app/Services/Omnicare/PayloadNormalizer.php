<?php

namespace App\Services\Omnicare;

use App\Support\Phone;
use DateTime;

/**
 * Normalize raw payload dari Omnicare API sebelum tulis ke DB.
 *
 * Kebutuhan yang ditangani:
 *   - Trim whitespace (Omnicare punya field email kadang leading space)
 *   - Empty string "" → NULL (untuk konsistensi DB)
 *   - Phone format: "81335091704" → "081335091704" via Phone::normalize
 *   - Date format DD/MM/YYYY → Y-m-d
 *   - Numeric cast safe (handle "0", "-1" string)
 *   - Boolean cast dari berbagai truthy representations
 */
final class PayloadNormalizer
{
    /** Trim whitespace + convert empty string to NULL. */
    public static function str($s): ?string
    {
        if ($s === null) {
            return null;
        }
        $trimmed = trim((string) $s);
        return $trimmed === '' ? null : $trimmed;
    }

    /** Parse DD/MM/YYYY (Omnicare display format) to Y-m-d, NULL if invalid. */
    public static function dateFromDmy($s): ?string
    {
        $s = self::str($s);
        if ($s === null) {
            return null;
        }
        $d = DateTime::createFromFormat('d/m/Y', $s);
        return $d ? $d->format('Y-m-d') : null;
    }

    /** Parse YYYYMMDD (Omnicare query/return format) to Y-m-d, NULL if invalid. */
    public static function dateFromYmd($s): ?string
    {
        $s = self::str($s);
        if ($s === null) {
            return null;
        }
        $d = DateTime::createFromFormat('Ymd', $s);
        return $d ? $d->format('Y-m-d') : null;
    }

    /**
     * Parse DD-MM-YYYY (invoice_date di sales_payments) to Y-m-d, NULL if invalid.
     */
    public static function dateFromDmyDash($s): ?string
    {
        $s = self::str($s);
        if ($s === null) {
            return null;
        }
        $d = DateTime::createFromFormat('d-m-Y', $s);
        return $d ? $d->format('Y-m-d') : null;
    }

    /**
     * Parse DD-MM-YYYY HH:MM:SS (pay_date/process_date di sales_payments) to
     * Y-m-d H:i:s datetime, NULL if invalid.
     */
    public static function dateTimeFromDmyHis($s): ?string
    {
        $s = self::str($s);
        if ($s === null) {
            return null;
        }
        $d = DateTime::createFromFormat('d-m-Y H:i:s', $s);
        return $d ? $d->format('Y-m-d H:i:s') : null;
    }

    /** Normalize phone ke format 08xxxxxxxxx via App\Support\Phone. */
    public static function phone($s): ?string
    {
        $s = self::str($s);
        if ($s === null) {
            return null;
        }
        return Phone::normalize($s);
    }

    /** Cast to int, return 0 untuk NULL/empty. Handle string numerics. */
    public static function int($v): int
    {
        if ($v === null || $v === '') {
            return 0;
        }
        return (int) $v;
    }

    /** Cast to float (decimal), return 0.0 untuk NULL/empty. */
    public static function decimal($v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }
        return (float) $v;
    }

    /**
     * Cast to tinyint(1). Handles:
     *   - "1", 1, true, "true"      → 1
     *   - "0", 0, "", null, false   → 0
     *   - other strings → 0
     */
    public static function bool($v): int
    {
        if ($v === null || $v === '' || $v === false || $v === 0 || $v === '0' || $v === 'false') {
            return 0;
        }
        return 1;
    }

    /** Cast Unix milliseconds bigint. Return 0 if invalid. */
    public static function unixMs($v): int
    {
        if ($v === null || $v === '') {
            return 0;
        }
        return (int) $v;
    }

    /**
     * Encode raw payload to JSON for storage in raw_payload column.
     * Returns NULL if encode fails or empty array.
     */
    public static function jsonPayload($data): ?string
    {
        if (empty($data)) {
            return null;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json !== false ? $json : null;
    }
}
