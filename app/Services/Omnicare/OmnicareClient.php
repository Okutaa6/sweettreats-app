<?php

namespace App\Services\Omnicare;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * HTTP client untuk Omnicare (Lokadok) Open API.
 *
 * Konvensi Lokadok:
 *   - Semua endpoint POST, JSON body untuk credentials
 *   - Query string untuk filter (place, start, end, per_page, dll.)
 *   - HTTP status selalu 200; status real ada di body.metadata.code
 *   - Cooldown 10 detik antar call ke sales endpoints
 *
 * Pemakaian:
 *   $client = app(OmnicareClient::class);
 *   $resp = $client->getLocation();
 *   if ($resp->isSuccess()) { foreach ($resp->data as $branch) {...} }
 *
 * Untuk pagination:
 *   foreach ($client->paginate('/get/sales_detail', ['place' => '29958']) as $page) {
 *       foreach ($page->data as $row) { ... }
 *   }
 */
class OmnicareClient
{
    private string $baseUrl;
    private array $credentials;
    private int $cooldownMs;
    private array $salesEndpoints;
    private int $timeoutSeconds;
    private int $retryAttempts;
    private int $retryDelayMs;
    private int $pageSize;

    /**
     * Timestamp (microtime float) of last call to a sales endpoint.
     * Used for cooldown enforcement.
     */
    private ?float $lastSalesCallAt = null;

    public function __construct()
    {
        $this->baseUrl        = rtrim((string) config('omnicare.base_url'), '/');
        $this->credentials    = config('omnicare.credentials', []);
        $this->cooldownMs     = (int) config('omnicare.cooldown_ms', 11000);
        $this->salesEndpoints = config('omnicare.sales_endpoints', []);
        $this->timeoutSeconds = (int) config('omnicare.timeout_seconds', 60);
        $this->retryAttempts  = (int) config('omnicare.retry.attempts', 3);
        $this->retryDelayMs   = (int) config('omnicare.retry.delay_ms', 2000);
        $this->pageSize       = (int) config('omnicare.sync.page_size', 100);

        if (empty($this->credentials['username']) || empty($this->credentials['password'])) {
            throw new RuntimeException(
                'Omnicare credentials not configured. Set OMNICARE_USERNAME and OMNICARE_PASSWORD in .env.'
            );
        }
    }

    // ────────────────────────────────────────────────────────────────
    // Endpoint methods — high-level wrappers for each Lokadok endpoint
    // ────────────────────────────────────────────────────────────────

    public function getLocation(): OmnicareResponse
    {
        return $this->post('/get/location');
    }

    public function getDoctorList(string $placeId): OmnicareResponse
    {
        return $this->post('/get/doctor_list', ['place' => $placeId]);
    }

    public function getPolyclinicList(string $placeId): OmnicareResponse
    {
        return $this->post('/get/polyclinic_list', ['place' => $placeId]);
    }

    public function getAllPatients(string $placeId, int $page = 1, ?int $lastId = null): OmnicareResponse
    {
        $params = ['place' => $placeId, 'per_page' => $page];
        if ($lastId !== null) {
            $params['last_id'] = $lastId;
        }
        return $this->post('/get/all_patients', $params);
    }

    public function getPatient(string $placeId, string $pid): OmnicareResponse
    {
        return $this->post('/get/patient', ['place' => $placeId, 'pid' => $pid]);
    }

    public function findPatientByMobileDob(string $placeId, string $mobile, string $dob): OmnicareResponse
    {
        return $this->post('/get/find_patient_by_mobile_dob', [
            'place'   => $placeId,
            'pmobile' => $mobile,
            'pdob'    => $dob,
        ]);
    }

    public function getAllMedicalRecords(
        string $placeId,
        string $startYmd,
        string $endYmd,
        int $page = 1,
        ?int $lastId = null
    ): OmnicareResponse {
        $params = [
            'place'    => $placeId,
            'start'    => $startYmd,
            'end'      => $endYmd,
            'per_page' => $page,
        ];
        if ($lastId !== null) {
            $params['last_id'] = $lastId;
        }
        return $this->post('/get/all_medical_records', $params);
    }

    public function getSalesDetail(
        string $placeId,
        string $startYmd,
        string $endYmd,
        int $page = 1,
        ?string $paymentStatus = null
    ): OmnicareResponse {
        $params = [
            'place'    => $placeId,
            'start'    => $startYmd,
            'end'      => $endYmd,
            'per_page' => $page,
        ];
        if ($paymentStatus !== null) {
            $params['payment_status'] = $paymentStatus;
        }
        return $this->post('/get/sales_detail', $params);
    }

    public function getProductSales(
        string $placeId,
        string $startYmd,
        string $endYmd,
        int $page = 1
    ): OmnicareResponse {
        return $this->post('/get/product_sales', [
            'place'    => $placeId,
            'start'    => $startYmd,
            'end'      => $endYmd,
            'per_page' => $page,
        ]);
    }

    public function getTimeSlots(
        string $placeId,
        string $docId,
        ?string $startYmd = null,
        ?int $span = null
    ): OmnicareResponse {
        $params = ['place' => $placeId, 'doc_id' => $docId];
        if ($startYmd !== null) {
            $params['start'] = $startYmd;
        }
        if ($span !== null) {
            $params['span'] = $span;
        }
        return $this->post('/get/time_slots', $params);
    }

    // ────────────────────────────────────────────────────────────────
    // Pagination iterator — yields OmnicareResponse per page
    // ────────────────────────────────────────────────────────────────

    /**
     * Iterate through all pages of a paginated endpoint.
     *
     * Yields OmnicareResponse for each page. Stops when metadata.pages is
     * reached or page returns isEmpty/error.
     *
     * @param  string  $endpoint  e.g. '/get/sales_detail'
     * @param  array   $baseParams (place, start, end, etc — NOT per_page)
     * @param  callable|null  $progress  optional callback(int $page, int $totalPages, int $rowsThisPage)
     * @return \Generator<int, OmnicareResponse>
     */
    public function paginate(string $endpoint, array $baseParams, ?callable $progress = null): \Generator
    {
        $page = 1;
        $totalPages = 1;

        do {
            $params = $baseParams + ['per_page' => $page];
            $response = $this->post($endpoint, $params);

            // Empty response is normal end-of-data, not error
            if ($response->isEmpty()) {
                break;
            }

            // Real errors stop pagination
            if (! $response->isSuccess()) {
                yield $response;
                break;
            }

            $totalPages = $response->pages ?? 1;
            $rowsThisPage = count($response->data);

            if ($progress !== null) {
                $progress($page, $totalPages, $rowsThisPage);
            }

            yield $response;

            $page++;
        } while ($page <= $totalPages);
    }

    // ────────────────────────────────────────────────────────────────
    // Core HTTP method
    // ────────────────────────────────────────────────────────────────

    /**
     * POST to Omnicare endpoint with credentials in body, params in query string.
     * Handles cooldown enforcement for sales endpoints and retry on connection errors.
     */
    private function post(string $endpoint, array $queryParams = []): OmnicareResponse
    {
        $endpoint = '/' . ltrim($endpoint, '/');
        $isSalesEndpoint = in_array($endpoint, $this->salesEndpoints, true);

        if ($isSalesEndpoint) {
            $this->enforceCooldown();
        }

        $url = $this->baseUrl . $endpoint;
        $startedAt = microtime(true);

        $lastException = null;

        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $httpResponse = Http::timeout($this->timeoutSeconds)
                    ->asJson()
                    ->post($url . '?' . http_build_query($queryParams), $this->credentials);

                if ($isSalesEndpoint) {
                    $this->lastSalesCallAt = microtime(true);
                }

                $parsed = $this->parseResponse($httpResponse, $endpoint, $queryParams);

                // 402 cooldown means we hit rate limit despite enforcement.
                // Wait full cooldown and retry.
                if ($parsed->isCooldown() && $attempt < $this->retryAttempts) {
                    Log::channel('omnicare')->warning('Cooldown hit, retrying', [
                        'endpoint' => $endpoint,
                        'attempt'  => $attempt,
                    ]);
                    usleep($this->cooldownMs * 1000);
                    continue;
                }

                // 598 = Invalid JSON response (server returned HTML/garbage).
                // Hampir selalu transient: gateway timeout, 502/503 di balik
                // proxy, atau overload sementara. Retry dengan backoff cegah
                // silent drop yang dulu hilangkan data Kalideres 24 bulan.
                if ($parsed->code === 598 && $attempt < $this->retryAttempts) {
                    Log::channel('omnicare')->warning('Invalid JSON, retrying', [
                        'endpoint'    => $endpoint,
                        'params'      => $queryParams,
                        'http_status' => $parsed->httpStatus,
                        'attempt'     => $attempt,
                    ]);
                    usleep($this->retryDelayMs * 1000);
                    continue;
                }

                $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
                Log::channel('omnicare')->info('Omnicare API call', [
                    'endpoint'    => $endpoint,
                    'params'      => $queryParams,
                    'code'        => $parsed->code,
                    'rows'        => count($parsed->data),
                    'pages'       => $parsed->pages,
                    'duration_ms' => $durationMs,
                    'attempt'     => $attempt,
                ]);

                return $parsed;
            } catch (\Throwable $e) {
                // Sebelumnya hanya catch ConnectionException — bocor untuk
                // DNS error / SSL handshake / Guzzle RequestException, dll.
                // Itu bikin sync_log stuck 'running' di prod (insiden 04 Jun 2026
                // — doctor_list crash semua 5 cabang, halt seluruh sync chain).
                // Broaden ke Throwable supaya semua HTTP-related error ter-retry
                // dan akhirnya return OmnicareResponse 599 yang ter-handle sync.
                $lastException = $e;
                $type = $e instanceof ConnectionException ? 'connection' : get_class($e);
                Log::channel('omnicare')->warning('HTTP error, retrying', [
                    'endpoint' => $endpoint,
                    'attempt'  => $attempt,
                    'type'     => $type,
                    'error'    => $e->getMessage(),
                ]);
                if ($attempt < $this->retryAttempts) {
                    usleep($this->retryDelayMs * 1000);
                }
            }
        }

        Log::channel('omnicare')->error('Omnicare API call failed after retries', [
            'endpoint' => $endpoint,
            'error'    => $lastException ? $lastException->getMessage() : 'Unknown',
        ]);

        return new OmnicareResponse(
            code:        599,
            message:     $lastException ? $lastException->getMessage() : 'Connection failed',
            data:        [],
            pages:       null,
            httpStatus:  null,
            endpoint:    $endpoint,
            queryParams: $queryParams,
        );
    }

    /**
     * Parse Laravel HTTP response into OmnicareResponse value object.
     */
    private function parseResponse(HttpResponse $httpResponse, string $endpoint, array $queryParams): OmnicareResponse
    {
        $body = $httpResponse->json();

        if (! is_array($body)) {
            return new OmnicareResponse(
                code:        598,
                message:     'Invalid JSON response',
                data:        [],
                pages:       null,
                httpStatus:  $httpResponse->status(),
                endpoint:    $endpoint,
                queryParams: $queryParams,
            );
        }

        $metadata = $body['metadata'] ?? [];
        $code     = (int) ($metadata['code'] ?? 0);
        $message  = (string) ($metadata['message'] ?? '');
        $pages    = isset($metadata['pages']) ? (int) $metadata['pages'] : null;
        $raw      = $body['response'] ?? [];

        // response can be array-of-objects (sales, patients) or object (polyclinic list).
        // Normalize to array of items.
        $data = is_array($raw) ? $raw : [];

        return new OmnicareResponse(
            code:        $code,
            message:     $message,
            data:        $data,
            pages:       $pages,
            httpStatus:  $httpResponse->status(),
            endpoint:    $endpoint,
            queryParams: $queryParams,
        );
    }

    /**
     * Sleep until cooldown_ms has elapsed since last sales endpoint call.
     */
    private function enforceCooldown(): void
    {
        if ($this->lastSalesCallAt === null) {
            return;
        }

        $elapsedMs = (microtime(true) - $this->lastSalesCallAt) * 1000;
        $remainingMs = $this->cooldownMs - $elapsedMs;

        if ($remainingMs > 0) {
            usleep((int) ($remainingMs * 1000));
        }
    }
}
