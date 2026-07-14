<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\ChecklistAuditLog;
use App\Models\ChecklistDivision;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistSubmissionItem;
use App\Models\ChecklistWeeklySubmission;
use App\Models\ChecklistWeeklySubmissionItem;
use App\Models\RawBranch;
use App\Models\Users;
use App\Models\UserPosition;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Modul Checklist Akreditasi — sisi Supervisor.
 *
 * Layar:
 *   - dashboard() : ringkasan KPI hari ini + aktivitas terbaru
 *   - reviewIndex() / reviewShow() / approve() / reject() / reopen() : review pengisian karyawan
 *   - audit() : log semua state transition (untuk audit akreditasi)
 *
 * Sisi karyawan (pengisian harian + history) ada di MyChecklistController.
 * Setup katalog (kategori & task) ada di ChecklistSetupController.
 *
 * Access control: semua method di-gate via isChecklistSupervisor(). Branch
 * scoping pakai resolveChecklistBranchId() — admin bisa dropdown, sales
 * locked ke session('raw_branch_id').
 */
class DigitalChecklistController extends Controller
{
    public $perPage = 15;

    private function guard(): void
    {
        if (! isChecklistSupervisor()) abort(403, 'Tidak punya akses ke modul Checklist Akreditasi.');
    }

    /* ─── Dashboard ────────────────────────────────────────────────────── */

    public function dashboard(Request $request)
    {
        $this->guard();

        $branchId = resolveChecklistBranchId($request->get('branch'));
        $today    = Carbon::today();

        // Karyawan aktif yang punya position hari ini (basis denominator).
        $activeUserIdsQuery = UserPosition::query()->activeOn($today);
        if ($branchId) $activeUserIdsQuery->where('raw_branch_id', $branchId);
        $activeUserIds = $activeUserIdsQuery->pluck('user_id')->unique()->values();
        $totalActiveEmployees = $activeUserIds->count();

        // Submission hari ini, di-scope ke set karyawan aktif.
        $submissionsToday = ChecklistSubmission::query()
            ->whereDate('submission_date', $today)
            ->whereIn('user_id', $activeUserIds)
            ->get();

        $countByStatus = $submissionsToday->groupBy('status')->map->count();
        $kpis = [
            'total_active'  => $totalActiveEmployees,
            'submitted'     => ($countByStatus[ChecklistSubmission::STATUS_SUBMITTED] ?? 0)
                              + ($countByStatus[ChecklistSubmission::STATUS_APPROVED] ?? 0)
                              + ($countByStatus[ChecklistSubmission::STATUS_REJECTED] ?? 0),
            'pending'       => $countByStatus[ChecklistSubmission::STATUS_SUBMITTED] ?? 0,
            'approved'      => $countByStatus[ChecklistSubmission::STATUS_APPROVED] ?? 0,
            'rejected'      => $countByStatus[ChecklistSubmission::STATUS_REJECTED] ?? 0,
            // Excused (dikecualikan) TIDAK dihitung sebagai "belum diisi": baris
            // excused sudah ikut $submissionsToday->count() dan bukan draft, jadi
            // otomatis ter-exclude dari not_submitted di bawah.
            'excused'       => $countByStatus[ChecklistSubmission::STATUS_EXCUSED] ?? 0,
            'not_submitted' => max(0, $totalActiveEmployees - $submissionsToday->count()
                              + ($countByStatus[ChecklistSubmission::STATUS_DRAFT] ?? 0)),
        ];

        // Progres per divisi: berapa karyawan tiap divisi sudah submit hari ini.
        $divisions = ChecklistDivision::query()->where('is_active', 1)->orderBy('sort_order')->get();
        $perDivision = $divisions->map(function ($div) use ($activeUserIds, $branchId, $today) {
            $userIds = UserPosition::query()->activeOn($today)
                ->where('division_id', $div->id)
                ->when($branchId, fn($q) => $q->where('raw_branch_id', $branchId))
                ->pluck('user_id')->unique();

            $total = $userIds->count();
            $subToday = ChecklistSubmission::query()
                ->whereDate('submission_date', $today)
                ->whereIn('user_id', $userIds)
                ->get(['status']);
            $submitted = $subToday->whereIn('status', [
                ChecklistSubmission::STATUS_SUBMITTED,
                ChecklistSubmission::STATUS_APPROVED,
                ChecklistSubmission::STATUS_REJECTED,
            ])->count();
            $excused = $subToday->where('status', ChecklistSubmission::STATUS_EXCUSED)->count();

            // Karyawan yang dikecualikan tidak masuk denominator (tidak perlu isi).
            $effectiveTotal = max(0, $total - $excused);

            return (object) [
                'division'   => $div,
                'total'      => $total,
                'submitted'  => $submitted,
                'excused'    => $excused,
                // 100% bila semua yang wajib sudah isi; kalau semua dikecualikan → tampil 100%.
                'pct'        => $effectiveTotal ? (int) round($submitted / $effectiveTotal * 100) : ($total ? 100 : 0),
            ];
        });

        // Aktivitas terbaru (10 submission terakhir, lintas status).
        $recent = ChecklistSubmission::query()
            ->with(['user', 'division', 'branch'])
            ->when($branchId, fn($q) => $q->where('raw_branch_id', $branchId))
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $branches = canSeeAllBranches() ? RawBranch::orderBy('name')->get() : collect();

        return view('crud.checklist.dashboard', compact(
            'kpis', 'perDivision', 'recent', 'branches', 'branchId', 'today'
        ));
    }

    /* ─── Review queue ─────────────────────────────────────────────────── */

    public function reviewIndex(Request $request)
    {
        $this->guard();

        $branchId   = resolveChecklistBranchId($request->get('branch'));
        $filterDate = $request->get('date');
        $filterDiv  = $request->get('division', 'all');
        $filterStat = $request->get('status', ChecklistSubmission::STATUS_SUBMITTED);
        $search     = trim((string) $request->get('q', ''));

        $base = ChecklistSubmission::query()
            ->when($branchId, fn($q) => $q->where('raw_branch_id', $branchId));

        // Status tab counts (hitung sebelum apply filter status).
        $countsBase = (clone $base);
        if ($filterDate) $countsBase->whereDate('submission_date', $filterDate);
        if ($filterDiv !== 'all') $countsBase->where('division_id', $filterDiv);
        if ($search !== '') {
            $countsBase->whereExists(function ($q) use ($search) {
                $q->select(DB::raw(1))->from('users')
                  ->whereRaw('users.id = sys_checklist_submission.user_id')
                  ->where('users.nama', 'like', "%{$search}%");
            });
        }
        $counts = [
            'all'       => (clone $countsBase)->count(),
            'submitted' => (clone $countsBase)->where('status', ChecklistSubmission::STATUS_SUBMITTED)->count(),
            'approved'  => (clone $countsBase)->where('status', ChecklistSubmission::STATUS_APPROVED)->count(),
            'rejected'  => (clone $countsBase)->where('status', ChecklistSubmission::STATUS_REJECTED)->count(),
            'excused'   => (clone $countsBase)->where('status', ChecklistSubmission::STATUS_EXCUSED)->count(),
            'missed'    => (clone $countsBase)->where('status', ChecklistSubmission::STATUS_MISSED)->count(),
        ];

        $query = (clone $base)->with(['user', 'division', 'branch']);
        if ($filterDate) $query->whereDate('submission_date', $filterDate);
        if ($filterDiv !== 'all') $query->where('division_id', $filterDiv);
        if ($filterStat !== 'all') $query->where('status', $filterStat);
        if ($search !== '') {
            $query->whereExists(function ($q) use ($search) {
                $q->select(DB::raw(1))->from('users')
                  ->whereRaw('users.id = sys_checklist_submission.user_id')
                  ->where('users.nama', 'like', "%{$search}%");
            });
        }

        $submissions = $query
            ->orderByDesc('submission_date')
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->paginate($this->perPage)
            ->appends($request->query());

        $divisions = ChecklistDivision::query()->where('is_active', 1)->orderBy('sort_order')->get();
        $branches  = canSeeAllBranches() ? RawBranch::orderBy('name')->get() : collect();

        return view('crud.checklist.review.index', compact(
            'submissions', 'counts', 'divisions', 'branches', 'branchId',
            'filterDate', 'filterDiv', 'filterStat', 'search'
        ));
    }

    public function reviewShow(string $id)
    {
        $this->guard();

        $submission = ChecklistSubmission::query()
            ->with(['user', 'reviewer', 'division', 'branch', 'items'])
            ->findOrFail($id);

        $this->abortIfBranchMismatch($submission);

        // Grup items per kategori untuk render rapi.
        $grouped = $submission->items->groupBy('category_title_snapshot');

        return view('crud.checklist.review.show', compact('submission', 'grouped'));
    }

    public function approve(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistSubmission::findOrFail($id);
        $this->abortIfBranchMismatch($sub);

        if ($sub->status !== ChecklistSubmission::STATUS_SUBMITTED) {
            return back()->with('error', 'Submission ini sudah tidak menunggu approval.');
        }

        $notes = trim((string) $request->get('review_notes', ''));

        $before = ['status' => $sub->status];
        $sub->update([
            'status'           => ChecklistSubmission::STATUS_APPROVED,
            'reviewer_user_id' => session('id'),
            'review_notes'     => $notes ?: null,
            'reviewed_at'      => now(),
            'updated_at'       => now(),
        ]);
        $after = ['status' => $sub->status, 'reviewer_user_id' => $sub->reviewer_user_id];

        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_APPROVE, $before, $after, $notes ?: null);

        $this->notifyEmployee($sub, 'checklist.approved', 'Checklist anda disetujui',
            'Pengisian checklist tanggal ' . $sub->submission_date->format('d M Y') . ' telah disetujui supervisor.');

        return redirect()->route('checklist.review')->with('success', 'Checklist disetujui.');
    }

    public function reject(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistSubmission::findOrFail($id);
        $this->abortIfBranchMismatch($sub);

        if ($sub->status !== ChecklistSubmission::STATUS_SUBMITTED) {
            return back()->with('error', 'Submission ini sudah tidak menunggu approval.');
        }

        $notes = trim((string) $request->get('review_notes', ''));
        if ($notes === '') {
            return back()->with('error', 'Catatan wajib diisi saat menolak checklist.');
        }

        $before = ['status' => $sub->status];
        $sub->update([
            'status'           => ChecklistSubmission::STATUS_REJECTED,
            'reviewer_user_id' => session('id'),
            'review_notes'     => $notes,
            'reviewed_at'      => now(),
            'updated_at'       => now(),
        ]);
        $after = ['status' => $sub->status, 'reviewer_user_id' => $sub->reviewer_user_id];

        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_REJECT, $before, $after, $notes);

        $this->notifyEmployee($sub, 'checklist.rejected', 'Checklist anda ditolak',
            'Pengisian ' . $sub->submission_date->format('d M Y') . ' perlu diperbaiki. Catatan: ' . $notes);

        return redirect()->route('checklist.review')->with('success', 'Checklist ditolak. Karyawan akan diberitahu.');
    }

    public function reopen(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistSubmission::findOrFail($id);
        $this->abortIfBranchMismatch($sub);

        if (! in_array($sub->status, [ChecklistSubmission::STATUS_APPROVED, ChecklistSubmission::STATUS_REJECTED], true)) {
            return back()->with('error', 'Hanya checklist yang sudah final yang bisa di-reopen.');
        }

        $notes = trim((string) $request->get('review_notes', ''));
        $before = ['status' => $sub->status];
        $sub->update([
            'status'      => ChecklistSubmission::STATUS_SUBMITTED,
            'reviewed_at' => null,
            'updated_at'  => now(),
        ]);
        $after = ['status' => $sub->status];

        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN, $before, $after, $notes ?: null);

        return redirect()->route('checklist.review.show', $sub->id)
            ->with('success', 'Checklist di-reopen — kembali ke status Menunggu Approval.');
    }

    /**
     * Override supervisor: batalkan pengecualian ("Dikecualikan" → draft) bila
     * dinilai tidak valid, sehingga karyawan diminta mengisi checklist.
     */
    public function revokeExcuse(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistSubmission::findOrFail($id);
        $this->abortIfBranchMismatch($sub);

        if ($sub->status !== ChecklistSubmission::STATUS_EXCUSED) {
            return back()->with('error', 'Hanya checklist berstatus Dikecualikan yang bisa dibatalkan.');
        }

        $before = ['status' => $sub->status, 'excuse_reason' => $sub->excuse_reason];
        $sub->update([
            'status'        => ChecklistSubmission::STATUS_DRAFT,
            'excuse_reason' => null,
            'updated_at'    => now(),
        ]);
        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN,
            $before, ['status' => $sub->status], 'Supervisor membatalkan pengecualian.');

        $this->notifyEmployee($sub, 'checklist.excuse.revoked', 'Pengecualian dibatalkan',
            'Pengecualian checklist ' . $sub->submission_date->format('d M Y') . ' dibatalkan supervisor — silakan isi checklist.');

        return redirect()->route('checklist.review')->with('success', 'Pengecualian dibatalkan. Karyawan diminta mengisi checklist.');
    }

    /* ─── Audit Trail ──────────────────────────────────────────────────── */

    public function audit(Request $request)
    {
        $this->guard();

        $branchId   = resolveChecklistBranchId($request->get('branch'));
        $filterAct  = $request->get('action', 'all');
        $filterType = $request->get('target_type', 'all');
        $dateFrom   = $request->get('date_from');
        $dateTo     = $request->get('date_to');
        $actor      = trim((string) $request->get('actor', ''));

        $query = ChecklistAuditLog::query()
            ->with(['actor', 'branch'])
            ->when($branchId,           fn($q) => $q->where('raw_branch_id', $branchId))
            ->when($filterAct !== 'all',  fn($q) => $q->where('action', $filterAct))
            ->when($filterType !== 'all', fn($q) => $q->where('target_type', $filterType))
            ->when($dateFrom,           fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,             fn($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($actor !== '',       function ($q) use ($actor) {
                $q->whereExists(function ($s) use ($actor) {
                    $s->select(DB::raw(1))->from('users')
                      ->whereRaw('users.id = sys_checklist_audit_log.actor_user_id')
                      ->where('users.nama', 'like', "%{$actor}%");
                });
            });

        $logs = $query->orderByDesc('created_at')->paginate(30)->appends($request->query());

        $branches = canSeeAllBranches() ? RawBranch::orderBy('name')->get() : collect();
        $actionOptions = ChecklistAuditLog::actionOptions();

        return view('crud.checklist.audit', compact(
            'logs', 'branches', 'branchId', 'filterAct', 'filterType',
            'dateFrom', 'dateTo', 'actor', 'actionOptions'
        ));
    }

    /* ─── Kelola Posisi Karyawan ──────────────────────────────────────────
       Supervisor assign user dengan role "Operasional" ke divisi (FO / GS /
       Asdent) + cabang. Karyawan yang sudah punya posisi aktif bisa di-
       reassign (effective_to lama di-tutup, row baru dibuat) — history
       posisi tetap utuh untuk audit. */

    public function positions(Request $request)
    {
        $this->guard();

        // Daftar group_name yang dianggap "karyawan operasional" — bisa
        // di-extend kalau ada role lain di masa depan.
        $operasionalGroupNames = ['Operasional', 'KARYAWAN'];

        $branchId = resolveChecklistBranchId($request->get('branch'));
        $search   = trim((string) $request->get('q', ''));
        $filterDiv = $request->get('division', 'all');

        // Build query: users dengan group_name di list operasional.
        // Pakai DB query langsung biar bisa filter by group nama tanpa
        // perlu eager-load semua user.
        $query = Users::query()
            ->select('users.*', 'group_path.nama as group_name', 'group_path.id as gid')
            ->join('group_path', 'users.group_id', '=', 'group_path.id')
            ->whereIn('group_path.nama', $operasionalGroupNames);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('users.nama', 'like', "%{$search}%")
                  ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('users.nama')->get()->keyBy('id');
        $userIds = $users->keys();

        // Posisi AKTIF (confirmed) & USULAN pending, per user.
        $today = Carbon::today();
        $active = UserPosition::query()
            ->activeOn($today)
            ->whereIn('user_id', $userIds)
            ->with(['division', 'branch'])
            ->get()
            ->keyBy('user_id');
        $pending = UserPosition::query()
            ->pending()
            ->whereIn('user_id', $userIds)
            ->with(['division', 'branch'])
            ->get()
            ->keyBy('user_id');

        // Hanya user yang punya posisi aktif ATAU usulan pending yang muncul —
        // user yang belum mengajukan apa pun tidak ditampilkan.
        $relevantIds = $active->keys()->merge($pending->keys())->unique();

        $rows = $relevantIds->map(function ($uid) use ($users, $active, $pending) {
            $u = $users->get($uid);
            if (! $u) return null;
            $pos  = $active->get($uid);
            $prop = $pending->get($uid);
            return (object) [
                'user'      => $u,
                'position'  => $pos,
                'division'  => $pos?->division,
                'branch'    => $pos?->branch,
                'pending'   => $prop,
            ];
        })->filter()->sortBy(fn($r) => $r->user->nama)->values();

        // Filter divisi/cabang berlaku ke posisi aktif ATAU usulan.
        if ($filterDiv !== 'all') {
            $rows = $rows->filter(fn($r) =>
                ($r->position && $r->position->division_id === $filterDiv)
                || ($r->pending && $r->pending->division_id === $filterDiv)
            )->values();
        }
        if ($branchId) {
            $rows = $rows->filter(fn($r) =>
                ($r->position && $r->position->raw_branch_id === $branchId)
                || ($r->pending && $r->pending->raw_branch_id === $branchId)
            )->values();
        }

        $divisions = ChecklistDivision::query()->where('is_active', 1)->orderBy('sort_order')->get();
        $branches  = RawBranch::orderBy('name')->get();

        // Stats
        $stats = [
            'total'       => $users->count(),
            'assigned'    => $active->count(),
            'pending'     => $pending->count(),
            'by_division' => $active->groupBy('division_id')->map->count(),
        ];

        return view('crud.checklist.positions', compact(
            'rows', 'divisions', 'branches', 'branchId', 'search', 'filterDiv', 'stats'
        ));
    }

    public function positionAssign(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'user_id'       => 'required|string|exists:users,id',
            'division_id'   => 'required|string|exists:sys_checklist_division,id',
            'raw_branch_id' => 'required|string|exists:raw_branch,id',
        ]);

        $today = Carbon::today();
        $userId = $data['user_id'];

        // Cari posisi aktif user. Kalau ada & sudah identik → no-op.
        $current = UserPosition::query()->activeOn($today)->where('user_id', $userId)->first();

        if ($current
            && $current->division_id   === $data['division_id']
            && $current->raw_branch_id === $data['raw_branch_id']) {
            return back()->with('info', 'Posisi tidak berubah — sudah sama dengan yang aktif.');
        }

        // Kalau ada posisi aktif tapi beda, tutup yang lama (effective_to=kemarin).
        if ($current) {
            $current->update([
                'effective_to' => $today->copy()->subDay(),
                'updated_at'   => now(),
            ]);
        }

        // Assign manual oleh supervisor = langsung dikonfirmasi (confirmed_at terisi).
        // Buat row baru
        $pos = UserPosition::create([
            'user_id'        => $userId,
            'division_id'    => $data['division_id'],
            'raw_branch_id'  => $data['raw_branch_id'],
            'is_primary'     => 1,
            'effective_from' => $today,
            'effective_to'   => null,
            'confirmed_at'   => now(),
            'confirmed_by'   => session('id'),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Bersihkan usulan pending user ini (kalau ada) — sudah digantikan assign manual.
        UserPosition::query()->pending()->where('user_id', $userId)
            ->where('id', '!=', $pos->id)->delete();

        $user = Users::find($userId);
        $div  = ChecklistDivision::find($data['division_id']);
        ChecklistAuditLog::write(
            'submission',
            $pos->id,
            $current ? ChecklistAuditLog::ACTION_UPDATE : ChecklistAuditLog::ACTION_CREATE,
            $current ? ['division_id' => $current->division_id, 'raw_branch_id' => $current->raw_branch_id] : null,
            ['division_id' => $pos->division_id, 'raw_branch_id' => $pos->raw_branch_id],
            'Posisi karyawan ' . ($user?->nama ?? '-') . ' diset ke ' . ($div?->name ?? $data['division_id'])
        );

        return redirect()->route('checklist.positions')
            ->with('success', ($current ? 'Posisi karyawan diperbarui.' : 'Posisi karyawan ditetapkan.')
                . ' — ' . ($user?->nama ?? '-') . ' → ' . ($div?->name ?? $data['division_id']));
    }

    public function positionRemove(string $id)
    {
        $this->guard();

        $pos = UserPosition::with(['user', 'division'])->findOrFail($id);

        // effective_to = kemarin, bukan hari ini — sebab scope activeOn() pakai
        // "effective_to >= hari ini" (inklusif). Kalau di-set hari ini, posisi
        // masih terhitung aktif seharian → seolah "tidak kecabut". Kemarin =
        // nonaktif seketika. (Konsisten dgn penutupan posisi lama saat reassign.)
        $pos->update([
            'effective_to' => Carbon::today()->subDay(),
            'updated_at'   => now(),
        ]);

        ChecklistAuditLog::write(
            'submission',
            $pos->id,
            ChecklistAuditLog::ACTION_DELETE,
            ['division_id' => $pos->division_id, 'raw_branch_id' => $pos->raw_branch_id],
            null,
            'Posisi karyawan ' . ($pos->user?->nama ?? '-') . ' (' . ($pos->division?->name ?? $pos->division_id) . ') dinonaktifkan'
        );

        return redirect()->route('checklist.positions')
            ->with('success', 'Posisi karyawan dinonaktifkan.');
    }

    /**
     * Supervisor mengonfirmasi usulan posisi (pending → aktif). Menutup posisi
     * aktif lama user (kalau ada) supaya hanya 1 yang aktif.
     */
    public function positionConfirm(string $id)
    {
        $this->guard();

        $pos = UserPosition::with(['user', 'division'])->findOrFail($id);
        if ($pos->confirmed_at) {
            return back()->with('info', 'Posisi ini sudah dikonfirmasi.');
        }

        $today = Carbon::today();

        // Tutup posisi aktif lama user (kalau ada) sebelum aktifkan usulan ini.
        UserPosition::query()->activeOn($today)
            ->where('user_id', $pos->user_id)
            ->where('id', '!=', $pos->id)
            ->update(['effective_to' => $today->copy()->subDay(), 'updated_at' => now()]);

        $pos->update([
            'confirmed_at'   => now(),
            'confirmed_by'   => session('id'),
            'effective_from' => $today,
            'updated_at'     => now(),
        ]);

        ChecklistAuditLog::write(
            'submission',
            $pos->id,
            ChecklistAuditLog::ACTION_UPDATE,
            null,
            ['division_id' => $pos->division_id, 'raw_branch_id' => $pos->raw_branch_id],
            'Konfirmasi posisi ' . ($pos->user?->nama ?? '-') . ' → ' . ($pos->division?->name ?? $pos->division_id)
        );

        return redirect()->route('checklist.positions')
            ->with('success', 'Posisi ' . ($pos->user?->nama ?? '-') . ' dikonfirmasi — karyawan sekarang bisa mengisi checklist.');
    }

    /**
     * Supervisor menolak usulan posisi (pending) — row dihapus. Yang sudah
     * dikonfirmasi tidak bisa ditolak (pakai Nonaktifkan).
     */
    public function positionReject(string $id)
    {
        $this->guard();

        $pos = UserPosition::with(['user', 'division'])->findOrFail($id);
        if ($pos->confirmed_at) {
            return back()->with('error', 'Posisi yang sudah dikonfirmasi tidak bisa ditolak — gunakan Nonaktifkan.');
        }

        ChecklistAuditLog::write(
            'submission',
            $pos->id,
            ChecklistAuditLog::ACTION_DELETE,
            ['division_id' => $pos->division_id, 'raw_branch_id' => $pos->raw_branch_id],
            null,
            'Usulan posisi ' . ($pos->user?->nama ?? '-') . ' (' . ($pos->division?->name ?? $pos->division_id) . ') ditolak'
        );

        $pos->delete();

        return redirect()->route('checklist.positions')
            ->with('success', 'Usulan posisi ditolak.');
    }

    /* ─── Review Form Mingguan (supervisor) ────────────────────────────── */

    public function weeklyReviewIndex(Request $request)
    {
        $this->guard();

        $branchId   = resolveChecklistBranchId($request->get('branch'));
        $filterWeek = $request->get('week'); // YYYY-MM-DD (Monday) atau null
        $filterDiv  = $request->get('division', 'all');
        $filterStat = $request->get('status', ChecklistWeeklySubmission::STATUS_SUBMITTED);
        $search     = trim((string) $request->get('q', ''));

        $base = ChecklistWeeklySubmission::query()
            ->when($branchId, fn($q) => $q->where('raw_branch_id', $branchId));

        $countsBase = (clone $base);
        if ($filterWeek) $countsBase->whereDate('week_start_date', $filterWeek);
        if ($filterDiv !== 'all') $countsBase->where('division_id', $filterDiv);
        if ($search !== '') {
            $countsBase->whereExists(function ($q) use ($search) {
                $q->select(DB::raw(1))->from('users')
                  ->whereRaw('users.id = sys_checklist_weekly_submission.user_id')
                  ->where('users.nama', 'like', "%{$search}%");
            });
        }
        $counts = [
            'all'       => (clone $countsBase)->count(),
            'submitted' => (clone $countsBase)->where('status', ChecklistWeeklySubmission::STATUS_SUBMITTED)->count(),
            'approved'  => (clone $countsBase)->where('status', ChecklistWeeklySubmission::STATUS_APPROVED)->count(),
            'rejected'  => (clone $countsBase)->where('status', ChecklistWeeklySubmission::STATUS_REJECTED)->count(),
            'excused'   => (clone $countsBase)->where('status', ChecklistWeeklySubmission::STATUS_EXCUSED)->count(),
            'draft'     => (clone $countsBase)->where('status', ChecklistWeeklySubmission::STATUS_DRAFT)->count(),
        ];

        $query = (clone $base)->with(['user', 'division', 'branch']);
        if ($filterWeek) $query->whereDate('week_start_date', $filterWeek);
        if ($filterDiv !== 'all') $query->where('division_id', $filterDiv);
        if ($filterStat !== 'all') $query->where('status', $filterStat);
        if ($search !== '') {
            $query->whereExists(function ($q) use ($search) {
                $q->select(DB::raw(1))->from('users')
                  ->whereRaw('users.id = sys_checklist_weekly_submission.user_id')
                  ->where('users.nama', 'like', "%{$search}%");
            });
        }

        $submissions = $query
            ->orderByDesc('week_start_date')
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->paginate($this->perPage)
            ->appends($request->query());

        $divisions = ChecklistDivision::query()->where('is_active', 1)->orderBy('sort_order')->get();
        $branches  = canSeeAllBranches() ? RawBranch::orderBy('name')->get() : collect();

        return view('crud.checklist.review.weekly-index', compact(
            'submissions', 'counts', 'divisions', 'branches', 'branchId',
            'filterWeek', 'filterDiv', 'filterStat', 'search'
        ));
    }

    public function weeklyReviewShow(string $id)
    {
        $this->guard();

        $submission = ChecklistWeeklySubmission::query()
            ->with(['user', 'reviewer', 'division', 'branch', 'items'])
            ->findOrFail($id);

        $this->abortIfBranchMismatchWeekly($submission);

        // Items di-group per day_of_week → category, supaya view bisa render
        // ala form kertas (Selasa column, Kamis column, dst).
        $grouped = $submission->items->groupBy('day_of_week')->map(function ($dayItems) {
            return $dayItems->groupBy('category_title_snapshot');
        });

        return view('crud.checklist.review.weekly-show', compact('submission', 'grouped'));
    }

    public function weeklyApprove(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistWeeklySubmission::findOrFail($id);
        $this->abortIfBranchMismatchWeekly($sub);

        if ($sub->status !== ChecklistWeeklySubmission::STATUS_SUBMITTED) {
            return back()->with('error', 'Form Mingguan ini sudah tidak menunggu approval.');
        }

        $notes = trim((string) $request->get('review_notes', ''));

        $before = ['status' => $sub->status];
        $sub->update([
            'status'           => ChecklistWeeklySubmission::STATUS_APPROVED,
            'reviewer_user_id' => session('id'),
            'review_notes'     => $notes ?: null,
            'reviewed_at'      => now(),
            'updated_at'       => now(),
        ]);
        $after = ['status' => $sub->status, 'reviewer_user_id' => $sub->reviewer_user_id];

        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_APPROVE, $before, $after, $notes ?: null);

        $this->notifyEmployeeWeekly($sub, 'checklist.weekly.approved', 'Form Mingguan anda disetujui',
            'Form Mingguan ' . $sub->weekLabel() . ' telah disetujui supervisor.');

        return redirect()->route('checklist.review.weekly')->with('success', 'Form Mingguan disetujui.');
    }

    public function weeklyReject(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistWeeklySubmission::findOrFail($id);
        $this->abortIfBranchMismatchWeekly($sub);

        if ($sub->status !== ChecklistWeeklySubmission::STATUS_SUBMITTED) {
            return back()->with('error', 'Form Mingguan ini sudah tidak menunggu approval.');
        }

        $notes = trim((string) $request->get('review_notes', ''));
        if ($notes === '') {
            return back()->with('error', 'Catatan wajib diisi saat menolak Form Mingguan.');
        }

        $before = ['status' => $sub->status];
        $sub->update([
            'status'           => ChecklistWeeklySubmission::STATUS_REJECTED,
            'reviewer_user_id' => session('id'),
            'review_notes'     => $notes,
            'reviewed_at'      => now(),
            'updated_at'       => now(),
        ]);
        $after = ['status' => $sub->status, 'reviewer_user_id' => $sub->reviewer_user_id];

        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_REJECT, $before, $after, $notes);

        $this->notifyEmployeeWeekly($sub, 'checklist.weekly.rejected', 'Form Mingguan anda ditolak',
            'Form Mingguan ' . $sub->weekLabel() . ' perlu diperbaiki. Catatan: ' . $notes);

        return redirect()->route('checklist.review.weekly')->with('success', 'Form Mingguan ditolak. Karyawan akan diberitahu.');
    }

    public function weeklyReopen(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistWeeklySubmission::findOrFail($id);
        $this->abortIfBranchMismatchWeekly($sub);

        if (! in_array($sub->status, [ChecklistWeeklySubmission::STATUS_APPROVED, ChecklistWeeklySubmission::STATUS_REJECTED], true)) {
            return back()->with('error', 'Hanya Form Mingguan yang sudah final yang bisa di-reopen.');
        }

        $notes  = trim((string) $request->get('review_notes', ''));
        $before = ['status' => $sub->status];
        $sub->update([
            'status'      => ChecklistWeeklySubmission::STATUS_SUBMITTED,
            'reviewed_at' => null,
            'updated_at'  => now(),
        ]);
        $after = ['status' => $sub->status];

        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN, $before, $after, $notes ?: null);

        return redirect()->route('checklist.review.weekly.show', $sub->id)
            ->with('success', 'Form Mingguan di-reopen — kembali ke status Menunggu Approval.');
    }

    /**
     * Override supervisor: batalkan pengecualian Form Mingguan (excused → draft).
     */
    public function weeklyRevokeExcuse(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistWeeklySubmission::findOrFail($id);
        $this->abortIfBranchMismatchWeekly($sub);

        if ($sub->status !== ChecklistWeeklySubmission::STATUS_EXCUSED) {
            return back()->with('error', 'Hanya Form Mingguan berstatus Dikecualikan yang bisa dibatalkan.');
        }

        $before = ['status' => $sub->status, 'excuse_reason' => $sub->excuse_reason];
        $sub->update([
            'status'        => ChecklistWeeklySubmission::STATUS_DRAFT,
            'excuse_reason' => null,
            'updated_at'    => now(),
        ]);
        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN,
            $before, ['status' => $sub->status], 'Supervisor membatalkan pengecualian Form Mingguan.');

        $this->notifyEmployeeWeekly($sub, 'checklist.weekly.excuse.revoked', 'Pengecualian dibatalkan',
            'Pengecualian Form Mingguan ' . $sub->weekLabel() . ' dibatalkan supervisor — silakan isi form.');

        return redirect()->route('checklist.review.weekly')->with('success', 'Pengecualian dibatalkan. Karyawan diminta mengisi Form Mingguan.');
    }

    /* ─── Helpers ──────────────────────────────────────────────────────── */

    private function abortIfBranchMismatch(ChecklistSubmission $sub): void
    {
        $branchId = resolveChecklistBranchId(null);
        // Kalau user terkunci ke branch tertentu, hanya boleh akses submission di branch yang sama.
        if ($branchId && $sub->raw_branch_id !== $branchId) {
            abort(403, 'Tidak punya akses ke checklist cabang lain.');
        }
    }

    private function abortIfBranchMismatchWeekly(ChecklistWeeklySubmission $sub): void
    {
        $branchId = resolveChecklistBranchId(null);
        if ($branchId && $sub->raw_branch_id !== $branchId) {
            abort(403, 'Tidak punya akses ke Form Mingguan cabang lain.');
        }
    }

    private function notifyEmployee(ChecklistSubmission $sub, string $type, string $title, string $body): void
    {
        try {
            AdminNotification::create([
                'user_id'       => $sub->user_id,
                'raw_branch_id' => $sub->raw_branch_id,
                'type'          => $type,
                'title'         => $title,
                'body'          => $body,
                'link'          => route('mychecklist'),
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            // jangan fail flow approval kalau notif error
        }
    }

    private function notifyEmployeeWeekly(ChecklistWeeklySubmission $sub, string $type, string $title, string $body): void
    {
        try {
            AdminNotification::create([
                'user_id'       => $sub->user_id,
                'raw_branch_id' => $sub->raw_branch_id,
                'type'          => $type,
                'title'         => $title,
                'body'          => $body,
                'link'          => route('mychecklist.weekform'),
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {}
    }
}
