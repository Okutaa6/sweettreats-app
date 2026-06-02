<?php

namespace App\Console\Commands;

use App\Services\Omnicare\OmnicareClient;
use Illuminate\Console\Command;

/**
 * Smoke test koneksi ke Omnicare Open API dari Laravel stack.
 *
 *   php artisan omnicare:health
 *   php artisan omnicare:health --verbose
 *
 * Tests:
 *   1. /get/location  → creds valid, dapat list branches
 *   2. /get/doctor_list (branch pertama) → param passing kerja
 *   3. /get/sales_detail (pilot Sunter, 3 hari) → sales endpoint + cooldown
 *
 * Output formatted, exit code 0 kalau semua hijau, 1 kalau ada yang gagal.
 */
class OmnicareHealthCheck extends Command
{
    protected $signature = 'omnicare:health';

    protected $description = 'Smoke test koneksi Omnicare Open API (creds + 3 endpoint kritis)';

    public function handle(OmnicareClient $client): int
    {
        $this->info('Omnicare API Health Check');
        $this->line(str_repeat('─', 60));

        $allOk = true;

        // ─── Test 1: Location ────────────────────────────────────────
        $this->line('');
        $this->info('[1/3] /get/location — creds + connectivity');
        $resp1 = $client->getLocation();

        if (! $resp1->isSuccess()) {
            $this->error("  ✗ FAILED — code={$resp1->code}, message=\"{$resp1->message}\"");
            if ($resp1->isAuthFailure()) {
                $this->warn('       Auth gagal — cek OMNICARE_USERNAME + OMNICARE_PASSWORD di .env');
            }
            return self::FAILURE;
        }

        $branchCount = count($resp1->data);
        $this->line("  ✓ {$branchCount} branches found");
        foreach ($resp1->data as $b) {
            $parent = (int) ($b['is_parent'] ?? 0) ? ' (parent)' : '';
            $this->line(sprintf('    - %s  %s, %s%s', $b['id'] ?? '?', $b['name'] ?? '?', $b['city'] ?? '?', $parent));
        }

        // Pick first non-parent branch for further tests; fallback to first
        $testPlace = null;
        foreach ($resp1->data as $b) {
            $testPlace = $b['id'] ?? null;
            if ($testPlace !== null) {
                break;
            }
        }

        if ($testPlace === null) {
            $this->error('  ✗ No branch ID found to continue tests');
            return self::FAILURE;
        }

        // ─── Test 2: Doctor list ─────────────────────────────────────
        $this->line('');
        $this->info("[2/3] /get/doctor_list?place={$testPlace} — param passing");
        $resp2 = $client->getDoctorList($testPlace);

        if ($resp2->isSuccess()) {
            $this->line('  ✓ ' . count($resp2->data) . ' doctors at this branch');
        } elseif ($resp2->isEmpty()) {
            $this->warn('  ⚠ Data kosong (0 doctors). This branch might be placeholder.');
        } else {
            $this->error("  ✗ FAILED — code={$resp2->code}, message=\"{$resp2->message}\"");
            $allOk = false;
        }

        // ─── Test 3: Sales detail Sunter (29958) 3 hari ──────────────
        $this->line('');
        $sunter = '29958';
        $start  = now()->subDays(3)->format('Ymd');
        $end    = now()->format('Ymd');
        $this->info("[3/3] /get/sales_detail?place={$sunter}&start={$start}&end={$end} — sales endpoint");
        $resp3 = $client->getSalesDetail($sunter, $start, $end);

        if ($resp3->isSuccess()) {
            $this->line('  ✓ ' . count($resp3->data) . " sales rows in last 3 days (pages={$resp3->pages})");
            if (! empty($resp3->data)) {
                $sample = $resp3->data[0];
                $this->line(sprintf(
                    '    Sample: %s (%s) Rp %s @ %s',
                    $sample['item_name'] ?? '?',
                    $sample['item_code'] ?? '?',
                    number_format((float) ($sample['net_sell_price'] ?? 0), 0, ',', '.'),
                    $sample['trx_date'] ?? '?'
                ));
            }
        } elseif ($resp3->isEmpty()) {
            $this->warn('  ⚠ Data kosong — no sales in last 3 days at Sunter (unexpected, check date or branch)');
        } else {
            $this->error("  ✗ FAILED — code={$resp3->code}, message=\"{$resp3->message}\"");
            $allOk = false;
        }

        // ─── Summary ─────────────────────────────────────────────────
        $this->line('');
        $this->line(str_repeat('─', 60));
        if ($allOk) {
            $this->info('All checks passed. Ready to proceed with sync command.');
            return self::SUCCESS;
        }

        $this->error('Some checks failed. Review errors above.');
        return self::FAILURE;
    }
}
