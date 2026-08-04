<?php

namespace App\Console\Commands;

use App\Services\Accurate\AccurateClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pull Chart of Accounts (glaccount) dari Accurate ke accurate_staging_glaccount.
 *
 * Fase testing PULL-ONLY. CoA = fondasi mapping akun (unknown #2). Read-only,
 * aman. Pagination via sp.page/sp.pageCount. Audit ke accurate_sync_log.
 *
 *   php artisan accurate:pull-glaccount --all
 *   php artisan accurate:pull-glaccount --slug=holding
 */
class AccuratePullGlaccount extends Command
{
    protected $signature = 'accurate:pull-glaccount
                            {--slug= : Pull satu Data Usaha saja}
                            {--all : Pull semua Data Usaha aktif}';

    protected $description = 'Tarik Chart of Accounts Accurate ke staging (per Data Usaha)';

    private const ENDPOINT = '/api/glaccount/list.do';
    private const FIELDS    = 'id,no,name,accountType,currency,balance,suspended';
    private const PAGE_SIZE = 100;

    public function handle(AccurateClient $client): int
    {
        $slug = $this->option('slug');
        if (! $slug && ! $this->option('all')) {
            $this->error('Pilih --slug=<nama> atau --all.');
            return self::FAILURE;
        }

        $query = DB::table('accurate_credentials')->where('is_active', 1)->whereNotNull('api_token');
        if ($slug) {
            $query->where('slug', $slug);
        }
        $creds = $query->orderBy('slug')->get();

        if ($creds->isEmpty()) {
            $this->error('Tidak ada kredensial dengan token yang cocok.');
            return self::FAILURE;
        }

        $triggeredBy = $this->option('no-interaction') ? 'cron' : 'manual';
        $hadError = false;

        foreach ($creds as $c) {
            $this->line("→ {$c->slug} ({$c->company_name})");

            $host = $this->ensureHost($client, $c);
            if (! $host) {
                $this->error("  host gagal di-resolve, skip.");
                $hadError = true;
                continue;
            }

            $logId = DB::table('accurate_sync_log')->insertGetId([
                'endpoint'     => self::ENDPOINT,
                'slug'         => $c->slug,
                'status'       => 'running',
                'triggered_by' => $triggeredBy,
                'started_at'   => now(),
            ]);

            try {
                [$records, $pages, $httpStatus] = $this->pull($client, $host, $c);

                DB::table('accurate_sync_log')->where('id', $logId)->update([
                    'status'        => 'success',
                    'records_count' => $records,
                    'pages_fetched' => $pages,
                    'http_status'   => $httpStatus,
                    'finished_at'   => now(),
                ]);
                $this->info("  OK — {$records} akun, {$pages} halaman.");
            } catch (\Throwable $e) {
                DB::table('accurate_sync_log')->where('id', $logId)->update([
                    'status'        => 'error',
                    'error_message' => mb_substr($e->getMessage(), 0, 1000),
                    'finished_at'   => now(),
                ]);
                $this->error('  ERROR — ' . $e->getMessage());
                $hadError = true;
            }
        }

        return $hadError ? self::FAILURE : self::SUCCESS;
    }

    /** Pastikan host ada; resolve kalau kosong. Return host atau null. */
    private function ensureHost(AccurateClient $client, object $c): ?string
    {
        if (! empty($c->host)) {
            return $c->host;
        }
        try {
            $info = $client->resolveHost($c->api_token);
            DB::table('accurate_credentials')->where('id', $c->id)->update([
                'host'            => $info['host'],
                'db_numeric_id'   => $info['id'],
                'host_checked_at' => now(),
                'updated_at'      => now(),
            ]);
            return $info['host'];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Paginate glaccount, upsert ke staging.
     * @return array{0:int,1:int,2:?int} [records, pages, lastHttpStatus]
     */
    private function pull(AccurateClient $client, string $host, object $c): array
    {
        $page = 1;
        $pageCount = 1;
        $records = 0;
        $httpStatus = null;

        do {
            $resp = $client->get($host, self::ENDPOINT, $c->api_token, [
                'fields'      => self::FIELDS,
                'sp.page'     => $page,
                'sp.pageSize' => self::PAGE_SIZE,
            ]);
            $httpStatus = $resp->httpStatus;

            if (! $resp->isSuccess()) {
                throw new \RuntimeException($resp->error ?? 'Request gagal (HTTP ' . $resp->httpStatus . ')');
            }

            $now = now();
            foreach ($resp->data as $row) {
                if (! isset($row['id'])) {
                    continue;
                }
                DB::table('accurate_staging_glaccount')->updateOrInsert(
                    ['slug' => $c->slug, 'accurate_id' => (int) $row['id']],
                    [
                        'no'             => $row['no'] ?? null,
                        'name'           => $row['name'] ?? null,
                        'account_type'   => $row['accountType'] ?? null,
                        'currency'       => is_array($row['currency'] ?? null)
                                                ? ($row['currency']['code'] ?? null)
                                                : ($row['currency'] ?? null),
                        'balance'        => isset($row['balance']) ? (float) $row['balance'] : null,
                        'suspended'      => isset($row['suspended']) ? (int) (bool) $row['suspended'] : null,
                        'parent_id'      => $row['parentId'] ?? null,
                        'raw_payload'    => json_encode($row, JSON_UNESCAPED_UNICODE),
                        'last_synced_at' => $now,
                    ]
                );
                $records++;
            }

            $pageCount = $resp->pageCount ?? 1;
            $page++;
        } while ($page <= $pageCount);

        return [$records, $pageCount, $httpStatus];
    }
}
