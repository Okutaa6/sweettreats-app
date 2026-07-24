<?php

namespace App\Console\Commands;

use App\Models\AttFlag;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sweep flag "lupa absen pulang" (single_scan) — jalan tiap pagi SETELAH
 * att:reconcile, karena bacanya dari att_day (bukan att_scan mentah).
 *
 *   php artisan att:flag-sweep            → hari kerja KEMARIN
 *   php artisan att:flag-sweep 2026-07-20 → tanggal tertentu (atau backfill)
 *
 * Aturan (docs/rencana-absen-hp.md §8):
 *   • Cuma orang BAKU — fleksibel tak pernah di-flag (scan mereka jejak doang).
 *   • single_scan bakal jadi flag tersibuk minggu-minggu awal; itu normal,
 *     mendidik kebiasaan. Jangan pernah auto-hukum dari sini.
 *   • Idempoten: AttFlag::raise UNIQUE (user, tanggal, jenis) — aman diulang.
 */
class AttFlagSweep extends Command
{
    protected $signature = 'att:flag-sweep {date? : YYYY-MM-DD; tanpa arg = 3 hari terakhir (catch-up)}';
    protected $description = 'Naikkan flag single_scan (lupa absen pulang) dari att_day untuk direview SPV';

    public function handle(): int
    {
        // Tanpa argumen: sapu 3 hari ke belakang, bukan cuma kemarin —
        // cron mati sehari / reconcile telat tidak bikin tanggal bolong
        // permanen (idempoten, jadi tumpang-tindih aman).
        $dates = $this->argument('date')
            ? [Carbon::parse($this->argument('date'))->toDateString()]
            : collect(range(1, 3))->map(fn ($i) => Carbon::today()->subDays($i)->toDateString())->all();

        foreach ($dates as $date) {
            // att_day.single_scan=1 + status kehadiran nyata + aturan BUKAN fleksibel
            $rows = DB::table('att_day as d')
                ->join('att_work_rule as wr', 'wr.id', '=', 'd.rule_id')
                ->where('d.work_date', $date)
                ->where('d.single_scan', 1)
                ->whereIn('d.status', ['hadir', 'telat'])
                ->where('wr.tipe', '!=', 'fleksibel')
                ->get(['d.id', 'd.user_id']);

            foreach ($rows as $r) {
                AttFlag::raise($r->user_id, $date, 'single_scan', 'att_day', $r->id);
            }

            $this->info("Sweep {$date}: " . count($rows) . " orang cuma punya satu scan (lupa absen pulang) → flag single_scan.");
        }

        return self::SUCCESS;
    }
}
