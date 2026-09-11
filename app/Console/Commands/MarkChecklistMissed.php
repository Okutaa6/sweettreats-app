<?php

namespace App\Console\Commands;

use App\Models\AttRequest;
use App\Models\ChecklistReopenRequest;
use App\Models\ChecklistSubmission;
use App\Models\UserPosition;
use App\Services\ChecklistExcuseService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
 * missed.
 *
 * CUTI: karyawan dengan cuti/izin/sakit APPROVED yang menutupi tanggal itu
 * TIDAK boleh dicap "Tidak Diisi" — barisnya dibuat/diubah jadi 'excused'
 * (excuse_source='system') di sini juga, bukan hanya lewat hook approval.
 * Sengaja dobel: ini lapisan yang tak butuh state sebelumnya, jadi ikut
 * menyelamatkan cuti yang di-ACC sebelum fitur ini ada & hook yang gagal diam.
 * Grant "buka kembali" tetap menang di atas cuti — itu keputusan eksplisit atasan.
 *
 * Idempoten — aman di-rerun (row missed & non-draft akan dilewati).
 * Basis karyawan aktif: UserPosition::activeOn($tanggal).
 */
class MarkChecklistMissed extends Command
{
    protected $signature = 'checklist:mark-missed
        {--date= : Tanggal spesifik (YYYY-MM-DD). Default: kemarin. Hari checklist-nya harus sudah tutup (jam 06:00).}
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

        $grandCreated = $grandConverted = $grandSkipped = $grandExcused = 0;

        foreach ($dates as $date) {
            // Senin klinik tutup — tidak ada checklist harian, jadi jangan
            // ditandai "tidak diisi". Hari operasional: Selasa s/d Minggu.
            if ($date->dayOfWeek === Carbon::MONDAY) {
                $this->line(sprintf('  %s → dilewati (Senin, klinik tutup)', $date->toDateString()));
                continue;
            }

            [$created, $converted, $skipped, $excused] = $this->processDate($date, $dryRun);
            $grandCreated   += $created;
            $grandConverted += $converted;
            $grandSkipped   += $skipped;
            $grandExcused   += $excused;

            $this->line(sprintf(
                '  %s → dibuat: %d, draft→missed: %d, dikecualikan (cuti): %d, dilewati: %d',
                $date->toDateString(), $created, $converted, $excused, $skipped
            ));
        }

        $this->info(sprintf(
            '%sSelesai. Total — dibuat: %d, draft→missed: %d, dikecualikan (cuti): %d, dilewati: %d',
            $dryRun ? '[DRY-RUN] ' : '',
            $grandCreated, $grandConverted, $grandExcused, $grandSkipped
        ));

        return self::SUCCESS;
    }

    /**
     * Tentukan daftar tanggal yang diproses (urut lama → baru). Hanya tanggal
     * yang HARI CHECKLIST-nya sudah tutup yang boleh diproses.
     *
     * Patokannya checklistOperationalDate(), bukan Carbon::today(): hari
     * checklist tutup jam 06:00, jadi kalau command ini terlanjur jalan jam 2
     * pagi, tanggal kemarin MASIH terbuka dan tak boleh dicap "Tidak Diisi".
     * Penjagaannya di sini, bukan cuma di jadwal — supaya salah setel cron atau
     * run manual tak bisa menghukum orang yang sebenarnya masih punya waktu.
     */
    private function resolveDates(): array
    {
        $today = checklistOperationalDate();

        if ($opt = $this->option('date')) {
            try {
                $d = Carbon::parse($opt)->startOfDay();
            } catch (\Throwable $e) {
                $this->error('Format --date tidak valid. Pakai YYYY-MM-DD.');
                return [];
            }
            if ($d->gte($today)) {
                $this->error(sprintf(
                    'Tanggal itu masih terbuka. Hari checklist tutup jam %02d:00 — yang boleh diproses hanya sebelum %s.',
                    ChecklistSubmission::DAY_CUTOFF_HOUR, $today->toDateString()
                ));
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
     * Proses satu tanggal. Return [dibuat, draft→missed, dilewati, dikecualikan].
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
            return [0, 0, 0, 0];
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

        $onLeave = $this->approvedLeavesOn($ds, $userIds);

        $newRows    = [];
        $convertIds = [];
        $excuseIds  = [];   // submission_id → att_request (draft → excused)
        $skipped    = 0;
        $excused    = 0;

        foreach ($positions as $uid => $pos) {
            if ($granted->has($uid)) {
                $skipped++;
                continue;
            }

            $sub   = $existing->get($uid);
            $leave = $onLeave->get($uid);

            if (! $sub) {
                $newRows[] = [
                    'id'              => (string) Str::uuid(),
                    'user_id'         => $uid,
                    'division_id'     => $pos->division_id,
                    'raw_branch_id'   => $pos->raw_branch_id,
                    'submission_date' => $ds,
                    'status'          => $leave ? ChecklistSubmission::STATUS_EXCUSED : ChecklistSubmission::STATUS_MISSED,
                    'excuse_reason'   => $leave ? $this->leaveReason($leave) : null,
                    'excuse_source'   => $leave ? ChecklistSubmission::EXCUSE_SOURCE_SYSTEM : ChecklistSubmission::EXCUSE_SOURCE_MANUAL,
                    'excuse_ref_id'   => $leave ? (string) $leave->id : null,
                    'total_tasks'     => 0,
                    'done_tasks'      => 0,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
                if ($leave) $excused++;
            } elseif ($sub->status === ChecklistSubmission::STATUS_DRAFT) {
                // Draft + sedang cuti → dikecualikan, BUKAN "Tidak Diisi".
                if ($leave) {
                    $excuseIds[$sub->id] = $leave;
                    $excused++;
                } else {
                    $convertIds[] = $sub->id;
                }
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
            // 1 UPDATE per baris: ref_id-nya beda-beda (1 pengajuan = 1 karyawan),
            // dan jumlah orang cuti per hari selalu segelintir.
            foreach ($excuseIds as $subId => $leave) {
                ChecklistSubmission::where('id', $subId)->update([
                    'status'        => ChecklistSubmission::STATUS_EXCUSED,
                    'excuse_reason' => $this->leaveReason($leave),
                    'excuse_source' => ChecklistSubmission::EXCUSE_SOURCE_SYSTEM,
                    'excuse_ref_id' => (string) $leave->id,
                    'submitted_at'  => null,
                    'updated_at'    => now(),
                ]);
            }
        }

        $createdMissed = count(array_filter($newRows, fn ($r) => $r['status'] === ChecklistSubmission::STATUS_MISSED));

        return [$createdMissed, count($convertIds), $skipped, $excused];
    }

    /**
     * Pengajuan cuti/izin/sakit APPROVED yang menutupi tanggal ini, per user
     * (1 pengajuan sudah cukup jadi bukti). Satu query indexed via
     * idx_req_user_tgl.
     *
     * Modul absensi tidak ada di semua environment (dev lokal belum punya) —
     * dan processDate() tak dibungkus try/catch, jadi query tanpa penjagaan
     * akan membuat cron malam GAGAL TOTAL di env seperti itu. Gagal/absen =
     * anggap tak ada cuti (perilaku lama), jangan bikin job mati.
     */
    private function approvedLeavesOn(string $ds, array $userIds)
    {
        try {
            if (! Schema::hasTable('att_request')) {
                return collect();
            }

            return AttRequest::approved()
                ->whereIn('tipe', ChecklistExcuseService::EXCUSE_TYPES)
                ->whereIn('user_id', $userIds)
                ->whereDate('tanggal_from', '<=', $ds)
                ->whereDate('tanggal_to', '>=', $ds)
                ->orderBy('id')
                ->get(['id', 'user_id', 'tipe'])
                ->keyBy('user_id');
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /** Alasan yang ditulis ke excuse_reason — samakan dengan ChecklistExcuseService. */
    private function leaveReason(AttRequest $leave): string
    {
        return 'Cuti/Izin disetujui (' . $leave->tipe . ') — otomatis';
    }
}
