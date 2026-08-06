<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\AttRequest;
use App\Models\ChecklistWeeklyDayExcuse;
use App\Models\ChecklistWeeklySubmission;
use App\Models\Users;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Sinkronisasi cuti/izin yang DISETUJUI → pengecualian hari pada Form Mingguan.
 *
 * Saat pengajuan cuti/izin di-approve, task checklist di hari-hari terkait
 * ditandai "dikecualikan" otomatis (source='system') supaya tidak terhitung
 * "belum dikerjakan". Karyawan TIDAK bisa membatalkannya (lihat guard di
 * MyChecklistController::dayUnexcuse & weekToggleItem). Idempoten via unique
 * (submission_id, day_of_week) + ref_id = att_request.id.
 *
 * HANYA tipe cuti & izin (bukan libur/wfh/lembur) — keputusan produk.
 * Dipanggil AttendanceRequestWorkflow di setiap titik status→approved, dan
 * di-reconcile ulang saat karyawan membuka Form Mingguan (weekForm).
 */
class ChecklistExcuseService
{
    /** Jenis pengajuan yang mengecualikan hari checklist. */
    private const EXCUSE_TYPES = ['cuti', 'izin'];

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
