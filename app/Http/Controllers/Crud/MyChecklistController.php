<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\ChecklistAuditLog;
use App\Models\ChecklistCategory;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistSubmissionItem;
use App\Models\ChecklistTask;
use App\Models\ChecklistWeeklySubmission;
use App\Models\ChecklistWeeklySubmissionItem;
use App\Models\Users;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pengisian checklist harian — sisi karyawan.
 *
 * Flow:
 *   1. index(): resolve user position aktif → firstOrCreate submission hari
 *      ini (status draft) → bootstrap items snapshot dari katalog aktif
 *   2. toggleItem(): AJAX optimistic update is_done
 *   3. updateNote(): AJAX update catatan inline
 *   4. submit(): validate → status = submitted → notify supervisor + audit log
 *   5. reopenSelf(): saat status rejected, karyawan bisa balik ke draft
 *      untuk perbaiki
 *   6. history(): list submission user, paginate per bulan
 *
 * Tidak butuh isChecklistSupervisor() — semua user yang punya position
 * aktif bisa akses.
 */
class MyChecklistController extends Controller
{
    public $perPage = 30;

    /* ─── Halaman pengisian harian ───────────────────────────────────── */

    public function index(Request $request)
    {
        $position = currentUserPosition();

        if (! $position) {
            // Empty state — karyawan belum di-assign divisi/cabang.
            return view('crud.checklist.my.index', [
                'position'      => null,
                'submission'    => null,
                'grouped'       => collect(),
                'today'         => Carbon::today(),
                'progress'      => ['done' => 0, 'total' => 0, 'pct' => 0],
                'rejectedNote'  => null,
            ]);
        }

        $today = Carbon::today();

        $submission = ChecklistSubmission::firstOrCreate(
            ['user_id' => session('id'), 'submission_date' => $today->toDateString()],
            [
                'id'             => (string) Str::uuid(),
                'division_id'    => $position->division_id,
                'raw_branch_id'  => $position->raw_branch_id,
                'status'         => ChecklistSubmission::STATUS_DRAFT,
                'total_tasks'    => 0,
                'done_tasks'     => 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]
        );

        // Bootstrap items kalau kosong (submission baru tercipta).
        if ($submission->items()->count() === 0) {
            $this->bootstrapItems($submission, $position->division_id, $position->raw_branch_id);
        } else if ($submission->isEditable()) {
            // Reconcile: kalau ada task baru di katalog yang belum punya item, tambahkan.
            $this->reconcileItems($submission, $position->division_id, $position->raw_branch_id);
        }

        $items = $submission->items()->get();
        $grouped = $items->groupBy('category_title_snapshot');
        $progress = [
            'done'  => $submission->done_tasks,
            'total' => $submission->total_tasks,
            'pct'   => $submission->progressPct(),
        ];

        // Kalau status rejected, tampilkan banner dengan catatan supervisor.
        $rejectedNote = $submission->status === ChecklistSubmission::STATUS_REJECTED
            ? $submission->review_notes
            : null;

        // ─── Extra widgets: heatmap 14 hari, stats, recent activity ─────
        $extras = $this->buildSidebarExtras($today);

        return view('crud.checklist.my.index', compact(
            'position', 'submission', 'grouped', 'today', 'progress', 'rejectedNote', 'extras'
        ));
    }

    /**
     * Compute data untuk widget-widget di samping/bawah checklist:
     *  - heatmap 14 hari (status per hari)
     *  - streak (hari berturut sudah submit)
     *  - rate minggu ini
     *  - recent activity (5 submission terakhir)
     */
    private function buildSidebarExtras(Carbon $today): array
    {
        $uid = session('id');

        // Ambil 30 hari terakhir sekali query, di-key by date string.
        $recentSubs = ChecklistSubmission::query()
            ->where('user_id', $uid)
            ->where('submission_date', '>=', $today->copy()->subDays(30)->toDateString())
            ->orderByDesc('submission_date')
            ->get()
            ->keyBy(fn($s) => $s->submission_date->toDateString());

        // 14-day heatmap timeline (oldest → newest, left → right)
        $heatmap = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i);
            $s = $recentSubs->get($d->toDateString());
            $heatmap[] = (object) [
                'date'     => $d,
                'day'      => $d->format('j'),
                'short'    => $d->translatedFormat('D'),
                'is_today' => $d->isSameDay($today),
                'status'   => $s?->status,
                'pct'      => $s ? $s->progressPct() : null,
            ];
        }

        // Streak: hari berturut-turut isi (status submitted/approved/rejected) dari today mundur.
        $streak = 0;
        $cursor = $today->copy();
        $doneStatuses = [
            ChecklistSubmission::STATUS_SUBMITTED,
            ChecklistSubmission::STATUS_APPROVED,
            ChecklistSubmission::STATUS_REJECTED,
        ];
        for ($i = 0; $i < 30; $i++) {
            $s = $recentSubs->get($cursor->toDateString());
            if (! $s || ! in_array($s->status, $doneStatuses, true)) break;
            $streak++;
            $cursor->subDay();
        }

        // Rate minggu ini (Senin - Minggu): berapa hari sudah submit / total hari sampai today
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $daysElapsed = $today->diffInDays($weekStart) + 1;
        $weekSubmitted = 0;
        $cursor = $weekStart->copy();
        while ($cursor->lte($today)) {
            $s = $recentSubs->get($cursor->toDateString());
            if ($s && in_array($s->status, $doneStatuses, true)) $weekSubmitted++;
            $cursor->addDay();
        }
        $weekRatePct = $daysElapsed > 0 ? (int) round($weekSubmitted / $daysElapsed * 100) : 0;

        // Recent 5 submissions (untuk activity feed)
        $recent = ChecklistSubmission::query()
            ->where('user_id', $uid)
            ->orderByDesc('submission_date')
            ->limit(5)
            ->get();

        return [
            'heatmap'        => $heatmap,
            'streak'         => $streak,
            'week_submitted' => $weekSubmitted,
            'week_total'     => $daysElapsed,
            'week_rate'      => $weekRatePct,
            'recent'         => $recent,
        ];
    }

    /**
     * AJAX: toggle is_done satu item. Return JSON { ok, progress }.
     */
    public function toggleItem(Request $request, string $id)
    {
        $item = ChecklistSubmissionItem::with('submission')->findOrFail($id);
        $sub  = $item->submission;

        // Hanya pemilik submission yang boleh toggle.
        if ($sub->user_id !== session('id')) abort(403);
        if (! $sub->isEditable()) {
            return response()->json(['ok' => false, 'error' => 'Checklist sudah submitted/approved — tidak bisa diubah.'], 422);
        }

        $newState = ! $item->is_done;
        $item->update([
            'is_done' => $newState,
            'done_at' => $newState ? now() : null,
        ]);

        // Recount header
        $done = $sub->items()->where('is_done', 1)->count();
        $sub->update(['done_tasks' => $done, 'updated_at' => now()]);

        return response()->json([
            'ok'       => true,
            'is_done'  => $newState,
            'progress' => [
                'done'  => $done,
                'total' => $sub->total_tasks,
                'pct'   => $sub->total_tasks ? (int) round($done / $sub->total_tasks * 100) : 0,
            ],
        ]);
    }

    public function updateNote(Request $request, string $id)
    {
        $item = ChecklistSubmissionItem::with('submission')->findOrFail($id);
        $sub  = $item->submission;
        if ($sub->user_id !== session('id')) abort(403);
        if (! $sub->isEditable()) return response()->json(['ok' => false], 422);

        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $item->update(['note' => $data['note'] ?? null]);

        return response()->json(['ok' => true]);
    }

    public function submit(Request $request)
    {
        $sub = ChecklistSubmission::where('user_id', session('id'))
            ->whereDate('submission_date', Carbon::today())
            ->firstOrFail();

        if (! $sub->isEditable()) {
            return back()->with('error', 'Checklist sudah disubmit.');
        }
        if ($sub->done_tasks === 0) {
            return back()->with('error', 'Minimal 1 tugas harus dicentang sebelum submit.');
        }

        $before = ['status' => $sub->status];
        $sub->update([
            'status'       => ChecklistSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'updated_at'   => now(),
        ]);

        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_SUBMIT,
            $before, ['status' => $sub->status, 'done' => $sub->done_tasks, 'total' => $sub->total_tasks]);

        // Notif ke supervisor cabang (broadcast — user_id NULL, raw_branch_id terisi).
        try {
            $user = Users::find(session('id'));
            $name = $user?->nama ?? 'Karyawan';
            AdminNotification::notify(
                'checklist.submitted',
                "Checklist baru dari {$name}",
                $name . ' (' . $sub->division_id . ') sudah submit checklist hari ini.',
                route('checklist.review.show', $sub->id),
                $sub->raw_branch_id
            );
        } catch (\Throwable $e) {
            // jangan fail submit kalau notif error
        }

        return redirect()->route('mychecklist')->with('success', 'Checklist disubmit. Menunggu review supervisor.');
    }

    public function reopenSelf(Request $request)
    {
        $sub = ChecklistSubmission::where('user_id', session('id'))
            ->whereDate('submission_date', Carbon::today())
            ->firstOrFail();

        if ($sub->status !== ChecklistSubmission::STATUS_REJECTED) {
            return back()->with('error', 'Hanya checklist berstatus Rejected yang bisa diperbaiki.');
        }

        $before = ['status' => $sub->status];
        $sub->update([
            'status'      => ChecklistSubmission::STATUS_DRAFT,
            'submitted_at' => null,
            'updated_at'  => now(),
        ]);
        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN,
            $before, ['status' => $sub->status], 'Karyawan membuka kembali setelah ditolak.');

        return redirect()->route('mychecklist')->with('success', 'Checklist dibuka kembali untuk diperbaiki.');
    }

    /* ─── Form Checklist Mingguan (karyawan) ──────────────────────────── */

    /**
     * Halaman Form Mingguan. ?week=offset (default 0 = minggu ini).
     *
     * Flow mirip index():
     *  1. firstOrCreate submission untuk (user, Monday of target week)
     *  2. Bootstrap items dari kategori cadence=weekly + task.day_of_week
     *  3. Render grid per-hari.
     */
    public function weekForm(Request $request)
    {
        $position = currentUserPosition();
        if (! $position) {
            return view('crud.checklist.my.weekform', [
                'position'   => null,
                'submission' => null,
                'days'       => [],
                'grouped'    => collect(),
                'progress'   => ['done' => 0, 'total' => 0, 'pct' => 0],
                'weekOffset' => 0,
                'weekStart'  => Carbon::today()->startOfWeek(Carbon::MONDAY),
                'weekEnd'    => Carbon::today()->endOfWeek(Carbon::SUNDAY),
                'rejectedNote' => null,
            ]);
        }

        $weekOffset = (int) $request->get('week', 0);
        $today      = Carbon::today();
        $weekStart  = $today->copy()->startOfWeek(Carbon::MONDAY)->addWeeks($weekOffset);
        $weekEnd    = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $yearWeek   = $weekStart->isoFormat('GGGG-[W]WW');

        // Future week — boleh dilihat tapi tidak boleh diisi (no submission auto-create).
        $isFuture = $weekStart->gt($today->copy()->startOfWeek(Carbon::MONDAY));

        $submission = null;
        if (! $isFuture) {
            $submission = ChecklistWeeklySubmission::firstOrCreate(
                ['user_id' => session('id'), 'week_start_date' => $weekStart->toDateString()],
                [
                    'id'              => (string) Str::uuid(),
                    'division_id'     => $position->division_id,
                    'raw_branch_id'   => $position->raw_branch_id,
                    'year_week'       => $yearWeek,
                    'status'          => ChecklistWeeklySubmission::STATUS_DRAFT,
                    'total_tasks'     => 0,
                    'done_tasks'      => 0,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );

            if ($submission->items()->count() === 0) {
                $this->bootstrapWeeklyItems($submission, $position->division_id, $position->raw_branch_id);
            } elseif ($submission->isEditable()) {
                $this->reconcileWeeklyItems($submission, $position->division_id, $position->raw_branch_id);
            }
        }

        // Build 6 hari row metadata (Selasa → Minggu). Senin dilewati — klinik tutup.
        $days = [];
        for ($i = 1; $i < 7; $i++) {
            $d = $weekStart->copy()->addDays($i);
            $iso = $i + 1; // 2=Sel … 7=Min
            $days[] = (object) [
                'iso'       => $iso,
                'date'      => $d,
                'label'     => ChecklistTask::dayLabels()[$iso],
                'short'     => ChecklistTask::dayShortLabels()[$iso],
                'is_today'  => $d->isSameDay($today),
                'is_past'   => $d->lt($today),
                'is_future' => $d->gt($today),
            ];
        }

        // Group items by day_of_week → category — supaya view tinggal iterasi.
        $grouped = collect();
        if ($submission) {
            $items = $submission->items()->get();
            $grouped = $items->groupBy('day_of_week')->map(function ($dayItems) {
                return $dayItems->groupBy('category_title_snapshot');
            });
        }

        $progress = [
            'done'  => $submission?->done_tasks ?? 0,
            'total' => $submission?->total_tasks ?? 0,
            'pct'   => $submission?->progressPct() ?? 0,
        ];

        $rejectedNote = ($submission && $submission->status === ChecklistWeeklySubmission::STATUS_REJECTED)
            ? $submission->review_notes
            : null;

        return view('crud.checklist.my.weekform', compact(
            'position', 'submission', 'days', 'grouped', 'progress',
            'weekOffset', 'weekStart', 'weekEnd', 'rejectedNote', 'isFuture'
        ));
    }

    public function weekToggleItem(Request $request, string $id)
    {
        $item = ChecklistWeeklySubmissionItem::with('submission')->findOrFail($id);
        $sub  = $item->submission;

        if ($sub->user_id !== session('id')) abort(403);
        if (! $sub->isEditable()) {
            return response()->json(['ok' => false, 'error' => 'Form mingguan sudah submitted/approved.'], 422);
        }

        $newState = ! $item->is_done;
        $item->update([
            'is_done' => $newState,
            'done_at' => $newState ? now() : null,
        ]);

        $done = $sub->items()->where('is_done', 1)->count();
        $sub->update(['done_tasks' => $done, 'updated_at' => now()]);

        return response()->json([
            'ok'      => true,
            'is_done' => $newState,
            'progress' => [
                'done'  => $done,
                'total' => $sub->total_tasks,
                'pct'   => $sub->total_tasks ? (int) round($done / $sub->total_tasks * 100) : 0,
            ],
        ]);
    }

    public function weekUpdateNote(Request $request, string $id)
    {
        $item = ChecklistWeeklySubmissionItem::with('submission')->findOrFail($id);
        $sub  = $item->submission;
        if ($sub->user_id !== session('id')) abort(403);
        if (! $sub->isEditable()) return response()->json(['ok' => false], 422);

        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $item->update(['note' => $data['note'] ?? null]);

        return response()->json(['ok' => true]);
    }

    public function weekSubmit(Request $request)
    {
        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);

        $sub = ChecklistWeeklySubmission::where('user_id', session('id'))
            ->whereDate('week_start_date', $weekStart)
            ->firstOrFail();

        if (! $sub->isEditable()) {
            return back()->with('error', 'Form mingguan sudah disubmit.');
        }
        if ($sub->done_tasks === 0) {
            return back()->with('error', 'Minimal 1 tugas harus dicentang sebelum submit.');
        }

        $before = ['status' => $sub->status];
        $sub->update([
            'status'       => ChecklistWeeklySubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'updated_at'   => now(),
        ]);

        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_SUBMIT,
            $before, ['status' => $sub->status, 'done' => $sub->done_tasks, 'total' => $sub->total_tasks]);

        // Notif supervisor cabang.
        try {
            $user = Users::find(session('id'));
            $name = $user?->nama ?? 'Karyawan';
            AdminNotification::notify(
                'checklist.weekly.submitted',
                "Form Mingguan dari {$name}",
                $name . ' (' . $sub->division_id . ') submit Form Mingguan ' . $sub->weekLabel() . '.',
                route('checklist.review.weekly.show', $sub->id),
                $sub->raw_branch_id
            );
        } catch (\Throwable $e) {}

        return redirect()->route('mychecklist.weekform')->with('success', 'Form Mingguan disubmit. Menunggu review supervisor.');
    }

    public function weekReopenSelf(Request $request)
    {
        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $sub = ChecklistWeeklySubmission::where('user_id', session('id'))
            ->whereDate('week_start_date', $weekStart)
            ->firstOrFail();

        if ($sub->status !== ChecklistWeeklySubmission::STATUS_REJECTED) {
            return back()->with('error', 'Hanya Form Mingguan berstatus Rejected yang bisa diperbaiki.');
        }

        $before = ['status' => $sub->status];
        $sub->update([
            'status'       => ChecklistWeeklySubmission::STATUS_DRAFT,
            'submitted_at' => null,
            'updated_at'   => now(),
        ]);
        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN,
            $before, ['status' => $sub->status], 'Karyawan membuka Form Mingguan setelah ditolak.');

        return redirect()->route('mychecklist.weekform')->with('success', 'Form Mingguan dibuka kembali untuk diperbaiki.');
    }

    /**
     * Snapshot task aktif kategori weekly → weekly_submission_item.
     * Hanya task yang punya day_of_week valid (1-7) yang di-include.
     */
    private function bootstrapWeeklyItems(ChecklistWeeklySubmission $sub, string $divisionId, string $branchId): void
    {
        $categories = ChecklistCategory::query()
            ->where('division_id', $divisionId)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->where('cadence', ChecklistCategory::CADENCE_WEEKLY)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('raw_branch_id')->orWhere('raw_branch_id', $branchId);
            })
            ->with(['tasks' => function ($q) {
                $q->whereNull('deleted_at')->where('is_active', 1)
                  ->whereNotNull('day_of_week')
                  ->whereIn('day_of_week', [2, 3, 4, 5, 6, 7])
                  ->orderBy('day_of_week')->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        $rows = [];
        foreach ($categories as $cat) {
            foreach ($cat->tasks as $task) {
                $rows[] = [
                    'id'                      => (string) Str::uuid(),
                    'submission_id'           => $sub->id,
                    'task_id'                 => $task->id,
                    'category_title_snapshot' => $cat->name,
                    'task_title_snapshot'     => $task->title,
                    'category_sort_order'     => (int) $cat->sort_order,
                    'task_sort_order'         => (int) $task->sort_order,
                    'day_of_week'             => (int) $task->day_of_week,
                    'is_done'                 => 0,
                    'note'                    => null,
                    'done_at'                 => null,
                    'created_at'              => now(),
                ];
            }
        }
        if ($rows) DB::table('sys_checklist_weekly_submission_item')->insert($rows);

        $sub->update(['total_tasks' => count($rows), 'updated_at' => now()]);
    }

    /**
     * Tambah task baru ke weekly submission draft. Skip yg sudah ada.
     */
    private function reconcileWeeklyItems(ChecklistWeeklySubmission $sub, string $divisionId, string $branchId): void
    {
        $existingTaskIds = $sub->items()->whereNotNull('task_id')->pluck('task_id')->toArray();

        $categories = ChecklistCategory::query()
            ->where('division_id', $divisionId)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->where('cadence', ChecklistCategory::CADENCE_WEEKLY)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('raw_branch_id')->orWhere('raw_branch_id', $branchId);
            })
            ->with(['tasks' => function ($q) {
                $q->whereNull('deleted_at')->where('is_active', 1)
                  ->whereNotNull('day_of_week')
                  ->whereIn('day_of_week', [2, 3, 4, 5, 6, 7])
                  ->orderBy('day_of_week')->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        $newRows = [];
        foreach ($categories as $cat) {
            foreach ($cat->tasks as $task) {
                if (in_array($task->id, $existingTaskIds, true)) continue;
                $newRows[] = [
                    'id'                      => (string) Str::uuid(),
                    'submission_id'           => $sub->id,
                    'task_id'                 => $task->id,
                    'category_title_snapshot' => $cat->name,
                    'task_title_snapshot'     => $task->title,
                    'category_sort_order'     => (int) $cat->sort_order,
                    'task_sort_order'         => (int) $task->sort_order,
                    'day_of_week'             => (int) $task->day_of_week,
                    'is_done'                 => 0,
                    'note'                    => null,
                    'done_at'                 => null,
                    'created_at'              => now(),
                ];
            }
        }
        if ($newRows) {
            DB::table('sys_checklist_weekly_submission_item')->insert($newRows);
            $total = $sub->items()->count();
            $sub->update(['total_tasks' => $total, 'updated_at' => now()]);
        }
    }

    /**
     * Tampilan mingguan (grid kalendar ala spreadsheet) — 7 hari dgn TODAY di
     * tengah. Past hari read-only checkmark, today clickable toggle, future
     * empty read-only.
     *
     * Bisa di-page via ?week=N (offset minggu, default 0).
     */
    public function weekly(Request $request)
    {
        $position = currentUserPosition();
        if (! $position) {
            // Sama empty-state seperti index — gak punya posisi
            return view('crud.checklist.my.weekly', [
                'position'   => null,
                'rows'       => collect(),
                'days'       => [],
                'weekOffset' => 0,
                'today'      => Carbon::today(),
                'submission' => null,
            ]);
        }

        $weekOffset = (int) $request->get('week', 0);
        $today = Carbon::today();

        // Minggu mulai SELASA → 7 hari (Sel, Rab, Kam, Jum, Sab, Min, Sen).
        // Klinik biasanya tutup Senin sehingga Selasa adalah hari pertama
        // operasional. Klik next/prev geser 7 hari sekali.
        $start = $today->copy()->startOfWeek(Carbon::TUESDAY)->addWeeks($weekOffset);
        $end   = $start->copy()->addDays(6);

        // Build daftar hari (untuk header tabel)
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i);
            $days[] = (object) [
                'date'      => $d,
                'iso'       => $d->toDateString(),
                'day_num'   => $d->format('j'),
                'day_short' => $d->translatedFormat('D'),
                'is_today'  => $d->isSameDay($today),
                'is_past'   => $d->lt($today),
                'is_future' => $d->gt($today),
            ];
        }

        // Submission today (firstOrCreate + bootstrap items) supaya bisa toggle.
        $submission = ChecklistSubmission::firstOrCreate(
            ['user_id' => session('id'), 'submission_date' => $today->toDateString()],
            [
                'id'             => (string) \Illuminate\Support\Str::uuid(),
                'division_id'    => $position->division_id,
                'raw_branch_id'  => $position->raw_branch_id,
                'status'         => ChecklistSubmission::STATUS_DRAFT,
                'total_tasks'    => 0,
                'done_tasks'     => 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]
        );
        if ($submission->items()->count() === 0) {
            $this->bootstrapItems($submission, $position->division_id, $position->raw_branch_id);
        } elseif ($submission->isEditable()) {
            $this->reconcileItems($submission, $position->division_id, $position->raw_branch_id);
        }

        // Ambil semua items dari submission user yang ada di week range.
        $itemsInWeek = ChecklistSubmissionItem::query()
            ->whereExists(function ($q) use ($start, $end) {
                $q->selectRaw(1)->from('sys_checklist_submission as s')
                  ->whereRaw('s.id = sys_checklist_submission_item.submission_id')
                  ->where('s.user_id', session('id'))
                  ->whereBetween('s.submission_date', [$start, $end]);
            })
            ->with('submission:id,submission_date')
            ->get();

        // Build lookup: [task_id][YYYY-MM-DD] => item
        // Tasks tanpa task_id (master dihapus) di-skip — task aktif harus ada
        // di katalog supaya muncul row-nya.
        $lookup = [];
        foreach ($itemsInWeek as $it) {
            if (! $it->task_id) continue;
            $key = $it->submission?->submission_date?->toDateString();
            if (! $key) continue;
            $lookup[$it->task_id][$key] = $it;
        }

        // Rows = current active katalog (kategori → tasks) untuk divisi user.
        // Hanya cadence=daily — kategori mingguan dipakai di form mingguan terpisah.
        $categories = ChecklistCategory::query()
            ->where('division_id', $position->division_id)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->where('cadence', ChecklistCategory::CADENCE_DAILY)
            ->where(function ($q) use ($position) {
                $q->whereNull('raw_branch_id')->orWhere('raw_branch_id', $position->raw_branch_id);
            })
            ->with(['tasks' => function ($q) {
                $q->whereNull('deleted_at')->where('is_active', 1)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        // Struktur rows untuk view: per kategori, array of tasks dengan state per day.
        // [{ category, tasks: [{ task, cells: [{day, item, state }] }] }]
        $rows = $categories->map(function ($cat) use ($days, $lookup, $submission) {
            $tasksRows = $cat->tasks->map(function ($task) use ($days, $lookup, $submission) {
                $cells = collect($days)->map(function ($day) use ($task, $lookup, $submission) {
                    $item = $lookup[$task->id][$day->iso] ?? null;
                    return (object) [
                        'day'        => $day,
                        'item'       => $item,
                        'is_done'    => $item ? (bool) $item->is_done : false,
                        'is_today'   => $day->is_today,
                        // Toggle hanya boleh kalau hari ini DAN submission editable
                        'editable'   => $day->is_today && $submission->isEditable() && $item !== null,
                    ];
                });
                return (object) [
                    'task'  => $task,
                    'cells' => $cells,
                ];
            });
            return (object) [
                'category' => $cat,
                'tasks'    => $tasksRows,
            ];
        });

        // Progress today (sama dengan index)
        $progress = [
            'done'  => $submission->done_tasks,
            'total' => $submission->total_tasks,
            'pct'   => $submission->progressPct(),
        ];

        return view('crud.checklist.my.weekly', compact(
            'position', 'submission', 'rows', 'days', 'weekOffset',
            'today', 'progress', 'start', 'end'
        ));
    }

    public function history(Request $request)
    {
        $type = $request->get('type', 'daily');
        if (! in_array($type, ['daily', 'weekly'], true)) $type = 'daily';

        $month = $request->get('month', Carbon::now()->format('Y-m'));
        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = Carbon::now()->startOfMonth();
            $month = $start->format('Y-m');
        }
        $end = $start->copy()->endOfMonth();

        $today      = Carbon::today();
        $prevMonth  = $start->copy()->subMonth()->format('Y-m');
        $nextMonth  = $start->copy()->addMonth()->format('Y-m');
        $monthLabel = $start->translatedFormat('F Y');

        if ($type === 'weekly') {
            $submissions = ChecklistWeeklySubmission::query()
                ->where('user_id', session('id'))
                ->whereBetween('week_start_date', [$start, $end])
                ->with(['items' => function ($q) {
                    $q->orderBy('day_of_week')->orderBy('category_sort_order')->orderBy('task_sort_order');
                }])
                ->orderByDesc('week_start_date')
                ->paginate($this->perPage)
                ->appends($request->query());

            $allMonth = ChecklistWeeklySubmission::query()
                ->where('user_id', session('id'))
                ->whereBetween('week_start_date', [$start, $end])
                ->get(['status', 'week_start_date', 'total_tasks', 'done_tasks']);

            $statusConst = ChecklistWeeklySubmission::class;
        } else {
            $submissions = ChecklistSubmission::query()
                ->where('user_id', session('id'))
                ->whereBetween('submission_date', [$start, $end])
                ->with(['items' => function ($q) {
                    $q->orderBy('category_sort_order')->orderBy('task_sort_order');
                }])
                ->orderByDesc('submission_date')
                ->paginate($this->perPage)
                ->appends($request->query());

            $allMonth = ChecklistSubmission::query()
                ->where('user_id', session('id'))
                ->whereBetween('submission_date', [$start, $end])
                ->get(['status', 'submission_date', 'total_tasks', 'done_tasks']);

            $statusConst = ChecklistSubmission::class;
        }

        $countByStatus = $allMonth->groupBy('status')->map->count();
        $totalSubs   = $allMonth->count();
        $sumDone     = (int) $allMonth->sum('done_tasks');
        $sumTotal    = (int) $allMonth->sum('total_tasks');
        $avgPct      = $sumTotal > 0 ? (int) round($sumDone / $sumTotal * 100) : 0;

        $stats = [
            'total'     => $totalSubs,
            'approved'  => (int) ($countByStatus[$statusConst::STATUS_APPROVED]  ?? 0),
            'pending'   => (int) ($countByStatus[$statusConst::STATUS_SUBMITTED] ?? 0),
            'rejected'  => (int) ($countByStatus[$statusConst::STATUS_REJECTED]  ?? 0),
            'draft'     => (int) ($countByStatus[$statusConst::STATUS_DRAFT]     ?? 0),
            'avg_pct'   => $avgPct,
        ];

        // Calendar — hanya untuk daily. Weekly skip karena tidak intuitif per-hari.
        $calendar = [];
        if ($type === 'daily') {
            $subsByDate = $allMonth->keyBy(fn($s) => $s->submission_date->toDateString());
            $leadBlanks = ($start->dayOfWeekIso ?? 1) - 1;
            for ($i = 0; $i < $leadBlanks; $i++) $calendar[] = null;
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $s = $subsByDate->get($d->toDateString());
                $calendar[] = (object) [
                    'date'     => $d->copy(),
                    'day'      => $d->day,
                    'status'   => $s?->status,
                    'pct'      => $s ? $s->progressPct() : null,
                    'is_today' => $d->isSameDay($today),
                    'is_future'=> $d->gt($today),
                ];
            }
        }

        return view('crud.checklist.my.history', compact(
            'type', 'submissions', 'month', 'monthLabel', 'prevMonth', 'nextMonth',
            'stats', 'calendar'
        ));
    }

    /* ─── Helpers ──────────────────────────────────────────────────────── */

    /**
     * Snapshot task aktif (div + branch optional) → submission_item.
     * Hanya dipanggil saat items kosong (submission baru).
     */
    private function bootstrapItems(ChecklistSubmission $sub, string $divisionId, string $branchId): void
    {
        $categories = ChecklistCategory::query()
            ->where('division_id', $divisionId)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->where('cadence', ChecklistCategory::CADENCE_DAILY)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('raw_branch_id')->orWhere('raw_branch_id', $branchId);
            })
            ->with(['tasks' => function ($q) {
                $q->whereNull('deleted_at')->where('is_active', 1)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        $rows = [];
        foreach ($categories as $cat) {
            foreach ($cat->tasks as $task) {
                $rows[] = [
                    'id'                      => (string) Str::uuid(),
                    'submission_id'           => $sub->id,
                    'task_id'                 => $task->id,
                    'category_title_snapshot' => $cat->name,
                    'task_title_snapshot'     => $task->title,
                    'category_sort_order'     => (int) $cat->sort_order,
                    'task_sort_order'         => (int) $task->sort_order,
                    'is_done'                 => 0,
                    'note'                    => null,
                    'done_at'                 => null,
                    'created_at'              => now(),
                ];
            }
        }
        if ($rows) DB::table('sys_checklist_submission_item')->insert($rows);

        $sub->update(['total_tasks' => count($rows), 'updated_at' => now()]);
    }

    /**
     * Add task baru ke draft yg sedang berjalan (task ditambahkan supervisor
     * setelah karyawan buka checklist). Skip task yg sudah ada item-nya.
     */
    private function reconcileItems(ChecklistSubmission $sub, string $divisionId, string $branchId): void
    {
        $existingTaskIds = $sub->items()->whereNotNull('task_id')->pluck('task_id')->toArray();

        $categories = ChecklistCategory::query()
            ->where('division_id', $divisionId)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->where('cadence', ChecklistCategory::CADENCE_DAILY)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('raw_branch_id')->orWhere('raw_branch_id', $branchId);
            })
            ->with(['tasks' => function ($q) {
                $q->whereNull('deleted_at')->where('is_active', 1)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        $newRows = [];
        foreach ($categories as $cat) {
            foreach ($cat->tasks as $task) {
                if (in_array($task->id, $existingTaskIds, true)) continue;
                $newRows[] = [
                    'id'                      => (string) Str::uuid(),
                    'submission_id'           => $sub->id,
                    'task_id'                 => $task->id,
                    'category_title_snapshot' => $cat->name,
                    'task_title_snapshot'     => $task->title,
                    'category_sort_order'     => (int) $cat->sort_order,
                    'task_sort_order'         => (int) $task->sort_order,
                    'is_done'                 => 0,
                    'note'                    => null,
                    'done_at'                 => null,
                    'created_at'              => now(),
                ];
            }
        }
        if ($newRows) {
            DB::table('sys_checklist_submission_item')->insert($newRows);
            $total = $sub->items()->count();
            $sub->update(['total_tasks' => $total, 'updated_at' => now()]);
        }
    }
}
