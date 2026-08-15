<?php

namespace App\Console\Commands;

use App\Models\ChecklistReopenRequest;
use App\Models\ChecklistSubmission;
use App\Models\UserPosition;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tandai pengisian checklist HARIAN yang tidak tuntas di tanggal yang sudah
 * lewat sebagai status "missed" ("Tidak Diisi"), supaya muncul di tab
 * "Tidak Diisi" pada halaman Review & Approve Checklist Harian.
 *
 * "Tidak diisi" = karyawan aktif di tanggal itu yang:
 *   - tidak punya submission sama sekali (tak pernah buka form)   → buat row missed
 *   - punya submission masih DRAFT (dibuka tapi tak disubmit)     → ubah jadi missed
 *
 * Yang DILEWATI (tidak diubah): submitted / approved / rejected / excused /
 * missed. Karyawan Dikecualikan otomatis aman karena sudah punya row excused.
 *
 * Idempoten — aman di-rerun (row missed & non-draft akan dilewati).
 * Basis karyawan aktif: UserPosition::activeOn($tanggal).
 */
class MarkChecklistMissed extends Command
{
    protected $signature = 'checklist:mark-missed
        {--date= : Tanggal spesifik (YYYY-MM-DD). Default: kemarin. Harus sudah lewat.}
        {--days=1 : Jumlah hari ke belakang (berakhir kemarin) bila --date tidak diisi.}
        {--dry-run : Hanya laporkan jumlahnya, tidak menulis ke database.}';

    protected $description = 'Tandai checklist harian yang tidak diisi (tanggal lewat) sebagai "Tidak Diisi" (missed).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $dates = $this->resolveDates();
        if (empty($dates)) {
            return self::FAILURE;
        }

        $grandCreated = $grandConverted = $grandSkipped = 0;

        foreach ($dates as $date) {
            // Senin klinik tutup — tidak ada checklist harian, jadi jangan
            // ditandai "tidak diisi". Hari operasional: Selasa s/d Minggu.
            if ($date->dayOfWeek === Carbon::MONDAY) {
                $this->line(sprintf('  %s → dilewati (Senin, klinik tutup)', $date->toDateString()));
                continue;
            }

            [$created, $converted, $skipped] = $this->processDate($date, $dryRun);
            $grandCreated   += $created;
            $grandConverted += $converted;
            $grandSkipped   += $skipped;

            $this->line(sprintf(
                '  %s → dibuat: %d, draft→missed: %d, dilewati: %d',
                $date->toDateString(), $created, $converted, $skipped
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
     * Tentukan daftar tanggal yang diproses (urut lama → baru). Hanya tanggal
     * yang sudah lewat (< hari ini) yang boleh diproses.
     */
    private function resolveDates(): array
    {
        $today = Carbon::today();

        if ($opt = $this->option('date')) {
            try {
                $d = Carbon::parse($opt)->startOfDay();
            } catch (\Throwable $e) {
                $this->error('Format --date tidak valid. Pakai YYYY-MM-DD.');
                return [];
            }
            if ($d->gte($today)) {
                $this->error('Tanggal harus sudah lewat (lebih kecil dari hari ini).');
                return [];
            }
            return [$d];
        }

        $days  = max(1, (int) $this->option('days'));
        $dates = [];
        for ($i = 1; $i <= $days; $i++) {
            $dates[] = $today->copy()->subDays($i);
        }
        return array_reverse($dates);
    }

    /**
     * Proses satu tanggal. Return [dibuat, draft→missed, dilewati].
     */
    private function processDate(Carbon $date, bool $dryRun): array
    {
        $ds = $date->toDateString();

        // Karyawan aktif pada tanggal itu — 1 posisi primary per user.
        // Akun nonaktif/terhapus di-skip: posisinya mungkin belum tertutup,
        // jangan sampai dicetak "Tidak Diisi" selamanya.
        $positions = UserPosition::query()->activeOn($date)
            ->whereIn('user_id', \App\Models\Users::query()->where('status', 1)->select('id'))
            ->get(['user_id', 'division_id', 'raw_branch_id'])
            ->unique('user_id')
            ->keyBy('user_id');

        if ($positions->isEmpty()) {
            return [0, 0, 0];
        }

        $userIds = $positions->keys()->all();

        // Submission yang sudah ada di tanggal itu, per user.
        $existing = ChecklistSubmission::query()
            ->whereDate('submission_date', $ds)
            ->whereIn('user_id', $userIds)
            ->get(['id', 'user_id', 'status'])
            ->keyBy('user_id');

        // User dengan grant "buka kembali" AKTIF (disetujui, belum dipakai) untuk
        // tanggal ini — jangan dicap "missed", karyawan sedang diberi kesempatan
        // mengisi ulang. Kalau tetap dicap missed, checklist-nya terkunci walau
        // atasan sudah meng-approve (isEditable() false utk missed).
        $granted = ChecklistReopenRequest::query()
            ->where('scope', ChecklistReopenRequest::SCOPE_DAILY)
            ->whereDate('target_date', $ds)
            ->where('status', ChecklistReopenRequest::STATUS_APPROVED)
            ->whereNull('fulfilled_at')
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->flip();

        $newRows    = [];
        $convertIds = [];
        $skipped    = 0;

        foreach ($positions as $uid => $pos) {
            if ($granted->has($uid)) {
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
                    'submission_date' => $ds,
                    'status'          => ChecklistSubmission::STATUS_MISSED,
                    'total_tasks'     => 0,
                    'done_tasks'      => 0,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            } elseif ($sub->status === ChecklistSubmission::STATUS_DRAFT) {
                $convertIds[] = $sub->id;
            } else {
                $skipped++;
            }
        }

        if (! $dryRun) {
            foreach (array_chunk($newRows, 500) as $chunk) {
                DB::table('sys_checklist_submission')->insert($chunk);
            }
            if ($convertIds) {
                ChecklistSubmission::whereIn('id', $convertIds)->update([
                    'status'     => ChecklistSubmission::STATUS_MISSED,
                    'updated_at' => now(),
                ]);
            }
        }

        return [count($newRows), count($convertIds), $skipped];
    }
}
