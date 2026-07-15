<?php

namespace App\Services\Accurate;

/**
 * Value object untuk wrap response dari Accurate Online Open API.
 *
 * Konvensi Accurate:
 *   - Sukses list/detail : { "s": true, "d": [...] | {...}, "sp": {pagination} }
 *   - Sukses save        : { "s": true, "r": {...}, "d": ["...pesan..."] }
 *   - Gagal (logika)     : { "s": false, "d": ["pesan error"] }
 *   - Gagal (auth)       : HTTP 401 { "error": "...", "error_description": "..." }
 *
 * Pagination ada di "sp": { page, pageCount, rowCount, pageSize }.
 */
final class AccurateResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly array $data,          // isi "d" (dinormalisasi ke array)
        public readonly ?array $record = null, // isi "r" (response save)
        public readonly ?int $page = null,
        public readonly ?int $pageCount = null,
        public readonly ?int $rowCount = null,
        public readonly ?string $error = null,
        public readonly ?int $httpStatus = 200,
        public readonly ?string $endpoint = null,
        public readonly ?array $raw = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /** Token invalid / dicabut / belum dikirim (butuh perhatian operasional). */
    public function isAuthFailure(): bool
    {
        return $this->httpStatus === 401
            || ($this->error !== null && stripos($this->error, 'token') !== false);
    }

    public function hasMorePages(int $currentPage): bool
    {
        return $this->pageCount !== null && $currentPage < $this->pageCount;
    }
}
