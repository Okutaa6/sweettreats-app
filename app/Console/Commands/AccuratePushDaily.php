<?php

namespace App\Console\Commands;

use App\Services\Accurate\AccurateClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * DAILY RUNNER otomasi Omnicare → Accurate (5 buku cabang asli).
 *
 * Tiap run (aman dijalankan kapan pun, berapa kali pun):
 *   1. Sync Omnicare window terakhir (sales + payments + commission)
 *   2. Audit per (cabang, hari): bandingkan data FRESH vs yang sudah ke-push
 *      - hari baru (belum ada di Accurate)      → push
 *      - hari berubah (late entries / koreksi)  → hapus dokumen lama → re-push
 *      - fee berubah                            → hapus jurnal lama → re-push
 *      - hari cocok                             → skip (no-op)
 *   3. Delegasi push per-hari ke accurate:push-sample (mekanik proven).
 *
 * Latar: insiden 2 Juni 2026 (mirror lokal bolong + late entries staf) —
 * karena itu SELALU sync dulu & audit vs data fresh, jangan percaya mirror.
 *
 *   php artisan accurate:push-daily                 # window 14 hari, semua cabang
 *   php artisan accurate:push-daily --days=7 --branches=bintaro,sunter
 *   php artisan accurate:push-daily --dry-run       # audit saja, tidak ada tulisan
 *   php artisan accurate:push-daily --skip-sync     # pakai mirror lokal apa adanya
 */
class AccuratePushDaily extends Command
{
    protected $signature = 'accurate:push-daily
                            {--days=14 : window audit ke belakang (hari)}
                            {--branches=all : koma-separated slug cabang, atau "all"}
                            {--skip-sync : lewati sync Omnicare (pakai mirror lokal)}
                            {--dry-run : audit & rencana saja, tidak menulis apa pun}';

    protected $description = 'Runner harian: sync Omnicare → audit → repair → push ke buku Accurate cabang asli';

    private const BRANCHES = [
        'bintaro' => '29957', 'sunter' => '29958', 'kalideres' => '29959',
        'cinere' => '30439', 'pamulang' => '30941',
    ];

    public function handle(AccurateClient $client): int
    {
        // Schema guard: skip bersih di env yang belum deploy tabel Accurate
        // (cegah cron error saat kode sudah ada tapi tabel/data belum).
        foreach (['accurate_credentials', 'accurate_push_log', 'accurate_item_account_map', 'accurate_staging_sales_item'] as $t) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($t)) {
                $this->line("Tabel {$t} belum ada — SKIP (env belum deploy Accurate).");
                return self::SUCCESS;
            }
        }

        $days = max(1, (int) $this->option('days'));
        $start = now()->subDays($days)->format('Y-m-d');
        $end = now()->subDay()->format('Y-m-d'); // s/d kemarin — hari berjalan belum final
        $startYmd = str_replace('-', '', $start);
        $endYmd = str_replace('-', '', $end);
        $dry = (bool) $this->option('dry-run');

        $branches = $this->option('branches') === 'all'
            ? array_keys(self::BRANCHES)
            : array_values(array_intersect(explode(',', (string) $this->option('branches')), array_keys(self::BRANCHES)));

        $this->info("Push daily — window {$start} s/d {$end}, cabang: " . implode(',', $branches) . ($dry ? ' (DRY-RUN)' : ''));

        // ── 1. Sync Omnicare window (sumber kebenaran fresh) ──
        if (! $this->option('skip-sync')) {
            foreach (['sales', 'payments', 'commission'] as $ep) {
                $this->line("  sync {$ep} {$startYmd}-{$endYmd} ...");
                Artisan::call('omnicare:sync', ['--endpoint' => $ep, '--start' => $startYmd, '--end' => $endYmd]);
            }
            $this->info('  sync selesai.');
        }

        // ── 2+3. Audit per cabang → repair → push ──
        $totNew = 0; $totFix = 0; $totSkip = 0; $totFail = 0;
        foreach ($branches as $slug) {
            $place = self::BRANCHES[$slug];
            $dest = DB::table('accurate_credentials')->where('slug', $slug)->whereNotNull('api_token')->first();
            if (! $dest || empty($dest->host)) {
                $this->error("  {$slug}: kredensial belum siap — skip");
                continue;
            }

            // fresh per hari
            $freshRev = [];
            foreach (DB::table('omnicare_sales_detail')->where('place_id', $place)->whereBetween('trx_date', [$start, $end])
                ->selectRaw('trx_date d, SUM(net_sell_price) s')->groupBy('trx_date')->get() as $r) {
                $freshRev[(string) $r->d] = (float) $r->s;
            }
            $freshFee = [];
            foreach (DB::table('omnicare_commission')->where('place_id', $place)->whereBetween('trx_date', [$start, $end])
                ->selectRaw('trx_date d, SUM(commission_amount) s')->groupBy('trx_date')->get() as $r) {
                $freshFee[(string) $r->d] = (float) $r->s;
            }

            // pushed per hari (dari push_log)
            $pushedInv = DB::table('accurate_push_log')->where('slug', $slug)->where('source_slug', 'omnicare-v2-invoice')
                ->where('status', 'pushed')->whereBetween('trans_date', [$start, $end])->pluck('gross_amount', 'trans_date');
            $pushedJv = DB::table('accurate_push_log')->where('slug', $slug)->where('source_slug', 'omnicare-v2-journal')
                ->where('status', 'pushed')->whereBetween('trans_date', [$start, $end])->pluck('gross_amount', 'trans_date');

            $daysAll = collect(array_keys($freshRev))->merge(array_keys($freshFee))
                ->merge($pushedInv->keys())->merge($pushedJv->keys())
                ->map(fn ($d) => (string) $d)->unique()->sort()->values();

            $newDays = []; $fixDays = [];
            foreach ($daysAll as $d) {
                $fr = $freshRev[$d] ?? 0; $ff = $freshFee[$d] ?? 0;
                $pi = (float) ($pushedInv[$d] ?? 0); $pj = (float) ($pushedJv[$d] ?? 0);
                $invDiff = abs($fr - $pi) >= 1;
                $feeDiff = abs($ff - $pj) >= 1;
                if (! $invDiff && ! $feeDiff) { $totSkip++; continue; }
                $isNew = $pi == 0 && $pj == 0;
                if ($isNew) { $newDays[] = $d; }
                else { $fixDays[$d] = ['inv' => $invDiff, 'fee' => $feeDiff]; }
            }

            if (! $newDays && ! $fixDays) {
                $this->line("  {$slug}: semua cocok (no-op)");
                continue;
            }
            $this->line("  {$slug}: baru [" . implode(',', $newDays) . '] · berubah [' . implode(',', array_keys($fixDays)) . ']');
            if ($dry) { $totNew += count($newDays); $totFix += count($fixDays); continue; }

            // repair: hapus dokumen hari yang berubah (receipt → invoice → journal kalau fee beda)
            foreach ($fixDays as $d => $what) {
                $kinds = [];
                if ($what['inv']) { $kinds['omnicare-v2-receipt'] = '/api/sales-receipt/delete.do'; $kinds['omnicare-v2-invoice'] = '/api/sales-invoice/delete.do'; }
                if ($what['fee']) { $kinds['omnicare-v2-journal'] = '/api/journal-voucher/delete.do'; }
                foreach ($kinds as $src => $ep) {
                    $rows = DB::table('accurate_push_log')->where('slug', $slug)->where('source_slug', $src)
                        ->where('trans_date', $d)->where('status', 'pushed')->whereNotNull('accurate_invoice_id')->get();
                    foreach ($rows as $row) {
                        $r = $client->get($dest->host, $ep, $dest->api_token, ['id' => $row->accurate_invoice_id]);
                        if ($r->isSuccess()) {
                            DB::table('accurate_push_log')->where('id', $row->id)->update(['status' => 'deleted']);
                        } else {
                            $this->warn("    ⚠ gagal hapus {$row->invoice_number}: " . mb_substr((string) $r->error, 0, 60));
                        }
                        usleep(150000);
                    }
                }
            }

            // push (hari baru + hari yang barusan dibersihkan)
            foreach (array_merge($newDays, array_keys($fixDays)) as $d) {
                $rc = Artisan::call('accurate:push-sample', [
                    '--from' => $slug, '--to' => $slug, '--allow-live' => true,
                    '--start' => $d, '--end' => $d,
                ]);
                if ($rc === 0) { in_array($d, $newDays, true) ? $totNew++ : $totFix++; }
                else { $totFail++; $this->error("    ✗ {$slug} {$d}: push gagal (lihat log)"); }
            }
        }

        $this->info("SELESAI — baru: {$totNew} · diperbaiki: {$totFix} · cocok/skip: {$totSkip} · gagal: {$totFail}" . ($dry ? ' (dry-run)' : ''));
        return $totFail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
