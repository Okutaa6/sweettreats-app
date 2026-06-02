<?php

namespace App\Console\Commands;

use App\Support\OmnicareEncounters;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reconciliation engine — cocokkan reservasi SweetTreats dengan encounter
 * (kunjungan nyata) di Omnicare. Menjawab: "pasien yang booking, jadi datang?
 * Sesuai cabang? Berapa yang dia bayar?"
 *
 *   php artisan omnicare:reconcile                  # 30 hari ke belakang
 *   php artisan omnicare:reconcile --days=90        # window lebih panjang
 *   php artisan omnicare:reconcile --dry-run        # hitung saja
 *   php artisan omnicare:reconcile --rematch        # hapus hasil sistem, ulang
 *
 * Jembatan identitas: raw_cus_reservation → raw_customer → customer_omnicare_link
 * → omnicare_pid → omnicare_sales_detail.p_id. Tanpa Step 2 (match-patients),
 * command ini tidak punya pegangan siapa-siapa.
 *
 * Hasil per reservasi (di reservation_omnicare_link):
 *   - linked   : encounter ketemu (omnicare_record_id terisi)
 *   - no_show  : window sudah lewat, tidak ada encounter
 *   - conflict : >1 encounter kandidat sama kuat → match_confidence='low', review manual
 *   - no_match : customer belum ter-link ke Omnicare
 *
 * Walk-in (encounter tanpa reservasi) TIDAK dimaterialisasi di sini — dihitung
 * sebagai statistik di dashboard (encounter total − encounter ter-link).
 *
 * Jadwal: jalan 03:00 setelah omnicare:sync 02:00 (lihat Kernel.php).
 */
class OmnicareReconcile extends Command
{
    protected $signature = 'omnicare:reconcile
                            {--days=30 : Lookback hari untuk reservasi yang diproses}
                            {--dry-run : Tidak tulis ke DB, hanya laporkan}
                            {--rematch : Hapus hasil reconcile sistem lalu ulang}';

    protected $description = 'Cocokkan reservasi SweetTreats ↔ encounter Omnicare (reservation_omnicare_link)';

    /** Toleransi geser tanggal reservasi ↔ encounter (hari). */
    private const MATCH_WINDOW = 3;

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $days  = max(1, (int) $this->option('days'));
        $today = Carbon::today();
        $from  = $today->copy()->subDays($days);

        $this->line('');
        $this->line(str_repeat('═', 64));
        $this->info('Omnicare Reconcile — ' . now()->format('Y-m-d H:i:s'));
        $this->line(str_repeat('═', 64));
        if ($this->dryRun) {
            $this->warn('▶ DRY RUN — tidak ada penulisan DB');
        }
        $this->line("▶ Reservasi diproses: {$from->toDateString()} → {$today->toDateString()}");

        if ($this->option('rematch') && ! $this->dryRun) {
            $deleted = DB::table('reservation_omnicare_link')->where('reconciled_by', 'system')->delete();
            $this->warn("▶ --rematch: {$deleted} baris reconcile sistem dihapus");
        }

        $alreadyLinked = DB::table('reservation_omnicare_link')
            ->whereNotNull('raw_cus_reservation_id')
            ->pluck('raw_cus_reservation_id')
            ->flip();

        // Reservasi dalam scope: tanggal sudah lewat / hari ini, di 5 cabang ter-map
        $reservations = DB::table('raw_cus_reservation as r')
            ->join('raw_branch as b', 'b.id', '=', 'r.raw_branch_id')
            ->leftJoin('customer_omnicare_link as l', 'l.raw_customer_id', '=', 'r.raw_customer_id')
            ->whereNotNull('b.omnicare_place_id')
            ->whereDate('r.date', '>=', $from)
            ->whereDate('r.date', '<=', $today)
            ->select(
                'r.id', 'r.date', 'r.raw_customer_id',
                'b.omnicare_place_id as place_id', 'b.name as branch_name',
                'l.omnicare_pid'
            )
            ->orderBy('r.date')
            ->get();

        $stats = [
            'scanned'  => $reservations->count(),
            'already'  => 0,
            'linked'   => 0,
            'no_show'  => 0,
            'conflict' => 0,
            'no_match' => 0,
            'open'     => 0, // window belum tutup, biarkan
        ];

        $rows = [];

        foreach ($reservations as $r) {
            if (isset($alreadyLinked[$r->id])) {
                $stats['already']++;
                continue;
            }

            $resDate     = Carbon::parse($r->date);
            $windowClose = $resDate->copy()->addDays(self::MATCH_WINDOW);
            $windowOpen  = $windowClose->gte($today); // encounter masih bisa muncul

            // ── Customer belum ter-link ke Omnicare → no_match ────────────
            if (empty($r->omnicare_pid)) {
                $rows[] = $this->row($r, [
                    'omnicare_record_id' => null,
                    'match_confidence'   => 'no_match',
                    'match_method'       => 'no_customer_link',
                    'notes'              => 'Customer belum ter-link ke Omnicare (jalankan omnicare:match-patients atau review manual).',
                ]);
                $stats['no_match']++;
                continue;
            }

            // ── Kandidat encounter: kunjungan pasien dalam window tanggal ──
            $candidates = $this->candidateEncounters(
                $r->omnicare_pid,
                $resDate->copy()->subDays(self::MATCH_WINDOW),
                $windowClose
            );

            if (empty($candidates)) {
                if ($windowOpen) {
                    $stats['open']++; // belum bisa disimpulkan no-show
                    continue;
                }
                $rows[] = $this->row($r, [
                    'omnicare_record_id' => null,
                    'match_confidence'   => 'no_match',
                    'match_method'       => 'orphan_noshow',
                    'notes'              => 'Tidak ada encounter dalam ±' . self::MATCH_WINDOW . ' hari — kemungkinan no-show.',
                ]);
                $stats['no_show']++;
                continue;
            }

            // ── Pilih kandidat terbaik: jarak tanggal terkecil, cabang cocok ──
            foreach ($candidates as &$cand) {
                $cand['date_dist']   = abs(Carbon::parse($cand['trx_date'])->diffInDays($resDate));
                $cand['same_branch'] = ((string) $cand['place_id'] === (string) $r->place_id);
            }
            unset($cand);

            usort($candidates, function ($a, $b) {
                return [$a['date_dist'], $a['same_branch'] ? 0 : 1]
                   <=> [$b['date_dist'], $b['same_branch'] ? 0 : 1];
            });

            $best = $candidates[0];

            // Ambigu: kandidat #2 sama jarak & sama status cabang → conflict
            $ambiguous = isset($candidates[1])
                && $candidates[1]['date_dist'] === $best['date_dist']
                && $candidates[1]['same_branch'] === $best['same_branch'];

            if ($ambiguous) {
                $list = array_map(
                    fn($c) => "{$c['encounter_id']} ({$c['trx_date']})",
                    array_slice($candidates, 0, 4)
                );
                $rows[] = $this->row($r, [
                    'omnicare_record_id' => null,
                    'match_confidence'   => 'low',
                    'match_method'       => 'conflict_multi',
                    'notes'              => 'Beberapa encounter sama kuat — perlu pilih manual: ' . implode(', ', $list),
                ]);
                $stats['conflict']++;
                continue;
            }

            // ── Linked ────────────────────────────────────────────────────
            $confidence = $this->confidence($best['date_dist'], $best['same_branch']);
            $method     = $best['same_branch'] ? 'link_date_branch' : 'link_date';
            $note       = $best['same_branch']
                ? null
                : "Cabang encounter ({$best['branch_name']}) beda dari reservasi ({$r->branch_name}).";

            $rows[] = $this->row($r, [
                'omnicare_record_id' => $best['encounter_id'],
                'match_confidence'   => $confidence,
                'match_method'       => $method,
                'actual_revenue'     => $best['revenue'],
                'payment_status'     => $best['payment_status'],
                'encounter_date'     => $best['trx_date'],
                'notes'              => $note,
            ]);
            $stats['linked']++;
        }

        if (! $this->dryRun && ! empty($rows)) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('reservation_omnicare_link')->insert($chunk);
            }
        }

        $this->report($stats);

        return self::SUCCESS;
    }

    /**
     * Encounter kandidat untuk satu pasien dalam rentang tanggal.
     * Grouping per encounter_id + aturan revenue di App\Support\OmnicareEncounters.
     */
    private function candidateEncounters(string $pid, Carbon $start, Carbon $end): array
    {
        $lines = DB::table('omnicare_sales_detail')
            ->where('p_id', $pid)
            ->whereBetween('trx_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('encounter_id')
            ->where('encounter_id', '<>', '')
            ->select('encounter_id', 'trx_date', 'place_id', 'branch_name', 'doctor',
                     'net_sell_price', 'item_type', 'item_name', 'qty_sold', 'payment_status')
            ->get();

        return OmnicareEncounters::group($lines);
    }

    /** Confidence link berdasar jarak tanggal & kecocokan cabang. */
    private function confidence(int $dateDist, bool $sameBranch): string
    {
        if (! $sameBranch) {
            return 'low'; // datang ke cabang lain — worth review
        }
        return match (true) {
            $dateDist === 0 => 'exact',
            $dateDist <= 1  => 'high',
            default         => 'medium',
        };
    }

    /** Bangun satu baris reservation_omnicare_link dengan default kolom. */
    private function row(object $r, array $override): array
    {
        $now = now();
        return array_merge([
            'id'                     => (string) Str::uuid(),
            'raw_cus_reservation_id' => $r->id,
            'omnicare_record_id'     => null,
            'place_id'               => $r->place_id,
            'match_confidence'       => 'no_match',
            'match_method'           => null,
            'service_match'          => 'unknown',
            'expected_revenue'       => null,
            'actual_revenue'         => null,
            'revenue_variance'       => null,
            'payment_status'         => null,
            'encounter_date'         => null,
            'reconciled_at'          => $now,
            'reconciled_by'          => 'system',
            'notes'                  => null,
            'created_at'             => $now,
        ], $override);
    }

    private function report(array $s): void
    {
        $this->line('');
        $this->line(str_repeat('─', 64));
        $this->info('HASIL RECONCILE');
        $this->line(str_repeat('─', 64));
        $this->table(
            ['Status', 'Jumlah'],
            [
                ['Reservasi di-scan',          number_format($s['scanned'])],
                ['  ↳ sudah ter-reconcile',    number_format($s['already'])],
                ['  ↳ window masih terbuka',   number_format($s['open'])],
                ['LINKED (encounter ketemu)',  number_format($s['linked'])],
                ['NO-SHOW (tidak datang)',     number_format($s['no_show'])],
                ['CONFLICT (review manual)',   number_format($s['conflict'])],
                ['NO-MATCH (customer ?)',      number_format($s['no_match'])],
            ]
        );
        $processed = $s['linked'] + $s['no_show'] + $s['conflict'];
        if ($processed > 0) {
            $rate = round($s['linked'] / $processed * 100, 1);
            $this->line("▶ Conversion (linked / selesai): {$rate}%");
        }
        if ($this->dryRun) {
            $this->warn('▶ DRY RUN — tidak ada baris ditulis.');
        } else {
            $written = $s['linked'] + $s['no_show'] + $s['conflict'] + $s['no_match'];
            $this->info('✓ ' . number_format($written) . ' baris ditulis ke reservation_omnicare_link');
        }
    }
}
