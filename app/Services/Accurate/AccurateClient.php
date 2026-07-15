<?php

namespace App\Services\Accurate;

use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * HTTP client untuk Accurate Online Open API — metode otorisasi API Token.
 *
 * Konvensi Accurate:
 *   - Otorisasi 2 lapis:
 *       1. account_url (https://account.accurate.id) untuk resolve host via
 *          POST /api/api-token.do → balikin host data usaha (mis. zeus.accurate.id)
 *       2. host data usaha untuk semua request data: https://{host}/accurate/api/...
 *   - Tiap request WAJIB 3 header:
 *       Authorization: Bearer {api_token}
 *       X-Api-Timestamp: {unix detik}        (toleransi ±600 detik)
 *       X-Api-Signature: base64(HMAC-SHA256(timestamp, signature_secret))
 *   - HTTP 401 + {error:invalid_token} = token salah/dicabut.
 *   - HTTP 308 = host data usaha pindah → ikuti Location (re-sign tiap hop).
 *   - Rate limit: maks 8 call/detik per token.
 *
 * Signature Secret SHARED untuk semua data usaha; api_token beda per data usaha
 * (dilewatkan sebagai argumen, BUKAN dari config).
 *
 * Pemakaian:
 *   $client = app(AccurateClient::class);
 *   $info = $client->resolveHost($token);            // ['host'=>.., 'id'=>.., 'alias'=>..]
 *   $resp = $client->get($info['host'], '/api/glaccount/list.do', $token, ['fields'=>'id,no,name']);
 */
class AccurateClient
{
    private string $accountUrl;
    private string $signatureSecret;
    private int $timeoutSeconds;
    private int $retryAttempts;
    private int $retryDelayMs;
    private int $minIntervalMs; // jeda minimum antar call (throttle)

    /** microtime float call terakhir — untuk throttle 8/detik. */
    private ?float $lastCallAt = null;

    private const MAX_REDIRECT_HOPS = 3;

    public function __construct()
    {
        $this->accountUrl      = rtrim((string) config('accurate.account_url'), '/');
        $this->signatureSecret = (string) config('accurate.credentials.signature_secret');
        $this->timeoutSeconds  = (int) config('accurate.timeout_seconds', 60);
        $this->retryAttempts   = (int) config('accurate.retry.attempts', 3);
        $this->retryDelayMs    = (int) config('accurate.retry.delay_ms', 2000);

        $maxPerSecond          = max(1, (int) config('accurate.rate_limit.max_per_second', 7));
        $this->minIntervalMs   = (int) ceil(1000 / $maxPerSecond);

        if ($this->signatureSecret === '') {
            throw new RuntimeException(
                'Accurate signature secret not configured. Set ACCURATE_SIGNATURE_SECRET in .env.'
            );
        }
    }

    // ────────────────────────────────────────────────────────────────
    // Lapis 1: resolve host data usaha via /api/api-token.do
    // ────────────────────────────────────────────────────────────────

    /**
     * Resolve host + identitas data usaha dari sebuah API Token.
     *
     * @return array{host:string,id:?int,alias:?string,licenseEnd:?string,trial:?bool}
     * @throws RuntimeException kalau token invalid / gagal.
     */
    public function resolveHost(string $token): array
    {
        $resp = $this->send('POST', $this->accountUrl . '/api/api-token.do', $token);

        if (! $resp->isSuccess()) {
            throw new RuntimeException(
                'Resolve host gagal: ' . ($resp->error ?? 'unknown') . ' (HTTP ' . $resp->httpStatus . ')'
            );
        }

        // Shape baru: d.database ; shape lama (dok PDF): d["data usaha"].
        $d  = $resp->raw['d'] ?? [];
        $du = $d['database'] ?? $d['data usaha'] ?? $d['dataUsaha'] ?? null;

        if (! is_array($du) || empty($du['host'])) {
            throw new RuntimeException('Resolve host: field host tidak ditemukan di response.');
        }

        return [
            'host'       => rtrim((string) $du['host'], '/'),
            'id'         => isset($du['id']) ? (int) $du['id'] : null,
            'alias'      => $du['alias'] ?? null,
            'licenseEnd' => $du['license']['licenseEnd'] ?? null,
            'trial'      => $du['license']['trial'] ?? null,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // Lapis 2: request data ke host data usaha
    // ────────────────────────────────────────────────────────────────

    public function get(string $host, string $endpoint, string $token, array $query = []): AccurateResponse
    {
        $url = rtrim($host, '/') . '/accurate' . $this->normalizeEndpoint($endpoint);
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        return $this->send('GET', $url, $token);
    }

    public function post(string $host, string $endpoint, string $token, array $body = []): AccurateResponse
    {
        $url = rtrim($host, '/') . '/accurate' . $this->normalizeEndpoint($endpoint);
        return $this->send('POST', $url, $token, $body);
    }

    // ────────────────────────────────────────────────────────────────
    // Core HTTP: signature, throttle, retry, follow 308 manual
    // ────────────────────────────────────────────────────────────────

    private function send(string $method, string $url, string $token, ?array $jsonBody = null): AccurateResponse
    {
        $startedAt = microtime(true);
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $http = $this->dispatch($method, $url, $token, $jsonBody);

                // Follow 308/307 (host data usaha pindah) — re-sign tiap hop.
                $hops = 0;
                while (in_array($http->status(), [307, 308], true) && $hops < self::MAX_REDIRECT_HOPS) {
                    $location = $http->header('Location');
                    if ($location === '' || $location === null) {
                        break;
                    }
                    Log::channel('accurate')->warning('Host pindah (HTTP '.$http->status().'), follow Location', [
                        'from' => $url, 'to' => $location,
                    ]);
                    $url  = $location;
                    $http = $this->dispatch($method, $url, $token, $jsonBody);
                    $hops++;
                }

                $parsed = $this->parseResponse($http, $url);

                $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
                Log::channel('accurate')->info('Accurate API call', [
                    'method'      => $method,
                    'url'         => $this->scrubUrl($url),
                    'http_status' => $parsed->httpStatus,
                    'success'     => $parsed->success,
                    'rows'        => count($parsed->data),
                    'error'       => $parsed->error,
                    'duration_ms' => $durationMs,
                    'attempt'     => $attempt,
                ]);

                // Auth failure tidak akan sembuh dengan retry — langsung balikin.
                return $parsed;
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::channel('accurate')->warning('HTTP error, retrying', [
                    'url'     => $this->scrubUrl($url),
                    'attempt' => $attempt,
                    'type'    => get_class($e),
                    'error'   => $e->getMessage(),
                ]);
                if ($attempt < $this->retryAttempts) {
                    usleep($this->retryDelayMs * 1000);
                }
            }
        }

        Log::channel('accurate')->error('Accurate API call gagal setelah retry', [
            'url'   => $this->scrubUrl($url),
            'error' => $lastException ? $lastException->getMessage() : 'Unknown',
        ]);

        return new AccurateResponse(
            success:    false,
            data:       [],
            error:      $lastException ? $lastException->getMessage() : 'Connection failed',
            httpStatus: null,
            endpoint:   $this->scrubUrl($url),
        );
    }

    /** Kirim satu HTTP request bertanda tangan (tanpa retry/redirect). */
    private function dispatch(string $method, string $url, string $token, ?array $jsonBody): HttpResponse
    {
        $this->throttle();

        $timestamp = (string) time();
        $signature = base64_encode(hash_hmac('sha256', $timestamp, $this->signatureSecret, true));

        $request = Http::withHeaders([
            'Authorization'   => 'Bearer ' . $token,
            'X-Api-Timestamp' => $timestamp,
            'X-Api-Signature' => $signature,
        ])
            ->timeout($this->timeoutSeconds)
            // Kita follow 308 manual (re-sign), jadi matikan auto-redirect Guzzle.
            ->withOptions(['allow_redirects' => false]);

        $this->lastCallAt = microtime(true);

        if (strtoupper($method) === 'POST') {
            return $request->asJson()->post($url, $jsonBody ?? []);
        }
        return $request->get($url);
    }

    private function parseResponse(HttpResponse $http, string $url): AccurateResponse
    {
        $body = $http->json();

        if (! is_array($body)) {
            return new AccurateResponse(
                success:    false,
                data:       [],
                error:      'Invalid JSON response',
                httpStatus: $http->status(),
                endpoint:   $this->scrubUrl($url),
            );
        }

        // Bentuk error auth: { error, error_description }
        if (isset($body['error'])) {
            return new AccurateResponse(
                success:    false,
                data:       [],
                error:      (string) ($body['error_description'] ?? $body['error']),
                httpStatus: $http->status(),
                endpoint:   $this->scrubUrl($url),
                raw:        $body,
            );
        }

        $success = (bool) ($body['s'] ?? false);
        $rawD    = $body['d'] ?? [];
        $data    = is_array($rawD) ? $rawD : [$rawD];
        $sp      = $body['sp'] ?? [];

        // Saat s=false, "d" biasanya array pesan error.
        $error = null;
        if (! $success) {
            $error = is_array($rawD) ? implode('; ', array_map('strval', $rawD)) : (string) $rawD;
            $error = $error !== '' ? $error : 'Request gagal (s=false)';
        }

        return new AccurateResponse(
            success:    $success,
            data:       $success ? $data : [],
            record:     isset($body['r']) && is_array($body['r']) ? $body['r'] : null,
            page:       isset($sp['page']) ? (int) $sp['page'] : null,
            pageCount:  isset($sp['pageCount']) ? (int) $sp['pageCount'] : null,
            rowCount:   isset($sp['rowCount']) ? (int) $sp['rowCount'] : null,
            error:      $error,
            httpStatus: $http->status(),
            endpoint:   $this->scrubUrl($url),
            raw:        $body,
        );
    }

    /** Tahan eksekusi supaya tidak melebihi batas call/detik. */
    private function throttle(): void
    {
        if ($this->lastCallAt === null) {
            return;
        }
        $elapsedMs   = (microtime(true) - $this->lastCallAt) * 1000;
        $remainingMs = $this->minIntervalMs - $elapsedMs;
        if ($remainingMs > 0) {
            usleep((int) ($remainingMs * 1000));
        }
    }

    private function normalizeEndpoint(string $endpoint): string
    {
        return '/' . ltrim($endpoint, '/');
    }

    /** Buang query string dari URL untuk log (hindari bocor param sensitif). */
    private function scrubUrl(string $url): string
    {
        return explode('?', $url, 2)[0];
    }
}
