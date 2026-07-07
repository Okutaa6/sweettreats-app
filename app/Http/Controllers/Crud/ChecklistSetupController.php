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

    /* ─── Halaman utama ──────────────────────────────────────────────── */

    public function index(Request $request)
    {
        $this->guard();

        $divisions = ChecklistDivision::query()
            ->where('is_active', 1)
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

        return view('crud.checklist.setup.index', compact(
            'divisions', 'division', 'categories',
            'dailyCategories', 'weeklyCategories',
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
            'requires_note' => 'nullable|boolean',
            'requires_photo'=> 'nullable|boolean',
        ]);

        $cat = ChecklistCategory::whereNull('deleted_at')->findOrFail($data['category_id']);

        // Untuk kategori mingguan, hari wajib. Untuk harian, paksa NULL.
        // Wajib-foto hanya berlaku untuk kategori mingguan.
        if ($cat->isWeekly()) {
            if (empty($data['day_of_week'])) {
                return back()->withErrors(['day_of_week' => 'Untuk kategori Mingguan, hari wajib dipilih.'])->withInput();
            }
            $dayOfWeek = (int) $data['day_of_week'];
            $requiresPhoto = (int) ($data['requires_photo'] ?? 0);
        } else {
            $dayOfWeek = null;
            $requiresPhoto = 0;
        }

        $nextOrder = (int) (ChecklistTask::where('category_id', $cat->id)
            ->whereNull('deleted_at')->max('sort_order') ?? 0) + 10;

        $task = ChecklistTask::create([
            'id'            => (string) Str::uuid(),
            'category_id'   => $cat->id,
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'day_of_week'   => $dayOfWeek,
            'requires_note' => (int) ($data['requires_note'] ?? 0),
            'requires_photo'=> $requiresPhoto,
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

    public function updateTask(Request $request, string $id)
    {
        $this->guard();
        $task = ChecklistTask::whereNull('deleted_at')->with('category')->findOrFail($id);

        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'day_of_week'   => 'nullable|integer|between:1,7',
            'requires_note' => 'nullable|boolean',
            'requires_photo'=> 'nullable|boolean',
            'is_active'     => 'nullable|boolean',
        ]);

        $cat = $task->category;
        if ($cat && $cat->isWeekly()) {
            if (empty($data['day_of_week'])) {
                return back()->withErrors(['day_of_week' => 'Untuk kategori Mingguan, hari wajib dipilih.'])->withInput();
            }
            $dayOfWeek = (int) $data['day_of_week'];
            $requiresPhoto = (int) ($data['requires_photo'] ?? 0);
        } else {
            $dayOfWeek = null;
            $requiresPhoto = 0;
        }

        $before = ['title' => $task->title, 'is_active' => $task->is_active, 'day_of_week' => $task->day_of_week];
        $task->update([
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'day_of_week'   => $dayOfWeek,
            'requires_note' => (int) ($data['requires_note'] ?? 0),
            'requires_photo'=> $requiresPhoto,
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
        $task = ChecklistTask::whereNull('deleted_at')->with('category')->findOrFail($id);
        $divId = $task->category->division_id;

        $task->delete(); // soft delete
        ChecklistAuditLog::write('task', $task->id, ChecklistAuditLog::ACTION_DELETE,
            ['title' => $task->title], null);

        return redirect()->route('checklist.setup', ['division' => $divId])
            ->with('success', 'Task dihapus.');
    }

    public function reorder(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'type'    => 'required|in:category,task',
            'items'   => 'required|array',
            'items.*.id'         => 'required|string',
            'items.*.sort_order' => 'required|integer',
        ]);

        $model = $data['type'] === 'category' ? ChecklistCategory::class : ChecklistTask::class;
        foreach ($data['items'] as $row) {
            $model::where('id', $row['id'])->update(['sort_order' => (int) $row['sort_order']]);
        }

        return response()->json(['ok' => true]);
    }
}
