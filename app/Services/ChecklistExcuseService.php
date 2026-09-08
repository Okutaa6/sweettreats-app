<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\AttRequest;
use App\Models\ChecklistAuditLog;
use App\Models\ChecklistReopenRequest;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistWeeklyDayExcuse;
use App\Models\ChecklistWeeklySubmission;
use App\Models\UserPosition;
use App\Models\Users;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sinkronisasi cuti/izin yang DISETUJUI → pengecualian checklist HARIAN &
 * MINGGUAN.
 *
 * MINGGUAN: hari-hari yang tercakup ditandai "dikecualikan" (row source='system'
 * di sys_checklist_weekly_day_excuse), idempoten via unique (submission_id,
 * day_of_week) + ref_id = att_request.id.
 *
 * HARIAN: baris sys_checklist_submission tanggal itu di-set status 'excused'
 * dengan excuse_source='system' + excuse_ref_id. Kalau barisnya belum ada,
 * dibuat — supaya cron checklist:mark-missed (00:10) tak pernah melihatnya
 * sebagai "tak ada submission" lalu mencap "Tidak Diisi" orang yang sedang cuti.
 *
 * Di kedua sisi karyawan TIDAK bisa membatalkan pengecualian sistem (lihat guard
 * di MyChecklistController::dayUnexcuse/unexcuseToday & DigitalChecklist-
 * Controller::revokeExcuse) — cabutnya lewat menolak pengajuannya.
 *
 * HANYA tipe cuti, izin & sakit (bukan libur/wfh/lembur) — keputusan produk.
 * Dipanggil AttendanceRequestWorkflow di setiap titik status→approved, dan
 * di-reconcile ulang saat karyawan membuka Form Mingguan (weekForm) atau
 * halaman checklist harian (index).
 */
class ChecklistExcuseService
{
    /**
     * Jenis pengajuan yang mengecualikan hari checklist.
     *
     * 'sakit' masuk sejak 2026-08-11 dengan alasan yang sama seperti izin:
     * orang yang tak ada di tempat tak bisa mengerjakan checklist. Tanpa ini,
     * karyawan yang sakit tetap dapat cap "checklist bolong" — dihukum untuk
     * hari yang justru sudah resmi diizinkan.
     *
     * Public supaya MarkChecklistMissed (jaring pengaman cron) memakai daftar
     * yang SAMA — dua definisi "tipe yang mengecualikan" pasti berselisih suatu
     * hari, dan selisihnya muncul sebagai cap "Tidak Diisi" yang salah.
     */
    public const EXCUSE_TYPES = ['cuti', 'izin', 'sakit'];

    /** Terapkan pengecualian untuk 1 pengajuan yang sudah approved. */
    public function excuseForLeave(AttRequest $req): void
    {
        if ($req->status !== 'approved') return;
        if (! in_array($req->tipe, self::EXCUSE_TYPES, true)) return;

        $user = Users::find($req->user_id);
        // Hanya karyawan operasional checklist (nama role == divisi FO/GS/Asdent).
        if (! checklistDivisionForGroup($user?->group_id)) return;

        $from = Carbon::parse($req->tanggal_from)->startOfDay();
        $to   = Carbon::parse($req->tanggal_to ?? $req->tanggal_from)->startOfDay();

        // Kumpulkan submission tersentuh → finalize sekali per submission.
        $touched = [];

        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $iso = $d->isoWeekday();
            if ($iso < 2 || $iso > 7) continue; // Senin (1) dilewati — klinik tutup

            $sub = $this->submissionForDate($req->user_id, $d);
            if (! $sub) continue;

            $touched[$sub->id] = $sub;

            // JANGAN timpa excuse MANUAL karyawan (mis. "alat rusak"). Hari itu
            // sudah dikecualikan, jadi row sistem tak perlu — sekaligus mencegah
            // alasan asli hilang bila cuti kelak DITOLAK (unexcuseForLeave hanya
            // menghapus source='system', maka row manual yang tak tertimpa aman).
            if (ChecklistWeeklyDayExcuse::where('submission_id', $sub->id)
                    ->where('day_of_week', $iso)->where('source', 'manual')->exists()) {
                continue;
            }

            // updateOrCreate tetap menulis walau minggu sudah submitted/approved
            // (jejak audit) — TAPI finalizeIfComplete di-guard isEditable, jadi
            // minggu final tak dipaksa reopen.
            ChecklistWeeklyDayExcuse::updateOrCreate(
                ['submission_id' => $sub->id, 'day_of_week' => $iso],
                [
                    'reason'     => 'Cuti/Izin disetujui (' . $req->tipe . ') — otomatis',
                    'source'     => 'system',
                    'ref_id'     => (string) $req->id,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        foreach ($touched as $sub) {
            $sub->load(['items', 'dayExcuses', 'daySubmits']);
            $before = $sub->status;
            // Roll-up HANYA untuk minggu yang masih editable (draft/rejected).
            // Menambah excuse cuma MENGURANGI kewajiban (hari aktif berkurang),
            // jadi minggu yang sudah final (submitted/approved) memang sudah beres —
            // melewati recompute tak pernah meninggalkannya "butuh diisi", tapi
            // MENCEGAH approved→draft yang menimpa reviewer_user_id/reviewed_at jadi
            // null (jejak approval hilang). Baris excuse tetap ditulis di atas.
            if ($sub->isEditable()) {
                $sub->recomputeStatus();
            }
            // Baru "otomatis dikirim" bila roll-up memindahkan ke submitted (mis.
            // seluruh hari kerja jadi excused) — beri tahu supervisor sekali.
            if ($before !== ChecklistWeeklySubmission::STATUS_SUBMITTED
                && $sub->status === ChecklistWeeklySubmission::STATUS_SUBMITTED) {
                $this->notifySupervisorsFinalized($sub);
            }
        }
    }

    /** Batalkan pengecualian sistem milik 1 pengajuan (mis. saat ditolak/dibatalkan). */
    public function unexcuseForLeave(AttRequest $req): void
    {
        // Hanya hapus row milik-sistem untuk ref_id ini — excuse manual karyawan
        // di hari lain tetap aman.
        $subIds = ChecklistWeeklyDayExcuse::where('source', 'system')
            ->where('ref_id', (string) $req->id)
            ->pluck('submission_id')->unique();

        ChecklistWeeklyDayExcuse::where('source', 'system')
            ->where('ref_id', (string) $req->id)
            ->delete();

        // WAJIB roll-up ulang: menghapus excuse MENAMBAH kewajiban (hari kembali
        // aktif) sehingga status hanya bisa turun/terbuka. Tanpa ini, minggu yang
        // sempat 'submitted' karena sepekan di-excuse tetap terkunci selamanya
        // (isEditable false → karyawan tak bisa isi, supervisor 0 pending).
        // Aman: minggu yang benar-benar di-ACC punya baris day_submit 'approved',
        // jadi deriveStatus() tetap 'approved' — takkan salah membuka minggu sah.
        foreach ($subIds as $sid) {
            $sub = ChecklistWeeklySubmission::with(['items', 'dayExcuses', 'daySubmits'])->find($sid);
            if ($sub) $sub->recomputeStatus();
        }
    }

    /**
     * Reconcile pengecualian dari SEMUA cuti/izin approved yang menutupi minggu
     * ini (dipanggil saat karyawan membuka Form Mingguan). Menangani cuti yang
     * disetujui SEBELUM submission minggu itu dibuat. Idempoten.
     */
    public function reconcileWeek(ChecklistWeeklySubmission $sub): void
    {
        if (! $sub->isEditable()) return;
        if (! $this->attendanceReady()) return;

        $weekStart = Carbon::parse($sub->week_start_date)->startOfWeek(Carbon::MONDAY);
        $weekEnd   = $weekStart->copy()->addDays(6);

        $leaves = AttRequest::approved()
            ->where('user_id', $sub->user_id)
            ->whereIn('tipe', self::EXCUSE_TYPES)
            ->whereDate('tanggal_from', '<=', $weekEnd->toDateString())
            ->whereDate('tanggal_to', '>=', $weekStart->toDateString())
            ->get();

        if ($leaves->isEmpty()) return;

        foreach ($leaves as $req) {
            $from = Carbon::parse($req->tanggal_from)->startOfDay()->max($weekStart);
            $to   = Carbon::parse($req->tanggal_to ?? $req->tanggal_from)->startOfDay()->min($weekEnd);
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $iso = $d->isoWeekday();
                if ($iso < 2 || $iso > 7) continue;
                // Jangan timpa excuse MANUAL — lihat catatan di excuseForLeave().
                if (ChecklistWeeklyDayExcuse::where('submission_id', $sub->id)
                        ->where('day_of_week', $iso)->where('source', 'manual')->exists()) {
                    continue;
                }
                ChecklistWeeklyDayExcuse::updateOrCreate(
                    ['submission_id' => $sub->id, 'day_of_week' => $iso],
                    [
                        'reason'     => 'Cuti/Izin disetujui (' . $req->tipe . ') — otomatis',
                        'source'     => 'system',
                        'ref_id'     => (string) $req->id,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        $sub->load(['items', 'dayExcuses', 'daySubmits']);
        $sub->recomputeStatus();
    }

    /* ─── Checklist HARIAN ────────────────────────────────────────────── */

    /**
     * Terapkan pengecualian HARIAN untuk 1 pengajuan yang sudah approved.
     * Tiap tanggal dalam rentang (Senin dilewati — klinik tutup) barisnya jadi
     * 'excused' milik sistem. Idempoten.
     */
    public function excuseDailyForLeave(AttRequest $req): void
    {
        if ($req->status !== 'approved') return;
        if (! in_array($req->tipe, self::EXCUSE_TYPES, true)) return;

        $user = Users::find($req->user_id);
        // Hanya karyawan operasional checklist (nama role == divisi FO/GS/Asdent).
        if (! checklistDivisionForGroup($user?->group_id)) return;

        $from = Carbon::parse($req->tanggal_from)->startOfDay();
        $to   = Carbon::parse($req->tanggal_to ?? $req->tanggal_from)->startOfDay();

        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if ($d->isoWeekday() === 1) continue; // Senin — tak ada checklist harian
            $this->applyDailyExcuse((string) $req->user_id, $d, $req);
        }
    }

    /**
     * Cabut pengecualian HARIAN milik 1 pengajuan (saat ditolak/dibatalkan).
     *
     * Tanggal yang sudah LEWAT dikembalikan ke 'missed', bukan 'draft': cron
     * 00:10 sudah melewati tanggal itu dan takkan menengoknya lagi, jadi baris
     * 'draft' akan jadi hantu yang bikin dashboard "Tidak Diisi" bohong. Kecuali
     * karyawan punya grant "buka kembali" aktif — di situ ia memang sedang
     * diberi kesempatan mengisi.
     */
    public function unexcuseDailyForLeave(AttRequest $req): void
    {
        $rows = ChecklistSubmission::where('excuse_source', ChecklistSubmission::EXCUSE_SOURCE_SYSTEM)
            ->where('excuse_ref_id', (string) $req->id)
            // Hanya yang MASIH excused — kalau supervisor sudah membuka/menilai
            // ulang hari itu, barisnya bukan milik kita lagi.
            ->where('status', ChecklistSubmission::STATUS_EXCUSED)
            ->get();
        if ($rows->isEmpty()) return;

        $today = Carbon::today();

        foreach ($rows as $sub) {
            $date   = Carbon::parse($sub->submission_date)->startOfDay();
            $revert = ChecklistSubmission::STATUS_DRAFT;
            if ($date->lt($today) && ! ChecklistReopenRequest::activeDailyGrant($sub->user_id, $date)) {
                $revert = ChecklistSubmission::STATUS_MISSED;
            }

            $before = ['status' => $sub->status, 'excuse_reason' => $sub->excuse_reason];
            $sub->update([
                'status'        => $revert,
                'excuse_reason' => null,
                'excuse_source' => ChecklistSubmission::EXCUSE_SOURCE_MANUAL, // kembali ke default kolom
                'excuse_ref_id' => null,
                'updated_at'    => now(),
            ]);
            $this->auditDaily($sub->id, ChecklistAuditLog::ACTION_REOPEN, $before, ['status' => $revert],
                'Pengecualian otomatis dicabut — pengajuan cuti/izin tidak lagi berlaku.');
        }
    }

    /**
     * Reconcile 1 (user, tanggal) dari SEMUA cuti approved yang menutupinya.
     * Dipakai saat karyawan membuka halaman checklist harian & oleh command
     * backfill — menangani cuti yang di-ACC sebelum baris harian dibuat, dan
     * hook approval yang gagal diam-diam. Idempoten.
     *
     * Return true bila tanggal itu berakhir 'excused'.
     */
    public function reconcileDailyDate(string $userId, CarbonInterface $date): bool
    {
        $d = Carbon::parse($date)->startOfDay();
        if ($d->isoWeekday() === 1) return false;
        if (! $this->attendanceReady()) return false;

        $leave = AttRequest::approved()
            ->where('user_id', $userId)
            ->whereIn('tipe', self::EXCUSE_TYPES)
            ->whereDate('tanggal_from', '<=', $d->toDateString())
            ->whereDate('tanggal_to', '>=', $d->toDateString())
            ->orderBy('id')
            ->first();                       // 1 pengajuan sudah cukup jadi bukti
        if (! $leave) return false;

        $user = Users::find($userId);
        if (! checklistDivisionForGroup($user?->group_id)) return false;

        return $this->applyDailyExcuse($userId, $d, $leave);
    }

    /**
     * Satu-satunya jalur tulis pengecualian harian. Return true bila baris
     * berakhir 'excused'.
     *
     * Yang TIDAK pernah ditimpa:
     *   - submitted/approved/rejected → hasil kerja & keputusan supervisor
     *   - excused MANUAL              → alasan asli karyawan harus selamat kalau
     *                                   cutinya kelak ditolak (unexcuse hanya
     *                                   menyentuh source='system')
     * Yang boleh ditimpa: draft, missed, dan excused milik sistem (refresh).
     * 'missed' memang HARUS bisa ditimpa — itu justru bug yang diperbaiki:
     * orang cuti terlanjur dicap "Tidak Diisi" oleh cron.
     */
    private function applyDailyExcuse(string $userId, Carbon $date, AttRequest $req): bool
    {
        $ds     = $date->toDateString();
        $reason = 'Cuti/Izin disetujui (' . $req->tipe . ') — otomatis';

        $sub = ChecklistSubmission::where('user_id', $userId)
            ->whereDate('submission_date', $ds)
            ->first();

        if ($sub) {
            if (in_array($sub->status, [
                ChecklistSubmission::STATUS_SUBMITTED,
                ChecklistSubmission::STATUS_APPROVED,
                ChecklistSubmission::STATUS_REJECTED,
            ], true)) return false;

            if ($sub->status === ChecklistSubmission::STATUS_EXCUSED
                && ($sub->excuse_source ?? ChecklistSubmission::EXCUSE_SOURCE_MANUAL)
                    !== ChecklistSubmission::EXCUSE_SOURCE_SYSTEM) {
                return true;   // sudah dikecualikan karyawan sendiri — biarkan
            }

            $before = ['status' => $sub->status, 'excuse_source' => $sub->excuse_source];
            $sub->update([
                'status'        => ChecklistSubmission::STATUS_EXCUSED,
                'excuse_reason' => $reason,
                'excuse_source' => ChecklistSubmission::EXCUSE_SOURCE_SYSTEM,
                'excuse_ref_id' => (string) $req->id,
                'submitted_at'  => null,
                'updated_at'    => now(),
            ]);
            // Audit hanya saat status benar-benar berubah — reconcile jalan tiap
            // buka halaman, jangan membanjiri log dengan baris identik.
            if (($before['status'] ?? null) !== ChecklistSubmission::STATUS_EXCUSED) {
                $this->auditDaily($sub->id, ChecklistAuditLog::ACTION_EXCUSE,
                    $before, ['status' => $sub->status], $reason);
            }

            return true;
        }

        // Belum ada baris → buat langsung 'excused'. Tanpa ini, cron 00:10 melihat
        // "tak ada submission" lalu mencetak baris 'missed'. Item sengaja TIDAK
        // di-bootstrap (total/done 0) — bentuknya sama dengan baris buatan cron;
        // kalau pengecualian dicabut, index() yang mem-bootstrap saat dibuka.
        [$divisionId, $branchId] = $this->dailyScopeOn($userId, $date);
        if (! $divisionId || ! $branchId) return false;   // tak bisa di-scope

        try {
            ChecklistSubmission::create([
                'id'              => (string) Str::uuid(),
                'user_id'         => $userId,
                'division_id'     => $divisionId,
                'raw_branch_id'   => $branchId,
                'submission_date' => $ds,
                'status'          => ChecklistSubmission::STATUS_EXCUSED,
                'excuse_reason'   => $reason,
                'excuse_source'   => ChecklistSubmission::EXCUSE_SOURCE_SYSTEM,
                'excuse_ref_id'   => (string) $req->id,
                'total_tasks'     => 0,
                'done_tasks'      => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (QueryException $e) {
            // Balapan dengan firstOrCreate di index()/cron — uniq_user_date yang
            // jadi wasit. Kalau barisnya memang sudah ada, ulangi lewat jalur
            // update (sekali saja: percobaan kedua pasti menemukan barisnya).
            // Error lain bukan urusan kita — lempar apa adanya.
            $exists = ChecklistSubmission::where('user_id', $userId)
                ->whereDate('submission_date', $ds)->exists();
            if (! $exists) throw $e;

            return $this->applyDailyExcuse($userId, $date, $req);
        }

        return true;
    }

    /**
     * [division_id, raw_branch_id] efektif user pada tanggal itu (rolling-aware).
     * Sengaja memakai UserPosition::activeOn seperti MarkChecklistMissed, supaya
     * baris buatan kedua jalur identik untuk orang & tanggal yang sama.
     */
    private function dailyScopeOn(string $userId, Carbon $date): array
    {
        $pos = UserPosition::query()->activeOn($date)
            ->where('user_id', $userId)
            ->first(['division_id', 'raw_branch_id']);

        return [$pos?->division_id, $pos?->raw_branch_id];
    }

    /**
     * Audit tanpa risiko: sinkronisasi ini juga jalan dari CLI (cron & backfill)
     * di mana actor session kosong. Gagal menulis audit tak boleh membatalkan
     * pengecualiannya — itu jauh lebih penting daripada jejaknya.
     */
    private function auditDaily(string $subId, string $action, ?array $before, ?array $after, ?string $notes): void
    {
        try {
            ChecklistAuditLog::write('submission', $subId, $action, $before, $after, $notes);
        } catch (\Throwable $e) {
            // biarkan
        }
    }

    /**
     * Modul absensi/cuti (att_request) tidak ada di semua environment — dev
     * lokal belum punya. Semua jalur yang MEMBACA tabel itu harus jadi no-op
     * diam, bukan fatal. Di-memo per request (satu query information_schema).
     */
    private function attendanceReady(): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;

        try {
            return $ok = Schema::hasTable('att_request');
        } catch (\Throwable $e) {
            return $ok = false;
        }
    }

    /* ─── Helper ──────────────────────────────────────────────────────── */

    /**
     * Ambil/siapkan weekly submission untuk (user, tanggal). Kalau belum ada,
     * buat + bootstrap item sesuai cabang efektif hari itu (rolling-aware) dan
     * divisi dari role. Null bila user tak punya posisi/divisi aktif (tak bisa
     * di-scope).
     */
    private function submissionForDate(string $userId, Carbon $date): ?ChecklistWeeklySubmission
    {
        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);

        $sub = ChecklistWeeklySubmission::where('user_id', $userId)
            ->whereDate('week_start_date', $weekStart)->first();
        if ($sub) return $sub;

        $branchId = positionBranchOn($userId, $date);
        $user     = Users::find($userId);
        $division = checklistDivisionForGroup($user?->group_id);
        if (! $branchId || ! $division) return null;

        $sub = ChecklistWeeklySubmission::create([
            'id'              => (string) Str::uuid(),
            'user_id'         => $userId,
            'division_id'     => $division->id,
            'raw_branch_id'   => $branchId,
            'week_start_date' => $weekStart->toDateString(),
            'year_week'       => $weekStart->isoFormat('GGGG-[W]WW'),
            'status'          => ChecklistWeeklySubmission::STATUS_DRAFT,
            'total_tasks'     => 0,
            'done_tasks'      => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $sub->bootstrapItems($division->id, $branchId);

        return $sub;
    }

    private function notifySupervisorsFinalized(ChecklistWeeklySubmission $sub): void
    {
        try {
            $user = Users::find($sub->user_id);
            $name = $user?->nama ?? 'Karyawan';
            $body = $name . ' (' . $sub->division_id . ') — Form Mingguan ' . $sub->weekLabel()
                . ' otomatis dikirim (cuti/izin sepekan).';
            foreach (checklistSupervisorRoleNamesForDivision($sub->division_id) as $roleName) {
                AdminNotification::notifyRole(
                    $roleName,
                    'checklist.weekly.submitted',
                    "Form Mingguan (cuti) — {$name}",
                    $body,
                    route('checklist.review.weekly.show', $sub->id),
                    $sub->raw_branch_id
                );
            }
        } catch (\Throwable $e) {
            // notif gagal tak boleh menggagalkan sinkronisasi excuse
        }
    }
}
