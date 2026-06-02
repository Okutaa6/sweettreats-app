<?php

namespace App\Console\Commands;

use App\Services\Omnicare\OmnicareClient;
use App\Services\Omnicare\OmnicareResponse;
use App\Services\Omnicare\PayloadNormalizer as N;
use App\Support\DoctorNameMatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sync data dari Omnicare ke mirror tables di SweetTreats.
 *
 *   php artisan omnicare:sync                              # full sync semua branch aktif
 *   php artisan omnicare:sync --place=29958                # 1 cabang (pilot Sunter)
 *   php artisan omnicare:sync --endpoint=patient           # 1 endpoint saja
 *   php artisan omnicare:sync --days=30                    # range lebih panjang
 *   php artisan omnicare:sync --start=20260301 --end=20260331  # custom range
 *   php artisan omnicare:sync --dry-run                    # no DB writes, log only
 *
 * Daily cron schedule (di Kernel.php): jalan 02:00 dengan default args (3 hari overlap).
 */
class SyncOmnicare extends Command
{
    protected $signature = 'omnicare:sync
                            {--place= : Place ID (default: semua cabang aktif dari config)}
                            {--endpoint= : location|polyclinic|doctor|patient|sales|product (default: all)}
                            {--days=3 : Lookback days untuk sales/product (default 3)}
                            {--start= : Override start date YYYYMMDD (e.g. backfill)}
                            {--end= : Override end date YYYYMMDD}
                            {--dry-run : Tidak tulis ke DB, hanya log}';

    protected $description = 'Pull data Omnicare ke local mirror tables (sales, patient, doctor, location)';

    private OmnicareClient $client;
    private bool $dryRun = false;

    public function handle(OmnicareClient $client): int
    {
        $this->client = $client;
        $this->dryRun = (bool) $this->option('dry-run');

        $endpoint    = $this->option('endpoint'); // null = all
        $placeFilter = $this->option('place');
        $days        = max(1, (int) $this->option('days'));

        $startYmd = (string) ($this->option('start') ?: now()->subDays($days - 1)->format('Ymd'));
        $endYmd   = (string) ($this->option('end') ?: now()->format('Ymd'));

        $this->line('');
        $this->line(str_repeat('═', 64));
        $this->info('Omnicare Sync — ' . now()->format('Y-m-d H:i:s'));
        $this->line(str_repeat('═', 64));

        if ($this->dryRun) {
            $this->warn('▶ DRY RUN — no DB writes will be performed');
        }
        $this->line("▶ Date range (sales/product): {$startYmd} → {$endYmd}");

        // Sync /get/location always first (kasih kita updated branch list)
        if ($endpoint === null || $endpoint === 'location') {
            $this->syncLocation();
        }

        $places = $this->resolvePlaces($placeFilter);
        $this->line('▶ Branches in scope: ' . implode(', ', $places));
        $this->line(str_repeat('─', 64));

        foreach ($places as $placeId) {
            $this->line('');
            $this->info("◉ Branch {$placeId}");

            try {
                if ($endpoint === null || $endpoint === 'polyclinic') {
                    $this->syncPolyclinic($placeId);
                }
                if ($endpoint === null || $endpoint === 'doctor') {
                    $this->syncDoctorList($placeId);
                }
                if ($endpoint === null || $endpoint === 'patient') {
                    $this->syncPatients($placeId);
                }
                if ($endpoint === null || $endpoint === 'sales') {
                    $this->syncSalesDetail($placeId, $startYmd, $endYmd);
                }
                if ($endpoint === null || $endpoint === 'product') {
                    $this->syncProductSales($placeId, $startYmd, $endYmd);
                }
            } catch (Throwable $e) {
                $this->error("  ✗ Branch {$placeId} threw: {$e->getMessage()}");
                Log::channel('omnicare')->error('Branch sync exception', [
                    'place' => $placeId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->line('');
        $this->line(str_repeat('═', 64));
        $this->info('Sync complete');
        return self::SUCCESS;
    }

    // ────────────────────────────────────────────────────────────────
    // Endpoint sync methods
    // ────────────────────────────────────────────────────────────────

    private function syncLocation(): void
    {
        $this->line('  /get/location');
        $logId = $this->startSyncLog('/get/location', null, []);
        $resp = $this->client->getLocation();

        if (! $resp->isSuccess()) {
            $this->finishSyncLog($logId, 'error', 0, 0, $resp->message);
            $this->error("    ✗ {$resp->code}: {$resp->message}");
            return;
        }

        $count = 0;
        foreach ($resp->data as $b) {
            if (! $this->dryRun) {
                DB::table('omnicare_location')->updateOrInsert(
                    ['id' => (string) $b['id']],
                    [
                        'name'           => N::str($b['name'] ?? null) ?? '?',
                        'address'        => N::str($b['address'] ?? null),
                        'phone'          => N::str($b['phone'] ?? null),
                        'wa_number'      => N::str($b['wa_number'] ?? null),
                        'fax'            => N::str($b['fax'] ?? null),
                        'email'          => N::str($b['email'] ?? null),
                        'type'           => N::str($b['type'] ?? null),
                        'village'        => N::str($b['village'] ?? null),
                        'sub_district'   => N::str($b['sub_district'] ?? null),
                        'city'           => N::str($b['city'] ?? null),
                        'province'       => N::str($b['province'] ?? null),
                        'is_parent'      => N::bool($b['is_parent'] ?? 0),
                        'last_synced_at' => now(),
                        'raw_payload'    => N::jsonPayload($b),
                        'updated_at'     => now(),
                    ]
                );
            }
            $count++;
        }

        $this->finishSyncLog($logId, 'success', $count, 1);
        $this->line("    ✓ {$count} branches");
    }

    private function syncPolyclinic(string $placeId): void
    {
        $this->line('  /get/polyclinic_list');
        $logId = $this->startSyncLog('/get/polyclinic_list', $placeId, []);
        $resp = $this->client->getPolyclinicList($placeId);

        if ($resp->isEmpty()) {
            $this->finishSyncLog($logId, 'success', 0, 1);
            $this->line('    ✓ 0 polyclinics (empty)');
            return;
        }
        if (! $resp->isSuccess()) {
            $this->finishSyncLog($logId, 'error', 0, 0, $resp->message);
            $this->error("    ✗ {$resp->code}: {$resp->message}");
            return;
        }

        // Polyclinic response is flat object: {poly_id: name, ...}
        // OmnicareClient normalizes to array, tapi key-value mapping hilang.
        // Pakai raw json untuk re-extract.
        $count = 0;
        // Reload via direct call to get raw mapping
        // Simpler: iterate response (if structured as object → array_values, kita kehilangan key)
        // Better approach: re-parse from data.
        // Note: response.data dari OmnicareResponse adalah array; kalau key string, PHP preserve them.
        foreach ($resp->data as $polyId => $polyName) {
            if (! is_string($polyName)) {
                // If data is array of objects instead of key-value, fallback
                $polyId = $polyName['poly_id'] ?? null;
                $polyName = $polyName['name'] ?? null;
            }
            if ($polyId === null || $polyName === null) {
                continue;
            }
            if (! $this->dryRun) {
                DB::table('omnicare_polyclinic')->updateOrInsert(
                    ['poly_id' => (string) $polyId, 'place_id' => $placeId],
                    [
                        'name'           => (string) $polyName,
                        'last_synced_at' => now(),
                        'updated_at'     => now(),
                    ]
                );
            }
            $count++;
        }

        $this->finishSyncLog($logId, 'success', $count, 1);
        $this->line("    ✓ {$count} polyclinics");
    }

    private function syncDoctorList(string $placeId): void
    {
        $this->line('  /get/doctor_list');
        $logId = $this->startSyncLog('/get/doctor_list', $placeId, []);
        $resp = $this->client->getDoctorList($placeId);

        if ($resp->isEmpty()) {
            $this->finishSyncLog($logId, 'success', 0, 1);
            $this->line('    ✓ 0 doctors (empty)');
            return;
        }
        if (! $resp->isSuccess()) {
            $this->finishSyncLog($logId, 'error', 0, 0, $resp->message);
            $this->error("    ✗ {$resp->code}: {$resp->message}");
            return;
        }

        $count = 0;
        $autoLinked = 0;
        foreach ($resp->data as $d) {
            $docId = N::str($d['doc_id'] ?? null);
            $docName = N::str($d['doc_name'] ?? null);
            if ($docId === null || $docName === null) {
                continue;
            }

            if (! $this->dryRun) {
                DB::table('omnicare_doctor')->updateOrInsert(
                    ['doc_id' => $docId, 'place_id' => $placeId],
                    [
                        'doc_name'             => $docName,
                        'gender'               => N::str($d['gender'] ?? null),
                        'poly_id'              => N::str($d['poly_id'] ?? null),
                        'poly_name'            => N::str($d['poly_name'] ?? null),
                        'specialty'            => N::str($d['specialty'] ?? null),
                        'email'                => N::str($d['email'] ?? null),
                        'mobile_number'        => N::phone($d['mobile_number'] ?? null),
                        'session_duration'    => N::int($d['session_duration'] ?? 0),
                        'prefix_queue'         => N::str($d['prefix_queue'] ?? null),
                        'room_name'            => N::str($d['room_name'] ?? null),
                        'book_online'          => N::bool($d['book_online'] ?? 0),
                        'online_consultation'  => N::bool($d['online_consultation'] ?? 0),
                        'receive_comission'    => N::bool($d['receive_comission'] ?? 0),
                        'duty_period'          => N::jsonPayload($d['duty_period'] ?? null),
                        'last_synced_at'       => now(),
                        'raw_payload'          => N::jsonPayload($d),
                        'updated_at'           => now(),
                    ]
                );

                // Auto-populate raw_doctor.omnicare_doc_id via fuzzy DoctorNameMatcher.
                // Hanya update kalau column masih NULL — never overwrite manual mapping.
                // Skip kalau ada ambiguity (multiple SweetTreats doctors match) — leave manual.
                $candidates = DB::table('raw_doctor')
                    ->whereNull('omnicare_doc_id')
                    ->pluck('name', 'id')
                    ->toArray();

                $best = DoctorNameMatcher::bestMatch($docName, $candidates);
                if ($best !== null) {
                    DB::table('raw_doctor')
                        ->where('id', $best['id'])
                        ->whereNull('omnicare_doc_id')
                        ->update(['omnicare_doc_id' => $docId]);
                    $autoLinked++;
                }
            }
            $count++;
        }

        $this->finishSyncLog($logId, 'success', $count, 1);
        $msg = "    ✓ {$count} doctors";
        if ($autoLinked > 0) {
            $msg .= " (auto-linked {$autoLinked} to raw_doctor)";
        }
        $this->line($msg);
    }

    private function syncPatients(string $placeId): void
    {
        $this->line('  /get/all_patients (paginated)');
        $logId = $this->startSyncLog('/get/all_patients', $placeId, []);
        $totalCount = 0;
        $pageCount = 0;

        try {
            foreach ($this->client->paginate('/get/all_patients', ['place' => $placeId]) as $resp) {
                if (! $resp->isSuccess()) {
                    if (! $resp->isEmpty()) {
                        $this->finishSyncLog($logId, 'error', $totalCount, $pageCount, $resp->message);
                        $this->error("    ✗ Page {$pageCount}: {$resp->code} {$resp->message}");
                        return;
                    }
                    break;
                }

                foreach ($resp->data as $p) {
                    $pid = N::str($p['pid'] ?? null);
                    if ($pid === null) {
                        continue;
                    }

                    if (! $this->dryRun) {
                        $firstName = N::str($p['first_name'] ?? null);
                        $lastName = N::str($p['last_name'] ?? null);
                        $fullName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));

                        DB::table('omnicare_patient')->updateOrInsert(
                            ['pid' => $pid],
                            [
                                'last_id'                   => N::str($p['last_id'] ?? null),
                                'old_pid'                   => N::str($p['old_pid'] ?? null),
                                'first_name'                => $firstName,
                                'last_name'                 => $lastName,
                                'full_name'                 => $fullName !== '' ? $fullName : null,
                                'dob'                       => N::dateFromDmy($p['dob'] ?? null),
                                'gender'                    => N::str($p['gender'] ?? null),
                                'marital_status'            => N::str($p['marital_status'] ?? null),
                                'mobile_number'             => N::phone($p['mobile_number'] ?? null),
                                'whatsapp_number'           => N::phone($p['whatsapp_number'] ?? null),
                                'home_phone'                => N::phone($p['home_phone'] ?? null),
                                'email'                     => N::str($p['patient_email'] ?? null),
                                'home_address'              => N::str($p['home_address'] ?? null),
                                'village'                   => N::str($p['village'] ?? null),
                                'sub_district'              => N::str($p['sub_district'] ?? null),
                                'city'                      => N::str($p['city'] ?? null),
                                'province'                  => N::str($p['province'] ?? null),
                                'zipcode'                   => N::str($p['zipcode'] ?? null),
                                'nationality'               => N::str($p['nationality'] ?? null),
                                'ethnicity'                 => N::str($p['ethnicity'] ?? null),
                                'religion'                  => N::str($p['religion'] ?? null),
                                'occupation'                => N::str($p['occupation'] ?? null),
                                'blood_type'                => N::str($p['blood_type'] ?? null),
                                'identification_num'        => N::str($p['identification_num'] ?? null),
                                'identification_type'       => N::str($p['identification_type'] ?? null),
                                'emergency_contact'         => N::str($p['emergency_contact'] ?? null),
                                'emergency_phone'           => N::phone($p['emergency_phone'] ?? null),
                                'membership_id'             => N::str($p['membership_id'] ?? null),
                                'prime_ins_plan_id'         => N::str($p['prime_ins_plan_id'] ?? null),
                                'prime_ins_policy_number'   => N::str($p['prime_ins_policy_number'] ?? null),
                                'calculated_final_point'    => N::int($p['calculated_final_point'] ?? 0),
                                'last_visit_cal_id'         => N::str($p['last_visit_cal_id'] ?? null),
                                'last_visit_pp'             => N::str($p['last_visit_pp'] ?? null),
                                'update_timestamp'          => N::unixMs($p['update_timestamp'] ?? 0),
                                'last_synced_at'            => now(),
                                'raw_payload'               => N::jsonPayload($p),
                                'updated_at'                => now(),
                            ]
                        );

                        // Junction row: track patient seen at this branch.
                        // INSERT IGNORE behavior via insertOrIgnore.
                        DB::table('omnicare_patient_branch')->updateOrInsert(
                            ['pid' => $pid, 'place_id' => $placeId],
                            ['last_seen_at' => now()]
                        );
                    }
                    $totalCount++;
                }
                $pageCount++;
            }
        } catch (Throwable $e) {
            $this->finishSyncLog($logId, 'error', $totalCount, $pageCount, $e->getMessage());
            throw $e;
        }

        $this->finishSyncLog($logId, 'success', $totalCount, $pageCount);
        $this->line("    ✓ {$totalCount} patients (across {$pageCount} pages)");
    }

    private function syncSalesDetail(string $placeId, string $startYmd, string $endYmd): void
    {
        // 31-day cap per Lokadok spec — chunk kalau range lebih besar
        $chunks = $this->dateChunks($startYmd, $endYmd, 31);
        $totalCount = 0;
        $pageCount = 0;
        $rangeLabel = count($chunks) === 1 ? "{$startYmd}-{$endYmd}" : count($chunks) . ' chunks';
        $this->line("  /get/sales_detail ({$rangeLabel})");

        $logId = $this->startSyncLog('/get/sales_detail', $placeId, [
            'start' => $startYmd, 'end' => $endYmd,
        ]);

        try {
            foreach ($chunks as [$chunkStart, $chunkEnd]) {
                foreach ($this->client->paginate('/get/sales_detail', [
                    'place' => $placeId,
                    'start' => $chunkStart,
                    'end'   => $chunkEnd,
                ]) as $resp) {
                    if (! $resp->isSuccess()) {
                        if (! $resp->isEmpty()) {
                            $this->finishSyncLog($logId, 'error', $totalCount, $pageCount, $resp->message);
                            $this->error("    ✗ {$resp->code}: {$resp->message}");
                            return;
                        }
                        break;
                    }

                    foreach ($resp->data as $row) {
                        $trxId = N::str($row['trx_id'] ?? null);
                        if ($trxId === null) {
                            continue;
                        }

                        if (! $this->dryRun) {
                            DB::table('omnicare_sales_detail')->updateOrInsert(
                                ['trx_id' => $trxId],
                                [
                                    'trx_date'           => N::dateFromYmd($row['trx_date'] ?? null) ?? now()->format('Y-m-d'),
                                    'invoice_num'        => N::str($row['invoice_num'] ?? null),
                                    'service_type'       => N::str($row['service_type'] ?? null),
                                    'item_code'          => N::str($row['item_code'] ?? null),
                                    'item_type'          => N::str($row['item_type'] ?? null),
                                    'item_name'          => N::str($row['item_name'] ?? null) ?? '(unknown)',
                                    'item_extra_name'    => N::str($row['item_extra_name'] ?? null),
                                    'qty_sold'           => N::decimal($row['qty_sold'] ?? 0),
                                    'net_sell_price'     => N::decimal($row['net_sell_price'] ?? 0),
                                    'gross_price'        => N::decimal($row['gross_price'] ?? 0),
                                    'discount'           => N::decimal($row['discount'] ?? 0),
                                    'pajak'              => N::decimal($row['pajak'] ?? 0),
                                    'base_price'         => N::decimal($row['base_price'] ?? 0),
                                    'usage_cost'         => N::decimal($row['usage_cost'] ?? 0),
                                    'net_profit'         => N::decimal($row['net_profit'] ?? 0),
                                    'polyclinic'         => N::str($row['polyclinic'] ?? null),
                                    'p_id'               => N::str($row['p_id'] ?? null),
                                    'p_name'             => N::str($row['p_name'] ?? null),
                                    'p_ins_id'           => N::str($row['p_ins_id'] ?? null),
                                    'p_insurance'        => N::str($row['p_insurance'] ?? null),
                                    'p_email'            => N::str($row['p_email'] ?? null),
                                    'redeem_rx'          => N::bool($row['redeem_rx'] ?? false),
                                    'sales_notes'        => N::str($row['sales_notes'] ?? null),
                                    'value_point'        => N::int($row['value_point'] ?? 0),
                                    'point_expired_date' => $this->parsePointExpiry($row['point_expired_date'] ?? null),
                                    'doctor'             => N::str($row['doctor'] ?? null),
                                    'doc_reff_id'        => N::str($row['doc_reff_id'] ?? null),
                                    'checkout_time'      => N::unixMs($row['checkout_time'] ?? 0),
                                    'encounter_id'       => $this->normalizeEncounterId($row['encounter_id'] ?? null),
                                    'place_id'           => $placeId,
                                    'branch_name'        => N::str($row['branch_name'] ?? null),
                                    'username'           => N::str($row['username'] ?? null),
                                    'payment_status'     => N::str($row['payment_status'] ?? null) ?? 'unpaid',
                                    'last_synced_at'     => now(),
                                    'raw_payload'        => N::jsonPayload($row),
                                    'updated_at'         => now(),
                                ]
                            );
                        }
                        $totalCount++;
                    }
                    $pageCount++;
                }
            }
        } catch (Throwable $e) {
            $this->finishSyncLog($logId, 'error', $totalCount, $pageCount, $e->getMessage());
            throw $e;
        }

        $this->finishSyncLog($logId, 'success', $totalCount, $pageCount);
        $this->line("    ✓ {$totalCount} sales rows (across {$pageCount} pages)");
    }

    private function syncProductSales(string $placeId, string $startYmd, string $endYmd): void
    {
        $chunks = $this->dateChunks($startYmd, $endYmd, 31);
        $totalCount = 0;
        $pageCount = 0;
        $this->line('  /get/product_sales (aggregate)');

        $logId = $this->startSyncLog('/get/product_sales', $placeId, [
            'start' => $startYmd, 'end' => $endYmd,
        ]);

        try {
            foreach ($chunks as [$chunkStart, $chunkEnd]) {
                $snapshotDate = N::dateFromYmd($chunkEnd) ?? now()->format('Y-m-d');

                foreach ($this->client->paginate('/get/product_sales', [
                    'place' => $placeId,
                    'start' => $chunkStart,
                    'end'   => $chunkEnd,
                ]) as $resp) {
                    if (! $resp->isSuccess()) {
                        if (! $resp->isEmpty()) {
                            $this->finishSyncLog($logId, 'error', $totalCount, $pageCount, $resp->message);
                            $this->error("    ✗ {$resp->code}: {$resp->message}");
                            return;
                        }
                        break;
                    }

                    foreach ($resp->data as $row) {
                        $itemId = N::str($row['item_id'] ?? null);
                        if ($itemId === null) {
                            continue;
                        }

                        if (! $this->dryRun) {
                            // Composite unique key (place_id, snapshot_date, item_id) — pakai updateOrInsert
                            DB::table('omnicare_product_sales_daily')->updateOrInsert(
                                [
                                    'place_id'      => $placeId,
                                    'snapshot_date' => $snapshotDate,
                                    'item_id'       => $itemId,
                                ],
                                [
                                    'item_type'       => N::str($row['item_type'] ?? null),
                                    'item_name'       => N::str($row['item_name'] ?? null) ?? '(unknown)',
                                    'group_name'      => N::str($row['group_name'] ?? null),
                                    'sku'             => N::str($row['sku'] ?? null),
                                    'manufacturer'    => N::str($row['manufacturer'] ?? null),
                                    'category'        => N::str($row['category'] ?? null),
                                    'qty_sold'        => N::decimal($row['qty_sold'] ?? 0),
                                    'sum_price'       => N::decimal($row['sum_price'] ?? 0),
                                    'gross_price'     => N::decimal($row['gross_price'] ?? 0),
                                    'discount_amount' => N::decimal($row['discount_amount'] ?? 0),
                                    'discount_pct'    => N::decimal($row['discount_pct'] ?? 0),
                                    'sum_base'        => N::decimal($row['sum_base'] ?? 0),
                                    'sum_extra'       => N::decimal($row['sum_extra'] ?? 0),
                                    'comission'       => N::decimal($row['comission'] ?? 0),
                                    'net_profit'      => N::decimal($row['net_profit'] ?? 0),
                                ]
                            );
                        }
                        $totalCount++;
                    }
                    $pageCount++;
                }
            }
        } catch (Throwable $e) {
            $this->finishSyncLog($logId, 'error', $totalCount, $pageCount, $e->getMessage());
            throw $e;
        }

        $this->finishSyncLog($logId, 'success', $totalCount, $pageCount);
        $this->line("    ✓ {$totalCount} product rows (across {$pageCount} pages)");
    }

    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────

    private function resolvePlaces(?string $filter): array
    {
        if ($filter !== null && $filter !== '') {
            return [trim($filter)];
        }
        return config('omnicare.active_branches', []);
    }

    /** Split YYYYMMDD range into max-N-day chunks (Lokadok sales cap = 31). */
    private function dateChunks(string $startYmd, string $endYmd, int $maxDays): array
    {
        $start = Carbon::createFromFormat('Ymd', $startYmd);
        $end   = Carbon::createFromFormat('Ymd', $endYmd);
        if (! $start || ! $end || $end->lt($start)) {
            return [[$startYmd, $endYmd]];
        }

        $chunks = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $chunkEnd = $cursor->copy()->addDays($maxDays - 1);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
            }
            $chunks[] = [$cursor->format('Ymd'), $chunkEnd->format('Ymd')];
            $cursor = $chunkEnd->copy()->addDay();
        }
        return $chunks;
    }

    private function parsePointExpiry($v): ?string
    {
        $s = N::str($v);
        if ($s === null || $s === '-') {
            return null;
        }
        return N::dateFromYmd($s);
    }

    /** Omnicare encounter_id "0" = not linked → store NULL. */
    private function normalizeEncounterId($v): ?string
    {
        $s = N::str($v);
        if ($s === null || $s === '0') {
            return null;
        }
        return $s;
    }

    private function startSyncLog(string $endpoint, ?string $place, array $params): ?int
    {
        if ($this->dryRun) {
            return null;
        }
        return DB::table('omnicare_sync_log')->insertGetId([
            'endpoint'     => $endpoint,
            'place_id'     => $place,
            'params'       => N::jsonPayload($params),
            'started_at'   => now(),
            'status'       => 'running',
            'triggered_by' => $this->detectTrigger(),
        ]);
    }

    private function finishSyncLog(?int $logId, string $status, int $records, int $pages, ?string $error = null): void
    {
        if ($logId === null) {
            return;
        }
        $log = DB::table('omnicare_sync_log')->where('id', $logId)->first();
        $durationMs = $log
            ? (int) (Carbon::parse($log->started_at)->diffInMilliseconds(now()))
            : null;

        DB::table('omnicare_sync_log')->where('id', $logId)->update([
            'finished_at'   => now(),
            'duration_ms'   => $durationMs,
            'status'        => $status,
            'records_count' => $records,
            'pages_fetched' => $pages,
            'error_message' => $error,
        ]);
    }

    private function detectTrigger(): string
    {
        // Heuristic: TTY-less = cron, TTY = manual
        if (function_exists('posix_isatty') && defined('STDIN')) {
            return @posix_isatty(STDIN) ? 'manual' : 'cron';
        }
        return $this->input->isInteractive() ? 'manual' : 'cron';
    }
}
