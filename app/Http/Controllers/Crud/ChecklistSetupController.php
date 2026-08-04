<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\ChecklistAuditLog;
use App\Models\ChecklistCategory;
use App\Models\ChecklistDivision;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistTask;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CRUD katalog checklist (kategori + task) per divisi. Hanya bisa diakses
 * oleh isChecklistSupervisor().
 *
 * Soft delete (deleted_at) untuk kategori & task — supaya snapshot di
 * submission lama tetap utuh. Hard delete tidak disediakan (kalau task
 * tidak relevan lagi → cukup is_active=0).
 */
class ChecklistSetupController extends Controller
{
    private function guard(): void
    {
        if (! isChecklistSupervisor()) abort(403, 'Tidak punya akses untuk mengelola tugas checklist.');
    }

    /** Cegah kelola divisi di luar cakupan supervisi (hierarki role) user ini. */
    private function abortIfDivisionOutOfScope(?string $divisionId): void
    {
        if (! in_array($divisionId, checklistVisibleDivisionIds(), true)) {
            abort(403, 'Divisi ini di luar cakupan supervisi Anda.');
        }
    }

    /* ─── Halaman utama ──────────────────────────────────────────────── */

    public function index(Request $request)
    {
        $this->guard();

        // Hanya divisi yang boleh disupervisi user ini (dari hierarki role) yang
        // tampil sbg tab — konsisten dgn Dashboard & Kelola Posisi.
        $divisions = ChecklistDivision::query()
            ->where('is_active', 1)
            ->whereIn('id', checklistVisibleDivisionIds())
            ->orderBy('sort_order')
            ->get();

        $activeDiv = $request->get('division', $divisions->first()->id ?? null);
        $division  = $divisions->firstWhere('id', $activeDiv) ?? $divisions->first();

        $categories = collect();
        $dailyCategories  = collect();
        $weeklyCategories = collect();
        $totalTasks = 0;
        $dailyTaskCount  = 0;
        $weeklyTaskCount = 0;
        if ($division) {
            $categories = ChecklistCategory::query()
                ->where('division_id', $division->id)
                ->whereNull('deleted_at')
                ->with(['tasks' => function ($q) {
                    $q->whereNull('deleted_at')->orderBy('sort_order');
                }])
                ->orderBy('sort_order')
                ->get();

            $dailyCategories  = $categories->where('cadence', ChecklistCategory::CADENCE_DAILY)->values();
            $weeklyCategories = $categories->where('cadence', ChecklistCategory::CADENCE_WEEKLY)->values();

            $totalTasks      = $categories->sum(fn($c) => $c->tasks->where('is_active', 1)->count());
            $dailyTaskCount  = $dailyCategories->sum(fn($c) => $c->tasks->where('is_active', 1)->count());
            $weeklyTaskCount = $weeklyCategories->sum(fn($c) => $c->tasks->where('is_active', 1)->count());
        }

        // Mingguan tidak lagi pakai kategori di UI — task langsung dijadwalkan per
        // hari. Ratakan semua task mingguan (lintas kategori lama) lalu group per hari.
        $weeklyTasksByDay = $weeklyCategories
            ->flatMap(fn($c) => $c->tasks)
            ->sortBy('sort_order')
            ->groupBy('day_of_week');

        return view('crud.checklist.setup.index', compact(
            'divisions', 'division', 'categories',
            'dailyCategories', 'weeklyCategories', 'weeklyTasksByDay',
            'totalTasks', 'dailyTaskCount', 'weeklyTaskCount'
        ));
    }

    /* ─── Category CRUD ──────────────────────────────────────────────── */

    public function storeCategory(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'division_id' => 'required|string|exists:sys_checklist_division,id',
            'name'        => 'required|string|max:128',
            'description' => 'nullable|string|max:255',
            'cadence'     => 'nullable|in:daily,weekly',
        ]);
        $this->abortIfDivisionOutOfScope($data['division_id']);

        $nextOrder = (int) (ChecklistCategory::where('division_id', $data['division_id'])
            ->whereNull('deleted_at')->max('sort_order') ?? 0) + 10;

        $cat = ChecklistCategory::create([
            'id'          => (string) Str::uuid(),
            'division_id' => $data['division_id'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'cadence'     => $data['cadence'] ?? ChecklistCategory::CADENCE_DAILY,
            'sort_order'  => $nextOrder,
            'is_active'   => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        ChecklistAuditLog::write('category', $cat->id, ChecklistAuditLog::ACTION_CREATE,
            null, ['division_id' => $cat->division_id, 'name' => $cat->name, 'cadence' => $cat->cadence]);

        return redirect()->route('checklist.setup', ['division' => $cat->division_id])
            ->with('success', 'Kategori "' . $cat->name . '" ditambahkan.');
    }

    public function updateCategory(Request $request, string $id)
    {
        $this->guard();
        $cat = ChecklistCategory::whereNull('deleted_at')->findOrFail($id);
        $this->abortIfDivisionOutOfScope($cat->division_id);

        $data = $request->validate([
            'name'        => 'required|string|max:128',
            'description' => 'nullable|string|max:255',
            'cadence'     => 'nullable|in:daily,weekly',
            'is_active'   => 'nullable|boolean',
        ]);

        $before = ['name' => $cat->name, 'is_active' => $cat->is_active, 'cadence' => $cat->cadence];
        $cat->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'cadence'     => $data['cadence'] ?? $cat->cadence,
            'is_active'   => isset($data['is_active']) ? (int) $data['is_active'] : $cat->is_active,
            'updated_at'  => now(),
        ]);
        $after = ['name' => $cat->name, 'is_active' => $cat->is_active, 'cadence' => $cat->cadence];

        ChecklistAuditLog::write('category', $cat->id, ChecklistAuditLog::ACTION_UPDATE, $before, $after);

        return redirect()->route('checklist.setup', ['division' => $cat->division_id])
            ->with('success', 'Kategori diupdate.');
    }

    public function destroyCategory(string $id)
    {
        $this->guard();
        $cat = ChecklistCategory::whereNull('deleted_at')->findOrFail($id);
        $this->abortIfDivisionOutOfScope($cat->division_id);

        // Cek apakah kategori ini punya task yang muncul di submission < 30 hari.
        $hasRecentSubmission = ChecklistSubmission::query()
            ->where('division_id', $cat->division_id)
            ->whereDate('submission_date', '>=', Carbon::today()->subDays(30))
            ->whereExists(function ($q) use ($cat) {
                $q->selectRaw(1)->from('sys_checklist_submission_item as i')
                  ->whereRaw('i.submission_id = sys_checklist_submission.id')
                  ->where('i.category_title_snapshot', $cat->name);
            })
            ->exists();

        if ($hasRecentSubmission) {
            return back()->with('error', 'Kategori ini sudah dipakai di submission 30 hari terakhir. Nonaktifkan saja (jangan hapus).');
        }

        // Ikut soft-delete task anaknya supaya tidak ada task "yatim" (kategori
        // hilang tapi task-nya masih ada → bisa memicu error saat hapus task).
        $cat->tasks()->whereNull('deleted_at')->update(['deleted_at' => now()]);

        $cat->delete(); // soft delete
        ChecklistAuditLog::write('category', $cat->id, ChecklistAuditLog::ACTION_DELETE,
            ['name' => $cat->name], null);

        return redirect()->route('checklist.setup', ['division' => $cat->division_id])
            ->with('success', 'Kategori dihapus.');
    }

    /* ─── Task CRUD ──────────────────────────────────────────────────── */

    public function storeTask(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'category_id'   => 'required|string|exists:sys_checklist_category,id',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'day_of_week'   => 'nullable|integer|between:1,7',
            'requires_photo'=> 'nullable|boolean',
            'photo_before_after' => 'nullable|boolean',
        ]);

        $cat = ChecklistCategory::whereNull('deleted_at')->findOrFail($data['category_id']);
        $this->abortIfDivisionOutOfScope($cat->division_id);

        // Untuk kategori mingguan, hari wajib. Untuk harian, paksa NULL.
        // Foto hanya berlaku untuk kategori mingguan. Mode before/after khusus GS,
        // dan mengimplikasikan requires_photo=1.
        if ($cat->isWeekly()) {
            $dayOfWeek = (int) ($data['day_of_week'] ?? 0);
            if (! in_array($dayOfWeek, [2, 3, 4, 5, 6, 7], true)) {
                return back()->withErrors(['day_of_week' => 'Hari tugas mingguan harus Selasa–Minggu (Senin klinik tutup).'])->withInput();
            }
            [$requiresPhoto, $beforeAfter] = $this->normalizePhotoMode($cat->division_id, $data);
        } else {
            $dayOfWeek = null;
            $requiresPhoto = 0;
            $beforeAfter = 0;
        }

        $nextOrder = (int) (ChecklistTask::where('category_id', $cat->id)
            ->whereNull('deleted_at')->max('sort_order') ?? 0) + 10;

        $task = ChecklistTask::create([
            'id'            => (string) Str::uuid(),
            'category_id'   => $cat->id,
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'day_of_week'   => $dayOfWeek,
            'requires_photo'=> $requiresPhoto,
            'photo_before_after' => $beforeAfter,
            'sort_order'    => $nextOrder,
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        ChecklistAuditLog::write('task', $task->id, ChecklistAuditLog::ACTION_CREATE,
            null, ['category_id' => $cat->id, 'title' => $task->title, 'day_of_week' => $dayOfWeek]);

        return redirect()->route('checklist.setup', ['division' => $cat->division_id])
            ->with('success', 'Task "' . $task->title . '" ditambahkan.');
    }

    /**
     * Normalisasi mode foto dari input form. Mengembalikan [requires_photo, photo_before_after].
     * - Mode before/after hanya ditawarkan untuk divisi GS.
     * - before/after=1 mengimplikasikan requires_photo=1.
     */
    private function normalizePhotoMode(?string $divisionId, array $data): array
    {
        $requiresPhoto = (int) ($data['requires_photo'] ?? 0);
        $beforeAfter   = ($divisionId === 'GS') ? (int) ($data['photo_before_after'] ?? 0) : 0;
        if ($beforeAfter) $requiresPhoto = 1;
        return [$requiresPhoto, $beforeAfter];
    }

    /**
     * Tambah task Mingguan langsung (tanpa pilih kategori). Task nempel ke
     * "kategori payung" mingguan divisi — dibuat otomatis kalau belum ada.
     */
    public function storeWeeklyTask(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'division_id'   => 'required|string|exists:sys_checklist_division,id',
            'title'         => 'required|string|max:255',
            'day_of_week'   => 'required|integer|between:2,7',
            'description'   => 'nullable|string|max:1000',
            'requires_photo'=> 'nullable|boolean',
            'photo_before_after' => 'nullable|boolean',
        ]);
        $this->abortIfDivisionOutOfScope($data['division_id']);

        $cat = ChecklistCategory::weeklyUmbrellaFor($data['division_id']);

        $nextOrder = (int) (ChecklistTask::where('category_id', $cat->id)
            ->whereNull('deleted_at')->max('sort_order') ?? 0) + 10;

        [$requiresPhoto, $beforeAfter] = $this->normalizePhotoMode($data['division_id'], $data);

        $task = ChecklistTask::create([
            'id'            => (string) Str::uuid(),
            'category_id'   => $cat->id,
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'day_of_week'   => (int) $data['day_of_week'],
            'requires_photo'=> $requiresPhoto,
            'photo_before_after' => $beforeAfter,
            'sort_order'    => $nextOrder,
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        ChecklistAuditLog::write('task', $task->id, ChecklistAuditLog::ACTION_CREATE,
            null, ['category_id' => $cat->id, 'title' => $task->title, 'day_of_week' => (int) $data['day_of_week']]);

        return redirect()->route('checklist.setup', ['division' => $data['division_id']])
            ->with('success', 'Task "' . $task->title . '" ditambahkan.');
    }

    public function updateTask(Request $request, string $id)
    {
        $this->guard();
        $task = ChecklistTask::whereNull('deleted_at')->with('category')->findOrFail($id);
        $this->abortIfDivisionOutOfScope($task->category?->division_id);

        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'day_of_week'   => 'nullable|integer|between:1,7',
            'requires_photo'=> 'nullable|boolean',
            'photo_before_after' => 'nullable|boolean',
            'is_active'     => 'nullable|boolean',
        ]);

        $cat = $task->category;
        if ($cat && $cat->isWeekly()) {
            $dayOfWeek = (int) ($data['day_of_week'] ?? 0);
            if (! in_array($dayOfWeek, [2, 3, 4, 5, 6, 7], true)) {
                return back()->withErrors(['day_of_week' => 'Hari tugas mingguan harus Selasa–Minggu (Senin klinik tutup).'])->withInput();
            }
            [$requiresPhoto, $beforeAfter] = $this->normalizePhotoMode($cat->division_id, $data);
        } else {
            $dayOfWeek = null;
            $requiresPhoto = 0;
            $beforeAfter = 0;
        }

        $before = ['title' => $task->title, 'is_active' => $task->is_active, 'day_of_week' => $task->day_of_week];
        $task->update([
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'day_of_week'   => $dayOfWeek,
            'requires_photo'=> $requiresPhoto,
            'photo_before_after' => $beforeAfter,
            'is_active'     => isset($data['is_active']) ? (int) $data['is_active'] : $task->is_active,
            'updated_at'    => now(),
        ]);
        $after = ['title' => $task->title, 'is_active' => $task->is_active, 'day_of_week' => $task->day_of_week];

        ChecklistAuditLog::write('task', $task->id, ChecklistAuditLog::ACTION_UPDATE, $before, $after);

        return redirect()->route('checklist.setup', ['division' => $cat->division_id])
            ->with('success', 'Task diupdate.');
    }

    public function destroyTask(string $id)
    {
        $this->guard();
        $task = ChecklistTask::whereNull('deleted_at')
            ->with(['category' => fn($q) => $q->withTrashed()])
            ->findOrFail($id);
        $divId = $task->category?->division_id;
        $this->abortIfDivisionOutOfScope($divId);

        $task->delete(); // soft delete
        ChecklistAuditLog::write('task', $task->id, ChecklistAuditLog::ACTION_DELETE,
            ['title' => $task->title], null);

        return redirect()->route('checklist.setup', ['division' => $divId])
            ->with('success', 'Task dihapus.');
    }

    /**
     * Simpan teks Info / pengumuman (running-text) global untuk my-checklist.
     * Disimpan di sys_setting key 'checklist_info_text'.
     */
    public function updateInfo(Request $request)
    {
        $this->guard();
        $data = $request->validate(['info_text' => 'nullable|string|max:500']);
        \App\Models\Setting::put('checklist_info_text', trim((string) ($data['info_text'] ?? '')));

        return back()->with('success', 'Teks Info diperbarui.');
    }
}
