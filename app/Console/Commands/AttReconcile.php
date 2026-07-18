<?php

namespace App\Console\Commands;

use App\Services\AttendanceReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Hitung ulang att_day dari att_scan + aturan, untuk rentang tanggal.
 *
 *   php artisan att:reconcile 2026-06-01 2026-06-30
 */
class AttReconcile extends Command
{
    protected $signature = 'att:reconcile {from : YYYY-MM-DD} {to : YYYY-MM-DD}';
    protected $description = 'Rekonsiliasi att_scan → att_day (telat/alpa/hadir per user per hari)';

    public function handle(AttendanceReconciler $engine): int
    {
        $from = Carbon::parse($this->argument('from'))->startOfDay();
        $to = Carbon::parse($this->argument('to'))->startOfDay();
        if ($to->lt($from)) {
            $this->error('Rentang tanggal terbalik.');
            return self::FAILURE;
        }

        $t0 = microtime(true);
        $res = $engine->reconcile($from, $to);
        $ms = round((microtime(true) - $t0) * 1000);

        $this->info("Rekonsiliasi {$from->toDateString()} s/d {$to->toDateString()} ({$ms}ms)");
        $this->line("  user diproses: {$res['users']}  |  baris att_day: {$res['days']}");
        $rows = [];
        foreach ($res['per_status'] as $status => $n) $rows[] = [$status, $n];
        usort($rows, fn ($a, $b) => $b[1] <=> $a[1]);
        $this->table(['status', 'jumlah'], $rows);

        return self::SUCCESS;
    }
}
