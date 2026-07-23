<?php

namespace App\Console\Commands;

use App\Models\ChecklistCategory;
use App\Models\ChecklistTask;
use App\Models\ChecklistWeeklySubmission;
use App\Models\UserPosition;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tandai Form Mingguan yang tidak tuntas di MINGGU yang sudah BERAKHIR sebagai
 * status "missed" ("Tidak Diisi"), supaya muncul di tab "Tidak Diisi" pada
 * halaman Review & Approve Checklist Mingguan. Versi mingguan dari
 * checklist:mark-missed (harian).
 *
 * "Tidak diisi" = karyawan aktif di minggu itu yang:
 *   - tidak punya Form Mingguan sama sekali (tak pernah buka)  → buat row missed
 *   - punya Form Mingguan masih DRAFT (dibuka tapi tak disubmit) → ubah jadi missed
 *
 * HANYA untuk karyawan yang divisinya PUNYA tugas mingguan aktif (kategori
 * cadence=weekly + task day_of_week 2–7). Divisi tanpa tugas mingguan tidak
 * dianggap "tidak diisi" (memang tak ada form-nya).
 *
 * Yang DILEWATI: submitted / approved / rejected / excused / missed.
 * Idempoten — aman di-rerun. Basis karyawan aktif: UserPosition::activeOn(akhir minggu).
 */
class MarkWeeklyChecklistMissed extends Command
{
    protected $signature = 'checklist:mark-weekly-missed
        {--week= : Tanggal apa saja dalam minggu target (YYYY-MM-DD) → dinormalisasi ke Senin. Default: minggu lalu. Minggu harus sudah berakhir.}
        {--weeks=1 : Jumlah minggu ke belakang (berakhir minggu lalu) bila --week tidak diisi.}
        {--dry-run : Hanya laporkan jumlahnya, tidak menulis ke database.}';

    protected $description = 'Tandai Form Mingguan yang tidak diisi (minggu sudah berakhir) sebagai "Tidak Diisi" (missed).';

    /** Cache jumlah tugas mingguan per "divisionId|branchId" dalam satu run. */
    private array $taskCountCache = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $weeks = $this->resolveWeeks();
        if (empty($weeks)) {
            return self::FAILURE;
        }

        $grandCreated = $grandConverted = $grandSkipped = 0;

        foreach ($weeks as $weekStart) {
            [$created, $converted, $skipped] = $this->processWeek($weekStart, $dryRun);
            $grandCreated   += $created;
            $grandConverted += $converted;
            $grandSkipped   += $skipped;

            $this->line(sprintf(
                '  Minggu %s → dibuat: %d, draft→missed: %d, dilewati: %d',
                $weekStart->toDateString(), $created, $converted, $skipped
            ));
        }

        $this->info(sprintf(
            '%sSelesai. Total — dibuat: %d, draft→missed: %d, dilewati: %d',
            $dryRun ? '[DRY-RUN] ' : '',
            $grandCreated, $grandConverted, $grandSkipped
        ));

        return self::SUCCESS;
    }

    /**
     * Daftar Senin minggu target (urut lama → baru). Hanya minggu yang SUDAH
     * berakhir (Minggu/akhir minggu < hari ini) yang diproses.
     */
    private function resolveWeeks(): array
    {
        $today          = Carbon::today();
        $currentMonday  = $today->copy()->startOfWeek(Carbon::MONDAY);

        if ($opt = $this->option('week')) {
            try {
                $ws = Carbon::parse($opt)->startOfWeek(Carbon::MONDAY);
            } catch (\Throwable $e) {
                $this->error('Format --week tidak valid. Pakai YYYY-MM-DD.');
                return [];
            }
            if ($ws->copy()->endOfWeek(Carbon::SUNDAY)->gte($today)) {
                $this->error('Minggu harus sudah berakhir (akhir minggu lebih kecil dari hari ini).');
                return [];
            }
            return [$ws];
        }

        $count  = max(1, (int) $this->option('weeks'));
        $weeks  = [];
        for ($i = 1; $i <= $count; $i++) {
            $weeks[] = $currentMonday->copy()->subWeeks($i);
        }
        return array_reverse($weeks);
    }

    /**
     * Proses satu minggu. Return [dibuat, draft→missed, dilewati].
     */
    private function processWeek(Carbon $weekStart, bool $dryRun): array
    {
        $weekEnd  = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $yearWeek = $weekStart->isoFormat('GGGG-[W]WW');

        // Karyawan yang masih aktif di akhir minggu itu (1 posisi primary per user).
        $positions = UserPosition::query()->activeOn($weekEnd)
            ->get(['user_id', 'division_id', 'raw_branch_id'])
            ->unique('user_id')
            ->keyBy('user_id');

        if ($positions->isEmpty()) {
            return [0, 0, 0];
        }

        $userIds = $positions->keys()->all();

        $existing = ChecklistWeeklySubmission::query()
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->whereIn('user_id', $userIds)
            ->get(['id', 'user_id', 'status'])
            ->keyBy('user_id');

        $newRows    = [];
        $convertIds = [];
        $skipped    = 0;

        foreach ($positions as $uid => $pos) {
            // Gate: divisi (per cabang) harus punya tugas mingguan aktif.
            $total = $this->weeklyTaskCount($pos->division_id, $pos->raw_branch_id);
            if ($total === 0) {
                $skipped++;
                continue;
            }

            $sub = $existing->get($uid);

            if (! $sub) {
                $newRows[] = [
                    'id'              => (string) Str::uuid(),
                    'user_id'         => $uid,
                    'division_id'     => $pos->division_id,
                    'raw_branch_id'   => $pos->raw_branch_id,
                    'week_start_date' => $weekStart->toDateString(),
                    'year_week'       => $yearWeek,
                    'status'          => ChecklistWeeklySubmission::STATUS_MISSED,
                    'total_tasks'     => $total,
                    'done_tasks'      => 0,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            } elseif ($sub->status === ChecklistWeeklySubmission::STATUS_DRAFT) {
                $convertIds[] = $sub->id;
            } else {
                $skipped++;
            }
        }

        if (! $dryRun) {
            foreach (array_chunk($newRows, 500) as $chunk) {
                DB::table('sys_checklist_weekly_submission')->insert($chunk);
            }
            if ($convertIds) {
                ChecklistWeeklySubmission::whereIn('id', $convertIds)->update([
                    'status'     => ChecklistWeeklySubmission::STATUS_MISSED,
                    'updated_at' => now(),
                ]);
            }
        }

        return [count($newRows), count($convertIds), $skipped];
    }

    /**
     * Jumlah tugas mingguan aktif untuk (divisi, cabang) — sama definisi dengan
     * bootstrapWeeklyItems: kategori cadence=weekly aktif (branch null / cocok)
     * + task aktif dengan day_of_week 2–7. Di-cache per run.
     */
    private function weeklyTaskCount(string $divisionId, ?string $branchId): int
    {
        $key = $divisionId . '|' . ($branchId ?? '');
        if (array_key_exists($key, $this->taskCountCache)) {
            return $this->taskCountCache[$key];
        }

        $count = ChecklistTask::query()
            ->where('is_active', 1)
            ->whereNotNull('day_of_week')
            ->whereIn('day_of_week', [2, 3, 4, 5, 6, 7])
            ->whereHas('category', function ($q) use ($divisionId, $branchId) {
                $q->where('division_id', $divisionId)
                  ->where('is_active', 1)
                  ->where('cadence', ChecklistCategory::CADENCE_WEEKLY)
                  ->where(function ($q2) use ($branchId) {
                      $q2->whereNull('raw_branch_id')->orWhere('raw_branch_id', $branchId);
                  });
            })
            ->count();

        return $this->taskCountCache[$key] = $count;
    }
}
