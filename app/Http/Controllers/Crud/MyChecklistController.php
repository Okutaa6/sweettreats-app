<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\ChecklistAuditLog;
use App\Models\ChecklistCategory;
use App\Models\ChecklistDivision;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistSubmissionItem;
use App\Models\ChecklistTask;
use App\Models\ChecklistWeeklyDayExcuse;
use App\Models\ChecklistWeeklyDaySubmit;
use App\Models\ChecklistWeeklySubmission;
use App\Models\ChecklistWeeklySubmissionItem;
use App\Models\RawBranch;
use App\Models\UserPosition;
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

        // Posisi otomatis dari data user (role→divisi, cabang dari record user).
        // Tidak ada lagi alur "ajukan → konfirmasi": begitu admin set role & cabang,
        // posisi checklist langsung aktif. Self-heal untuk user lama yang belum punya row.
        if (! $position) {
            $me = Users::find(session('id'));
            $position = UserPosition::syncFromUser($me, session('id'));
        }

        if (! $position) {
            // Benar-benar belum bisa di-assign. Dua sub-state:
            //  - role belum ditautkan ke divisi checklist
            //  - cabang belum diset admin
            $roleDivision = checklistDivisionForGroup(session('group_id'));

            return view('crud.checklist.my.index', [
                'position'      => null,
                'roleDivision'  => $roleDivision,
                'userName'      => Users::find(session('id'))?->nama ?? 'Rekan',
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

        // Progress tampilan & gating berbasis "satisfied" (dicentang ATAU
        // dikecualikan) supaya ring atas + tombol submit menghormati item yang
        // ditandai tidak perlu diisi. done_tasks di DB tetap = benar-benar dikerjakan.
        $satisfied = $items->filter(fn ($it) => $it->is_done || $it->is_excepted)->count();
        $progress = [
            'done'  => $satisfied,
            'total' => $submission->total_tasks,
            'pct'   => $submission->total_tasks ? (int) round($satisfied / $submission->total_tasks * 100) : 0,
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
        $update = [
            'is_done' => $newState,
            'done_at' => $newState ? now() : null,
        ];
        // Mencentang item yang tadinya dikecualikan → batalkan pengecualiannya
        // (tak boleh dicentang sekaligus dikecualikan; centang menang).
        if ($newState && $item->is_excepted) {
            $update['is_excepted']      = 0;
            $update['exception_reason'] = null;
            $update['excepted_at']      = null;
        }
        $item->update($update);

        // Recount header (done_tasks = benar-benar dicentang).
        $done = $sub->items()->where('is_done', 1)->count();
        $sub->update(['done_tasks' => $done, 'updated_at' => now()]);

        // Progress ke UI berbasis "satisfied" (dicentang ATAU dikecualikan).
        $satisfied = $sub->satisfiedCount();

        return response()->json([
            'ok'          => true,
            'is_done'     => $newState,
            'is_excepted' => false,
            'progress' => [
                'done'  => $satisfied,
                'total' => $sub->total_tasks,
                'pct'   => $sub->total_tasks ? (int) round($satisfied / $sub->total_tasks * 100) : 0,
            ],
        ]);
    }

    /**
     * AJAX: tandai SATU item "Dikecualikan" (tidak berlaku hari ini) + alasan
     * wajib. Item tetap terhitung "satisfied" (100%) tanpa perlu dicentang, tapi
     * diberi flag supaya beda dari "belum dikerjakan". Mencentang membatalkan ini.
     */
    public function exceptItem(Request $request, string $id)
    {
        $item = ChecklistSubmissionItem::with('submission')->findOrFail($id);
        $sub  = $item->submission;

        if ($sub->user_id !== session('id')) abort(403);
        if (! $sub->isEditable()) {
            return response()->json(['ok' => false, 'error' => 'Checklist sudah submitted/approved — tidak bisa diubah.'], 422);
        }

        $request->merge(['reason' => trim((string) $request->input('reason'))]);
        $data = $request->validate(
            ['reason' => 'required|string|min:3|max:500'],
            [
                'reason.required' => 'Alasan wajib diisi.',
                'reason.min'      => 'Alasan terlalu pendek — jelaskan sedikit (min. 3 karakter).',
            ]
        );

        $item->update([
            'is_excepted'      => 1,
            'exception_reason' => $data['reason'],
            'excepted_at'      => now(),
            'is_done'          => 0,
            'done_at'          => null,
        ]);

        $done = $sub->items()->where('is_done', 1)->count();
        $sub->update(['done_tasks' => $done, 'updated_at' => now()]);

        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_EXCUSE,
            null, ['item_id' => $item->id, 'is_excepted' => 1],
            'Item "' . $item->task_title_snapshot . '" dikecualikan: ' . $data['reason']);

        $satisfied = $sub->satisfiedCount();

        return response()->json([
            'ok'          => true,
            'is_excepted' => true,
            'reason'      => $data['reason'],
            'progress' => [
                'done'  => $satisfied,
                'total' => $sub->total_tasks,
                'pct'   => $sub->total_tasks ? (int) round($satisfied / $sub->total_tasks * 100) : 0,
            ],
        ]);
    }

    /** AJAX: batalkan pengecualian satu item — kembali "belum dikerjakan". */
    public function unexceptItem(Request $request, string $id)
    {
        $item = ChecklistSubmissionItem::with('submission')->findOrFail($id);
        $sub  = $item->submission;

        if ($sub->user_id !== session('id')) abort(403);
        if (! $sub->isEditable()) {
            return response()->json(['ok' => false, 'error' => 'Checklist sudah submitted/approved — tidak bisa diubah.'], 422);
        }

        $item->update([
            'is_excepted'      => 0,
            'exception_reason' => null,
            'excepted_at'      => null,
        ]);

        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN,
            null, ['item_id' => $item->id, 'is_excepted' => 0],
            'Pengecualian item "' . $item->task_title_snapshot . '" dibatalkan.');

        $satisfied = $sub->satisfiedCount();

        return response()->json([
            'ok'          => true,
            'is_excepted' => false,
            'progress' => [
                'done'  => $satisfied,
                'total' => $sub->total_tasks,
                'pct'   => $sub->total_tasks ? (int) round($satisfied / $sub->total_tasks * 100) : 0,
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

        // Hitung ulang dari item — JANGAN percaya counter header (bisa drift kalau
        // item berubah di luar toggle). Gating 100% dinilai dari hasil re-count ini.
        $total     = $sub->items()->count();
        $done      = $sub->items()->where('is_done', 1)->count();
        $satisfied = $sub->satisfiedCount();   // dicentang ATAU dikecualikan
        if ($total === 0) {
            $sub->update(['total_tasks' => 0, 'done_tasks' => 0]);
            return back()->with('error', 'Belum ada tugas untuk hari ini — tidak perlu submit.');
        }
        if ($satisfied < $total) {
            $sub->update(['total_tasks' => $total, 'done_tasks' => $done]);
            return back()->with('error', 'Masih ada tugas yang belum dicentang atau dikecualikan — selesaikan/kecualikan semua (100%) sebelum submit.');
        }

        $before = ['status' => $sub->status];
        DB::transaction(function () use ($sub, $total, $done, $before) {
            $sub->update([
                'status'       => ChecklistSubmission::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'updated_at'   => now(),
                'total_tasks'  => $total,
                'done_tasks'   => $done,
            ]);
            ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_SUBMIT,
                $before, ['status' => $sub->status, 'done' => $done, 'total' => $total]);
        });

        // Notif ke ATASAN divisi (hierarki), di-scope ke cabang submission.
        try {
            $user = Users::find(session('id'));
            $name = $user?->nama ?? 'Karyawan';
            $body = $name . ' (' . $sub->division_id . ') sudah submit checklist hari ini.';
            foreach (checklistSupervisorRoleNamesForDivision($sub->division_id) as $roleName) {
                AdminNotification::notifyRole(
                    $roleName,
                    'checklist.submitted',
                    "Checklist baru dari {$name}",
                    $body,
                    route('checklist.review.show', $sub->id),
                    $sub->raw_branch_id
                );
            }
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

    /**
     * Tandai checklist HARI INI "tidak perlu diisi" (status excused) dengan
     * alasan. Self-declared, langsung berlaku — tidak dihitung "belum diisi"
     * di dashboard supervisor, tapi tetap tampil (beserta alasan) untuk
     * transparansi & bisa dibatalkan supervisor (revokeExcuse).
     */
    public function excuseToday(Request $request)
    {
        $sub = ChecklistSubmission::where('user_id', session('id'))
            ->whereDate('submission_date', Carbon::today())
            ->firstOrFail();

        if (! $sub->isEditable()) {
            return back()->with('error', 'Checklist hari ini sudah tidak bisa diubah.');
        }

        $request->merge(['excuse_reason' => trim((string) $request->input('excuse_reason'))]);
        $data = $request->validate(
            ['excuse_reason' => 'required|string|min:3|max:500'],
            [
                'excuse_reason.required' => 'Alasan wajib diisi.',
                'excuse_reason.min'      => 'Alasan terlalu pendek — jelaskan sedikit (min. 3 karakter).',
            ]
        );

        $before = ['status' => $sub->status];
        $sub->update([
            'status'        => ChecklistSubmission::STATUS_EXCUSED,
            'excuse_reason' => $data['excuse_reason'],
            'submitted_at'  => null,
            'updated_at'    => now(),
        ]);

        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_EXCUSE,
            $before, ['status' => $sub->status], $data['excuse_reason']);

        try {
            $user = Users::find(session('id'));
            $name = $user?->nama ?? 'Karyawan';
            $body = $name . ' (' . $sub->division_id . ') menandai checklist hari ini tidak perlu diisi. Alasan: ' . $data['excuse_reason'];
            foreach (checklistSupervisorRoleNamesForDivision($sub->division_id) as $roleName) {
                AdminNotification::notifyRole(
                    $roleName,
                    'checklist.excused',
                    "Checklist dikecualikan — {$name}",
                    $body,
                    route('checklist.review.show', $sub->id),
                    $sub->raw_branch_id
                );
            }
        } catch (\Throwable $e) {
            // jangan gagalkan aksi kalau notif error
        }

        return redirect()->route('mychecklist')->with('success', 'Checklist hari ini ditandai "Dikecualikan".');
    }

    /**
     * Batalkan pengecualian (excused → draft) oleh karyawan sendiri, hari ini.
     */
    public function unexcuseToday(Request $request)
    {
        $sub = ChecklistSubmission::where('user_id', session('id'))
            ->whereDate('submission_date', Carbon::today())
            ->firstOrFail();

        if ($sub->status !== ChecklistSubmission::STATUS_EXCUSED) {
            return back()->with('error', 'Checklist ini tidak dalam status Dikecualikan.');
        }

        $before = ['status' => $sub->status];
        $sub->update([
            'status'        => ChecklistSubmission::STATUS_DRAFT,
            'excuse_reason' => null,
            'updated_at'    => now(),
        ]);
        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN,
            $before, ['status' => $sub->status], 'Karyawan membatalkan pengecualian.');

        return redirect()->route('mychecklist')->with('success', 'Pengecualian dibatalkan — checklist kembali bisa diisi.');
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
                'excusedDays' => [],
                'dayReviews' => [],
                'progress'   => ['done' => 0, 'total' => 0, 'pct' => 0],
                'weekOffset' => 0,
                'weekStart'  => Carbon::today()->startOfWeek(Carbon::MONDAY),
                'weekEnd'    => Carbon::today()->endOfWeek(Carbon::SUNDAY),
                'rejectedNote' => null,
            ]);
        }

        // Dibatasi ±8 minggu supaya tidak ada akses tak berhingga (mis. ?week=999).
        $weekOffset = max(-8, min(8, (int) $request->get('week', 0)));
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

            // Terapkan pengecualian dari cuti/izin approved yang menutupi minggu ini
            // (menghormati cuti yang disetujui SEBELUM minggu ini dibuat). Idempoten;
            // gagal tak boleh menggagalkan render form.
            try {
                app(\App\Services\ChecklistExcuseService::class)->reconcileWeek($submission);
                $submission->refresh();
            } catch (\Throwable $e) {
                // biarkan — form tetap tampil; reconcile ulang lain kali
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

        // Group items by day_of_week saja — task mingguan tak lagi dikelompokkan
        // per kategori; cukup diurut per hari.
        $grouped = collect();
        if ($submission) {
            $items = $submission->items()
                ->orderBy('category_sort_order')
                ->orderBy('task_sort_order')
                ->get();
            $grouped = $items->groupBy('day_of_week');
        }

        // Peta hari yang dikecualikan (iso => ['reason'=>.., 'source'=>..]) untuk
        // minggu ini. source='system' = otomatis dari cuti/izin → tak bisa dibatalkan.
        $excusedDays = $submission
            ? $submission->dayExcuses->mapWithKeys(fn ($ex) => [
                (int) $ex->day_of_week => ['reason' => $ex->reason, 'source' => $ex->source ?? 'manual'],
            ])->all()
            : [];

        // Peta hari → row submit (status review, waktu kirim, catatan tolak) untuk
        // render per-hari: pending / approved / rejected.
        $dayReviews = $submission
            ? $submission->daySubmits->keyBy(fn ($ds) => (int) $ds->day_of_week)->all()
            : [];

        // Progress form berjalan berbasis "satisfied" (dicentang ATAU hari
        // dikecualikan) supaya ring atas & teks "belum dikerjakan" menghormati
        // hari yang di-skip. done_tasks di DB tetap = benar-benar dikerjakan.
        $satisfied = $submission?->satisfiedCount() ?? 0;
        $total     = $submission?->total_tasks ?? 0;
        $progress = [
            'done'  => $satisfied,
            'total' => $total,
            'pct'   => $total > 0 ? (int) round(($satisfied / $total) * 100) : 0,
        ];

        $rejectedNote = ($submission && $submission->status === ChecklistWeeklySubmission::STATUS_REJECTED)
            ? $submission->review_notes
            : null;

        return view('crud.checklist.my.weekform', compact(
            'position', 'submission', 'days', 'grouped', 'progress', 'excusedDays', 'dayReviews',
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
        if ($r = $this->weekDayLockGuard($sub, $item)) return $r;

        $newState = ! $item->is_done;

        // Foto bersifat OPSIONAL — hanya pengingat/bukti, tidak pernah memblokir
        // centang di divisi manapun. Ops boleh menandai selesai tanpa foto.

        $update = [
            'is_done' => $newState,
            'done_at' => $newState ? now() : null,
        ];
        // Mencentang item yang tadinya dikecualikan → batalkan pengecualiannya.
        if ($newState && $item->is_excepted) {
            $update['is_excepted']      = 0;
            $update['exception_reason'] = null;
            $update['excepted_at']      = null;
        }
        $item->update($update);

        $done = $sub->items()->where('is_done', 1)->count();
        $sub->update(['done_tasks' => $done, 'updated_at' => now()]);

        // Progress yang dikirim ke UI berbasis "satisfied" (dicentang ATAU hari
        // dikecualikan) supaya konsisten dengan render server-side.
        $satisfied = $sub->satisfiedCount();

        return response()->json([
            'ok'      => true,
            'is_done' => $newState,
            'progress' => [
                'done'  => $satisfied,
                'total' => $sub->total_tasks,
                'pct'   => $sub->total_tasks ? (int) round($satisfied / $sub->total_tasks * 100) : 0,
            ],
        ]);
    }

    public function weekUpdateNote(Request $request, string $id)
    {
        $item = ChecklistWeeklySubmissionItem::with('submission')->findOrFail($id);
        $sub  = $item->submission;
        if ($sub->user_id !== session('id')) abort(403);
        if (! $sub->isEditable()) return response()->json(['ok' => false], 422);
        if ($r = $this->weekDayLockGuard($sub, $item)) return $r;

        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $item->update(['note' => $data['note'] ?? null]);

        return response()->json(['ok' => true]);
    }

    /**
     * AJAX: tandai SATU item mingguan "Dikecualikan" + alasan wajib. Item tetap
     * terhitung satisfied (100%) tanpa dicentang, diberi flag. Mencentang membatalkan.
     */
    public function weekExceptItem(Request $request, string $id)
    {
        $item = ChecklistWeeklySubmissionItem::with('submission')->findOrFail($id);
        $sub  = $item->submission;

        if ($sub->user_id !== session('id')) abort(403);
        if (! $sub->isEditable()) {
            return response()->json(['ok' => false, 'error' => 'Form mingguan sudah submitted/approved.'], 422);
        }
        if ($r = $this->weekDayLockGuard($sub, $item)) return $r;

        $request->merge(['reason' => trim((string) $request->input('reason'))]);
        $data = $request->validate(
            ['reason' => 'required|string|min:3|max:500'],
            [
                'reason.required' => 'Alasan wajib diisi.',
                'reason.min'      => 'Alasan terlalu pendek — jelaskan sedikit (min. 3 karakter).',
            ]
        );

        $item->update([
            'is_excepted'      => 1,
            'exception_reason' => $data['reason'],
            'excepted_at'      => now(),
            'is_done'          => 0,
            'done_at'          => null,
        ]);

        $done = $sub->items()->where('is_done', 1)->count();
        $sub->update(['done_tasks' => $done, 'updated_at' => now()]);

        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_EXCUSE,
            null, ['item_id' => $item->id, 'is_excepted' => 1],
            'Item "' . $item->task_title_snapshot . '" dikecualikan: ' . $data['reason']);

        $satisfied = $sub->satisfiedCount();

        return response()->json([
            'ok'          => true,
            'is_excepted' => true,
            'reason'      => $data['reason'],
            'progress' => [
                'done'  => $satisfied,
                'total' => $sub->total_tasks,
                'pct'   => $sub->total_tasks ? (int) round($satisfied / $sub->total_tasks * 100) : 0,
            ],
        ]);
    }

    /** AJAX: batalkan pengecualian satu item mingguan. */
    public function weekUnexceptItem(Request $request, string $id)
    {
        $item = ChecklistWeeklySubmissionItem::with('submission')->findOrFail($id);
        $sub  = $item->submission;

        if ($sub->user_id !== session('id')) abort(403);
        if (! $sub->isEditable()) {
            return response()->json(['ok' => false, 'error' => 'Form mingguan sudah submitted/approved.'], 422);
        }
        if ($r = $this->weekDayLockGuard($sub, $item)) return $r;

        $item->update([
            'is_excepted'      => 0,
            'exception_reason' => null,
            'excepted_at'      => null,
        ]);

        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN,
            null, ['item_id' => $item->id, 'is_excepted' => 0],
            'Pengecualian item "' . $item->task_title_snapshot . '" dibatalkan.');

        $satisfied = $sub->satisfiedCount();

        return response()->json([
            'ok'          => true,
            'is_excepted' => false,
            'progress' => [
                'done'  => $satisfied,
                'total' => $sub->total_tasks,
                'pct'   => $sub->total_tasks ? (int) round($satisfied / $sub->total_tasks * 100) : 0,
            ],
        ]);
    }

    /**
     * Upload foto bukti untuk 1 item Form Mingguan (task requires_photo).
     * File disimpan ke public/uploads/checklist-photos/{YYYY}/{Wxx}/{uuid}.{ext},
     * dipartisi per minggu ISO supaya folder tidak membludak (pola sama dengan
     * attachment petty cash). Foto lama dihapus dulu saat foto ulang.
     */
    public function weekUploadPhoto(Request $request, string $id)
    {
        $item = ChecklistWeeklySubmissionItem::with('submission')->findOrFail($id);
        $sub  = $item->submission;

        if ($sub->user_id !== session('id')) abort(403);
        if (! $sub->isEditable()) {
            return response()->json(['ok' => false, 'error' => 'Form mingguan sudah submitted/approved.'], 422);
        }
        if ($r = $this->weekDayLockGuard($sub, $item)) return $r;
        if (! $item->requires_photo || $item->photo_before_after) {
            return response()->json(['ok' => false, 'error' => 'Task ini tidak memakai foto tunggal.'], 422);
        }

        $request->validate(
            ['photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:8192'],
            [
                'photo.required' => 'Foto wajib diunggah.',
                'photo.image'    => 'File harus berupa gambar.',
                'photo.mimes'    => 'Format foto harus JPG, PNG, atau WEBP.',
                'photo.max'      => 'Ukuran foto maksimal 8 MB.',
            ]
        );

        // Hapus foto lama (foto ulang) supaya tidak menumpuk.
        $this->deletePhotoFile($item->photo_path);

        // Simpan file + cap tanggal/jam/nama/cabang ke gambar.
        $path = $this->storeChecklistPhoto($request->file('photo'), $sub);

        $item->update(['photo_path' => $path]);

        return response()->json([
            'ok'   => true,
            'url'  => asset($path),
            'path' => $path,
        ]);
    }

    /**
     * Hapus foto item (mau foto ulang / salah ambil). Kalau task wajib foto dan
     * sudah tercentang, centang otomatis dibatalkan — tanpa foto tak boleh selesai.
     */
    public function weekDeletePhoto(Request $request, string $id)
    {
        $item = ChecklistWeeklySubmissionItem::with('submission')->findOrFail($id);
        $sub  = $item->submission;

        if ($sub->user_id !== session('id')) abort(403);
        if (! $sub->isEditable()) {
            return response()->json(['ok' => false, 'error' => 'Form mingguan sudah submitted/approved.'], 422);
        }
        if ($r = $this->weekDayLockGuard($sub, $item)) return $r;

        $this->deletePhotoFile($item->photo_path);

        // Foto opsional → menghapus foto tidak membatalkan centang.
        $item->update(['photo_path' => null]);

        $done = $sub->items()->where('is_done', 1)->count();
        $sub->update(['done_tasks' => $done, 'updated_at' => now()]);
        $satisfied = $sub->satisfiedCount();

        return response()->json([
            'ok'       => true,
            'progress' => [
                'done'  => $satisfied,
                'total' => $sub->total_tasks,
                'pct'   => $sub->total_tasks ? (int) round($satisfied / $sub->total_tasks * 100) : 0,
            ],
        ]);
    }

    /**
     * Upload foto before/after OPSIONAL untuk 1 item Form Mingguan.
     * Hanya untuk item ber-mode before/after (task->photo_before_after, di setup
     * hanya ditawarkan untuk GS). Slot: 'before' | 'after'. Tidak memblokir centang
     * dan tidak auto-menandai selesai — murni dokumentasi.
     */
    public function weekUploadBaPhoto(Request $request, string $id, string $slot)
    {
        $item = ChecklistWeeklySubmissionItem::with('submission')->findOrFail($id);
        $sub  = $item->submission;

        if ($sub->user_id !== session('id')) abort(403);
        if (! in_array($slot, ['before', 'after'], true)) {
            return response()->json(['ok' => false, 'error' => 'Slot foto tidak valid.'], 422);
        }
        if (! $item->photo_before_after) {
            return response()->json(['ok' => false, 'error' => 'Task ini tidak memakai foto before/after.'], 422);
        }
        if (! $sub->isEditable()) {
            return response()->json(['ok' => false, 'error' => 'Form mingguan sudah submitted/approved.'], 422);
        }

        $request->validate(
            ['photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:8192'],
            [
                'photo.required' => 'Foto wajib diunggah.',
                'photo.image'    => 'File harus berupa gambar.',
                'photo.mimes'    => 'Format foto harus JPG, PNG, atau WEBP.',
                'photo.max'      => 'Ukuran foto maksimal 8 MB.',
            ]
        );

        if ($r = $this->weekDayLockGuard($sub, $item)) return $r;

        $col = "photo_{$slot}_path";

        // Hapus foto lama slot ini (foto ulang) supaya tidak menumpuk.
        $this->deletePhotoFile($item->{$col});

        // Simpan file + cap tanggal/jam/nama/cabang ke gambar.
        $path = $this->storeChecklistPhoto($request->file('photo'), $sub);

        $item->update([$col => $path]);

        return response()->json([
            'ok'   => true,
            'url'  => asset($path),
            'path' => $path,
            'slot' => $slot,
        ]);
    }

    /** Hapus foto before/after slot tertentu (before|after). */
    public function weekDeleteBaPhoto(Request $request, string $id, string $slot)
    {
        $item = ChecklistWeeklySubmissionItem::with('submission')->findOrFail($id);
        $sub  = $item->submission;

        if ($sub->user_id !== session('id')) abort(403);
        if (! in_array($slot, ['before', 'after'], true)) {
            return response()->json(['ok' => false, 'error' => 'Slot foto tidak valid.'], 422);
        }
        if (! $sub->isEditable()) {
            return response()->json(['ok' => false, 'error' => 'Form mingguan sudah submitted/approved.'], 422);
        }
        if ($r = $this->weekDayLockGuard($sub, $item)) return $r;

        $col = "photo_{$slot}_path";
        $this->deletePhotoFile($item->{$col});
        $item->update([$col => null]);

        return response()->json(['ok' => true, 'slot' => $slot]);
    }

    /** Hapus file foto dari disk kalau ada (path relatif dari public/). */
    private function deletePhotoFile(?string $relPath): void
    {
        if (! $relPath) return;
        $abs = public_path($relPath);
        if (is_file($abs)) @unlink($abs);
    }

    /**
     * Pindahkan file upload ke folder mingguan + cap tanggal/jam/nama/cabang,
     * kembalikan path relatif dari public/. Dipakai foto tunggal & before/after.
     */
    private function storeChecklistPhoto($file, ChecklistWeeklySubmission $sub): string
    {
        $week   = optional($sub->week_start_date)->isoFormat('GGGG-[W]WW') ?: date('Y-\WW');
        [$year, $wk] = array_pad(explode('-', $week), 2, 'W00');
        $relDir = "uploads/checklist-photos/{$year}/{$wk}";
        $absDir = public_path($relDir);
        if (! is_dir($absDir)) @mkdir($absDir, 0775, true);

        $ext      = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = (string) Str::uuid() . '.' . $ext;
        $file->move($absDir, $filename);
        $abs = $absDir . DIRECTORY_SEPARATOR . $filename;

        $this->stampPhoto($abs, $sub);

        return "{$relDir}/{$filename}";
    }

    /**
     * Bakar keterangan ke foto (server-side, tak bisa dimanipulasi jam HP):
     * baris 1 = tanggal + jam server (WIB), baris 2 = nama karyawan — cabang.
     * Kalau gagal (mis. GD tanpa FreeType), foto asli tetap tersimpan.
     */
    private function stampPhoto(string $absPath, ChecklistWeeklySubmission $sub): void
    {
        try {
            $user   = Users::find(session('id'));
            $nama   = trim((string) ($user?->nama ?? 'Karyawan')) ?: 'Karyawan';
            $cabang = trim((string) ($sub->branch?->name ?? $sub->raw_branch_id ?? ''));
            $waktu  = now()->translatedFormat('d/m/Y H:i') . ' WIB';
            $line2  = $cabang !== '' ? ($nama . ' — ' . $cabang) : $nama;

            $font = public_path('assets/fonts/stamp-sans.ttf');

            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $img = $manager->read($absPath);
            $img->orient(); // luruskan rotasi EXIF dari kamera HP dulu.

            $w   = $img->width();
            $h   = $img->height();
            // Font besar & proporsional lebar foto biar tetap kebaca saat thumbnail.
            $fs  = max(24, (int) round($w / 26));
            $pad = (int) round($fs * 0.55);
            $lh  = (int) round($fs * 1.32);
            $barH = $pad * 2 + $lh * 2;

            // Bar gelap semi-transparan di bawah supaya teks putih selalu terbaca.
            $img->drawRectangle(0, $h - $barH, function ($r) use ($w, $barH) {
                $r->size($w, $barH);
                $r->background('rgba(0, 0, 0, 0.62)');
            });

            $tx = $pad;
            $ty = $h - $barH + $pad;
            foreach ([$waktu, $line2] as $i => $line) {
                // Stroke hitam tipis → tetap kontras walau kena latar terang/putih.
                $img->text($line, $tx, $ty + $i * $lh, function ($f) use ($font, $fs) {
                    $f->filename($font);
                    $f->size($fs);
                    $f->color('#ffffff');
                    $f->align('left');
                    $f->valign('top');
                    $f->stroke('#000000', 2);
                });
            }

            $img->save($absPath, quality: 85);
        } catch (\Throwable $e) {
            \Log::warning('stampPhoto gagal: ' . $e->getMessage());
        }
    }

    /**
     * 422 kalau hari dari item sudah "dikirim" (submit per-hari) — hari terkunci
     * sampai karyawan membatalkan kirim. null artinya boleh diubah.
     */
    private function weekDayLockGuard(ChecklistWeeklySubmission $sub, ChecklistWeeklySubmissionItem $item)
    {
        if ($sub->isDaySubmitted((int) $item->day_of_week)) {
            return response()->json([
                'ok'    => false,
                'error' => 'Hari ini sudah dikirim — batalkan kirim dulu untuk mengubah.',
            ], 422);
        }
        // Hari yang dikecualikan OTOMATIS dari cuti/izin (source=system) terkunci —
        // karyawan tak boleh mencentang untuk menimpanya.
        $sysExcused = $sub->dayExcuses()
            ->where('day_of_week', (int) $item->day_of_week)
            ->where('source', 'system')->exists();
        if ($sysExcused) {
            return response()->json([
                'ok'    => false,
                'error' => 'Hari ini dikecualikan otomatis (cuti/izin) — tidak bisa diubah.',
            ], 422);
        }
        return null;
    }

    /**
     * Kirim tugas SATU hari pada minggu berjalan. Setelah dikirim, hari itu
     * terkunci (tak bisa centang/foto lagi) sampai dibatalkan. Begitu semua hari
     * ber-tugas sudah dikirim/dikecualikan, header otomatis di-finalize ke
     * 'submitted' dan masuk antrian review supervisor (review tetap per-minggu).
     */
    public function weekDaySubmit(Request $request)
    {
        $data = $request->validate([
            'day_of_week' => 'required|integer|between:2,7',
            'note'        => 'nullable|string|max:1000',
        ]);

        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $sub = ChecklistWeeklySubmission::where('user_id', session('id'))
            ->whereDate('week_start_date', $weekStart)
            ->firstOrFail();

        if (! $sub->isEditable()) {
            return back()->with('error', 'Form Mingguan sudah tidak bisa diubah.');
        }

        $iso      = (int) $data['day_of_week'];
        $dayLabel = ChecklistTask::dayLabels()[$iso] ?? ('Hari ' . $iso);

        if ($sub->isDayExcused($iso)) {
            return back()->with('error', $dayLabel . ' sudah ditandai tidak perlu diisi — tak perlu dikirim.');
        }
        if ($sub->isDaySubmitted($iso)) {
            return back()->with('error', $dayLabel . ' sudah dikirim.');
        }

        // Gate per-hari: semua item hari itu harus beres (dicentang / dikecualikan).
        $dayItems = $sub->items()->where('day_of_week', $iso)->get();
        if ($dayItems->count() === 0) {
            return back()->with('error', 'Tidak ada tugas di ' . $dayLabel . ' untuk dikirim.');
        }
        $unresolved = $dayItems->filter(fn ($i) => ! ($i->is_done || $i->is_excepted))->count();
        if ($unresolved > 0) {
            return back()->with('error', 'Selesaikan semua tugas ' . $dayLabel . ' dulu sebelum kirim.');
        }

        DB::transaction(function () use ($sub, $iso, $data, $dayLabel) {
            // Kirim (atau kirim ulang hari yang tadinya ditolak) → review_status pending,
            // bersihkan jejak review lama supaya masuk antrian lagi.
            ChecklistWeeklyDaySubmit::updateOrCreate(
                ['submission_id' => $sub->id, 'day_of_week' => $iso],
                [
                    'note'             => $data['note'] ?? null,
                    'review_status'    => ChecklistWeeklyDaySubmit::REVIEW_PENDING,
                    'reviewer_user_id' => null,
                    'review_notes'     => null,
                    'reviewed_at'      => null,
                    'submitted_at'     => now(),
                    'updated_at'       => now(),
                    'created_at'       => now(),
                ]
            );
            ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_SUBMIT,
                null, ['day_of_week' => $iso], $dayLabel . ' dikirim.');
        });

        // Turunkan status header dari state per-hari (draft/submitted/dst).
        $sub->load(['items', 'dayExcuses', 'daySubmits']);
        $sub->recomputeStatus();

        // Notif supervisor TIAP hari dikirim — biar bisa langsung review harian.
        $this->notifyWeeklyDaySubmitted($sub, $dayLabel);

        return redirect()->route('mychecklist.weekform')
            ->with('success', $dayLabel . ' sudah dikirim.');
    }

    /**
     * Batalkan kirim satu hari (untuk koreksi) — selama header masih editable.
     * Hari itu kembali terbuka untuk diubah.
     */
    public function weekDayUnsubmit(Request $request)
    {
        $data = $request->validate(['day_of_week' => 'required|integer|between:2,7']);

        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $sub = ChecklistWeeklySubmission::where('user_id', session('id'))
            ->whereDate('week_start_date', $weekStart)
            ->firstOrFail();

        if (! $sub->isEditable()) {
            return back()->with('error', 'Form Mingguan sudah tidak bisa diubah.');
        }

        $iso      = (int) $data['day_of_week'];
        $dayLabel = ChecklistTask::dayLabels()[$iso] ?? ('Hari ' . $iso);

        // Hanya hari yang MASIH pending yang boleh dibatalkan — kalau sudah
        // di-approve/tolak supervisor, karyawan tak boleh mencabut sepihak.
        $status = $sub->dayReviewStatus($iso);
        if ($status === null) {
            return back()->with('error', $dayLabel . ' belum dikirim.');
        }
        if ($status !== ChecklistWeeklyDaySubmit::REVIEW_PENDING) {
            return back()->with('error', $dayLabel . ' sudah direview supervisor — tak bisa dibatalkan.');
        }

        ChecklistWeeklyDaySubmit::where('submission_id', $sub->id)
            ->where('day_of_week', $iso)
            ->delete();

        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN,
            null, ['day_of_week' => $iso], 'Kiriman ' . $dayLabel . ' dibatalkan.');

        $sub->load(['items', 'dayExcuses', 'daySubmits']);
        $sub->recomputeStatus();

        return redirect()->route('mychecklist.weekform')
            ->with('success', 'Kiriman ' . $dayLabel . ' dibatalkan — hari ini terbuka lagi.');
    }

    /**
     * Notif ke ATASAN divisi (hierarki) tiap kali karyawan mengirim satu hari,
     * di-scope ke cabang submission. Link ke halaman review supervisor.
     */
    private function notifyWeeklyDaySubmitted(ChecklistWeeklySubmission $sub, string $dayLabel): void
    {
        try {
            $user = Users::find(session('id'));
            $name = $user?->nama ?? 'Karyawan';
            $body = $name . ' (' . $sub->division_id . ') mengirim tugas ' . $dayLabel . ' — ' . $sub->weekLabel() . '.';
            foreach (checklistSupervisorRoleNamesForDivision($sub->division_id) as $roleName) {
                AdminNotification::notifyRole(
                    $roleName,
                    'checklist.weekly.day_submitted',
                    "Tugas {$dayLabel} dari {$name}",
                    $body,
                    route('checklist.review.weekly.show', $sub->id),
                    $sub->raw_branch_id
                );
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Tandai SATU hari pada minggu ini "tidak perlu diisi" + alasan (wajib).
     * Task di hari itu otomatis terhitung "satisfied" tanpa perlu dicentang.
     */
    public function dayExcuse(Request $request)
    {
        $request->merge(['excuse_reason' => trim((string) $request->input('excuse_reason'))]);
        $data = $request->validate(
            [
                'day_of_week'   => 'required|integer|between:2,7',
                'excuse_reason' => 'required|string|min:3|max:500',
            ],
            [
                'excuse_reason.required' => 'Alasan wajib diisi.',
                'excuse_reason.min'      => 'Alasan terlalu pendek — jelaskan sedikit (min. 3 karakter).',
            ]
        );

        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $sub = ChecklistWeeklySubmission::where('user_id', session('id'))
            ->whereDate('week_start_date', $weekStart)
            ->firstOrFail();

        if (! $sub->isEditable()) {
            return back()->with('error', 'Form Mingguan sudah tidak bisa diubah.');
        }

        $iso = (int) $data['day_of_week'];

        ChecklistWeeklyDayExcuse::updateOrCreate(
            ['submission_id' => $sub->id, 'day_of_week' => $iso],
            ['reason' => $data['excuse_reason'], 'updated_at' => now(), 'created_at' => now()]
        );
        $sub->update(['updated_at' => now()]);

        $dayLabel = ChecklistTask::dayLabels()[$iso] ?? ('Hari ' . $iso);
        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_EXCUSE,
            null, ['day_of_week' => $iso], $dayLabel . ': ' . $data['excuse_reason']);

        try {
            $user = Users::find(session('id'));
            $name = $user?->nama ?? 'Karyawan';
            $body = $name . ' (' . $sub->division_id . ') menandai ' . $dayLabel . ' (' . $sub->weekLabel() . ') tidak perlu diisi. Alasan: ' . $data['excuse_reason'];
            foreach (checklistSupervisorRoleNamesForDivision($sub->division_id) as $roleName) {
                AdminNotification::notifyRole(
                    $roleName,
                    'checklist.weekly.day_excused',
                    "Hari dikecualikan — {$name}",
                    $body,
                    route('checklist.review.weekly.show', $sub->id),
                    $sub->raw_branch_id
                );
            }
        } catch (\Throwable $e) {
            // jangan gagalkan aksi kalau notif error
        }

        // Mengecualikan hari mengubah komposisi hari aktif → hitung ulang status header.
        $sub->load(['items', 'dayExcuses', 'daySubmits']);
        $sub->recomputeStatus();

        return redirect()->route('mychecklist.weekform')->with('success', $dayLabel . ' ditandai "tidak perlu diisi".');
    }

    /**
     * Batalkan pengecualian satu hari — hari itu kembali wajib diisi.
     */
    public function dayUnexcuse(Request $request)
    {
        $data = $request->validate([
            'day_of_week' => 'required|integer|between:2,7',
        ]);

        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $sub = ChecklistWeeklySubmission::where('user_id', session('id'))
            ->whereDate('week_start_date', $weekStart)
            ->firstOrFail();

        if (! $sub->isEditable()) {
            return back()->with('error', 'Form Mingguan sudah tidak bisa diubah.');
        }

        $iso = (int) $data['day_of_week'];

        // Pengecualian otomatis dari cuti/izin (source=system) TIDAK boleh
        // dibatalkan karyawan — hanya batal saat pengajuannya ditolak/dicabut.
        $excuse = ChecklistWeeklyDayExcuse::where('submission_id', $sub->id)
            ->where('day_of_week', $iso)->first();
        if ($excuse && ($excuse->source ?? 'manual') === 'system') {
            return back()->with('error', 'Pengecualian ini otomatis dari cuti/izin yang disetujui — tidak bisa dibatalkan manual.');
        }

        // Defense-in-depth: hanya hapus pengecualian manual.
        ChecklistWeeklyDayExcuse::where('submission_id', $sub->id)
            ->where('day_of_week', $iso)
            ->where('source', 'manual')
            ->delete();
        $sub->update(['updated_at' => now()]);

        $dayLabel = ChecklistTask::dayLabels()[$iso] ?? ('Hari ' . $iso);
        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN,
            null, ['day_of_week' => $iso], 'Karyawan membatalkan pengecualian ' . $dayLabel . '.');

        $sub->load(['items', 'dayExcuses', 'daySubmits']);
        $sub->recomputeStatus();

        return redirect()->route('mychecklist.weekform')->with('success', 'Pengecualian ' . $dayLabel . ' dibatalkan — hari itu kembali wajib diisi.');
    }

    /**
     * Snapshot task aktif kategori weekly → weekly_submission_item.
     * Hanya task yang punya day_of_week valid (1-7) yang di-include.
     */
    private function bootstrapWeeklyItems(ChecklistWeeklySubmission $sub, string $divisionId, string $branchId): void
    {
        // Logika katalog dipindah ke model supaya ChecklistExcuseService (auto-excuse
        // cuti/izin) memakai sumber yang sama.
        $sub->bootstrapItems($divisionId, $branchId);
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

        // Peta task master (id → task) untuk sinkronisasi item lama + deteksi baru.
        $taskMap = [];
        foreach ($categories as $cat) {
            foreach ($cat->tasks as $task) $taskMap[$task->id] = ['task' => $task, 'cat' => $cat];
        }

        $newRows = [];
        foreach ($taskMap as $taskId => $pair) {
            if (in_array($taskId, $existingTaskIds, true)) continue;
            $task = $pair['task'];
            $cat  = $pair['cat'];
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
                'requires_photo'          => (int) $task->requires_photo,
                'photo_before_after'      => (int) $task->photo_before_after,
                'photo_path'              => null,
                'done_at'                 => null,
                'created_at'              => now(),
            ];
        }
        if ($newRows) {
            DB::table('sys_checklist_weekly_submission_item')->insert($newRows);
        }

        // Sinkronkan item draft yang sudah ada dengan master task-nya — supaya
        // perubahan supervisor (aktifkan "wajib foto", ganti judul, pindah hari)
        // langsung berlaku di minggu berjalan yang belum disubmit.
        foreach ($sub->items()->whereNotNull('task_id')->get() as $item) {
            $pair = $taskMap[$item->task_id] ?? null;
            if (! $pair) continue;
            $task = $pair['task'];
            $changes = [];
            if ((int) $item->requires_photo !== (int) $task->requires_photo) $changes['requires_photo'] = (int) $task->requires_photo;
            if ((int) $item->photo_before_after !== (int) $task->photo_before_after) $changes['photo_before_after'] = (int) $task->photo_before_after;
            if ($item->task_title_snapshot !== $task->title)                 $changes['task_title_snapshot'] = $task->title;
            if ((int) $item->day_of_week !== (int) $task->day_of_week)        $changes['day_of_week'] = (int) $task->day_of_week;
            if ((int) $item->task_sort_order !== (int) $task->sort_order)     $changes['task_sort_order'] = (int) $task->sort_order;
            if ($changes) {
                DB::table('sys_checklist_weekly_submission_item')->where('id', $item->id)->update($changes);
            }
        }

        // Buang item yang task-nya sudah dinonaktifkan/dihapus/pindah ke luar
        // Sel–Min dari katalog aktif — supaya tidak menghalangi 100%. Hanya draft
        // yang sampai sini. Dilewati kalau katalog mingguan aktif kosong, biar tak
        // sengaja mengosongkan draft. Foto ikut dibersihkan agar tak jadi orphan.
        $staleRemoved = false;
        if (! empty($taskMap)) {
            $staleItems = $sub->items()->whereNotNull('task_id')
                ->whereNotIn('task_id', array_keys($taskMap))->get();
            foreach ($staleItems as $it) {
                $this->deletePhotoFile($it->photo_path);
                $this->deletePhotoFile($it->photo_before_path);
                $this->deletePhotoFile($it->photo_after_path);
            }
            if ($staleItems->isNotEmpty()) {
                DB::table('sys_checklist_weekly_submission_item')
                    ->whereIn('id', $staleItems->pluck('id')->all())->delete();
                $staleRemoved = true;
            }
        }

        if ($newRows || $staleRemoved) {
            $total = $sub->items()->count();
            $done  = $sub->items()->where('is_done', 1)->count();
            $sub->update(['total_tasks' => $total, 'done_tasks' => $done, 'updated_at' => now()]);
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

        // Dibatasi ±8 minggu supaya tidak ada akses tak berhingga (mis. ?week=999).
        $weekOffset = max(-8, min(8, (int) $request->get('week', 0)));
        $today = Carbon::today();

        // Minggu mulai SELASA → 6 hari (Sel, Rab, Kam, Jum, Sab, Min). Senin
        // tidak ditampilkan karena klinik tutup Senin — konsisten dengan Form
        // Mingguan. Klik next/prev geser 7 hari sekali.
        $start = $today->copy()->startOfWeek(Carbon::TUESDAY)->addWeeks($weekOffset);
        $end   = $start->copy()->addDays(5);

        // Build daftar hari (untuk header tabel)
        $days = [];
        for ($i = 0; $i < 6; $i++) {
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

        // Status & subtotal tiap hari (untuk dot status di header + baris "Terisi"
        // di kaki grid). Ambil SEMUA submission harian user di rentang minggu ini.
        $subsByDate = ChecklistSubmission::query()
            ->where('user_id', session('id'))
            ->whereBetween('submission_date', [$start, $end])
            ->get(['submission_date', 'status', 'done_tasks', 'total_tasks'])
            ->keyBy(fn ($s) => $s->submission_date->toDateString());

        foreach ($days as $day) {
            $s = $subsByDate->get($day->iso);
            $day->status     = $s?->status;
            $day->is_excused = $s && $s->status === ChecklistSubmission::STATUS_EXCUSED;
            $day->sub_done   = $s ? (int) $s->done_tasks : null;
            $day->sub_total  = $s ? (int) $s->total_tasks : null;
            $day->sub_pct    = $s ? $s->progressPct() : null;
        }

        // Agregat 6 hari — metrik UTAMA "Rekap": hanya hari yang sudah lewat/hari
        // ini dan TIDAK dikecualikan yang dihitung (konsisten dgn baris "Terisi").
        $weekDone = 0; $weekTotal = 0;
        foreach ($days as $day) {
            if ($day->is_future || $day->is_excused) continue;
            $weekTotal += (int) ($day->sub_total ?? 0);
            $weekDone  += (int) ($day->sub_done ?? 0);
        }
        $weekProgress = [
            'done'  => $weekDone,
            'total' => $weekTotal,
            'pct'   => $weekTotal > 0 ? (int) round($weekDone / $weekTotal * 100) : 0,
        ];

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
            'today', 'progress', 'weekProgress', 'start', 'end'
        ));
    }

    public function history(Request $request)
    {
        $type = $request->get('type', 'daily');
        if (! in_array($type, ['daily', 'weekly'], true)) $type = 'daily';

        // Filter status (opsional) — hanya mempersempit DAFTAR, bukan statistik/kalender
        // (biar ringkasan bulan tetap utuh & bisa jadi tombol filter).
        $status = $request->get('status');
        if (! in_array($status, ['draft', 'submitted', 'approved', 'rejected', 'excused', 'missed'], true)) {
            $status = null;
        }

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
                ->when($status, fn ($q) => $q->where('status', $status))
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
                ->when($status, fn ($q) => $q->where('status', $status))
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
            'stats', 'calendar', 'status'
        ));
    }

    /**
     * Detail satu pengisian lampau (halaman penuh) — dibuka dari kartu Riwayat.
     * Hanya submission milik user sendiri yang bisa dilihat.
     */
    public function historyShow(Request $request, string $type, string $id)
    {
        if (! in_array($type, ['daily', 'weekly'], true)) $type = 'daily';

        if ($type === 'weekly') {
            $submission = ChecklistWeeklySubmission::query()
                ->where('user_id', session('id'))->where('id', $id)
                ->with(['items' => function ($q) {
                    $q->orderBy('day_of_week')->orderBy('category_sort_order')->orderBy('task_sort_order');
                }, 'division', 'branch'])
                ->firstOrFail();

            $grouped = $submission->items
                ->sortBy(fn($i) => sprintf('%d-%06d-%06d', $i->day_of_week ?? 0, $i->category_sort_order ?? 0, $i->task_sort_order ?? 0))
                ->groupBy('day_of_week');
        } else {
            $submission = ChecklistSubmission::query()
                ->where('user_id', session('id'))->where('id', $id)
                ->with(['items' => function ($q) {
                    $q->orderBy('category_sort_order')->orderBy('task_sort_order');
                }, 'division', 'branch'])
                ->firstOrFail();

            $grouped = $submission->items->groupBy('category_title_snapshot');
        }

        $month = $request->get('month');   // untuk tombol kembali (jaga konteks bulan)

        return view('crud.checklist.my.history-show', compact('submission', 'type', 'grouped', 'month'));
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

        $activeTaskIds = [];
        $newRows = [];
        foreach ($categories as $cat) {
            foreach ($cat->tasks as $task) {
                $activeTaskIds[] = $task->id;
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

        // Buang item yang task-nya sudah dinonaktifkan/dihapus dari katalog aktif,
        // supaya tidak lagi menghalangi 100%. Hanya submission editable yang sampai
        // sini (yang sudah disubmit/approve tak pernah di-reconcile → snapshot aman).
        // Dilewati kalau katalog aktif kosong, biar tak sengaja mengosongkan draft.
        $staleIds = [];
        if ($activeTaskIds) {
            $staleIds = $sub->items()->whereNotNull('task_id')
                ->whereNotIn('task_id', $activeTaskIds)->pluck('id')->toArray();
        }

        $changed = false;
        if ($newRows)  { DB::table('sys_checklist_submission_item')->insert($newRows); $changed = true; }
        if ($staleIds) { DB::table('sys_checklist_submission_item')->whereIn('id', $staleIds)->delete(); $changed = true; }
        if ($changed) {
            $total = $sub->items()->count();
            $done  = $sub->items()->where('is_done', 1)->count();
            $sub->update(['total_tasks' => $total, 'done_tasks' => $done, 'updated_at' => now()]);
        }
    }
}
