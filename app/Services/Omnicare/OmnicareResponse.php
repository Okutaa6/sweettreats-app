<?php

namespace App\Services\Omnicare;

/**
 * Value object untuk wrap response dari Omnicare Open API.
 *
 * HTTP status selalu 200; status sebenarnya ada di body.metadata.code:
 *   200 = sukses
 *   400 = bad request / auth fail / range invalid
 *   401 = data kosong (atau resource tidak ditemukan)
 *   402 = rate limit / cooldown
 */
final class OmnicareResponse
{
    public function __construct(
        public readonly int $code,
        public readonly string $message,
        public readonly array $data,
        public readonly ?int $pages = null,
        public readonly ?int $httpStatus = 200,
        public readonly ?string $endpoint = null,
        public readonly ?array $queryParams = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->code === 200;
    }

    public function isEmpty(): bool
    {
        return $this->code === 401;
    }

    public function isCooldown(): bool
    {
        return $this->code === 402;
    }

    public function isAuthFailure(): bool
    {
        return $this->code === 400
            && stripos($this->message, 'username') !== false;
    }

    public function isDateRangeInvalid(): bool
    {
        return $this->code === 400
            && (stripos($this->message, 'tanggal') !== false
                || stripos($this->message, 'rentang') !== false);
    }

    public function isParameterError(): bool
    {
        return $this->code === 400
            && stripos($this->message, 'parameter') !== false;
    }

    public function hasMorePages(int $currentPage): bool
    {
        return $this->pages !== null && $currentPage < $this->pages;
    }
}
