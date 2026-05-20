<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\EpKonten;
use App\Models\EpKontenPillar;
use App\Models\EpCampaignFramework;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EpKontenController extends Controller
{
    public function index()
    {
        // Calendar view (default — replace list view per user request).
        // Tab platform = filter utama. Filter lain (status/pillar/tipe) di sidebar.
        $month = (int) request('month', now()->month);
        $year  = (int) request('year', now()->year);
        $platform     = request('platform', 'all'); // all | ig_post | ig_story | tiktok
        $filterStatus = request('status');
        $filterPillar = request('pillar_id');
        $filterTipe   = request('tipe_konten');
        $filterKind   = request('kind', 'all'); // all | regular | khusus
        $filterSearch = request('search');

        // Validate month/year sanity
        if ($month < 1 || $month > 12) $month = now()->month;
        if ($year < 2000 || $year > 2100) $year = now()->year;

        // Build calendar grid range (Sunday..Saturday — 5/6 weeks covering month)
        $monthStart = \Carbon\Carbon::create($year, $month, 1);
        $monthEnd   = $monthStart->copy()->endOfMonth();
        $gridStart  = $monthStart->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
        $gridEnd    = $monthEnd->copy()->endOfWeek(\Carbon\Carbon::SATURDAY);

        // Query konten dalam range
        $query = EpKonten::with('pillars')
            ->whereDate('tanggal_upload', '>=', $gridStart->toDateString())
            ->whereDate('tanggal_upload', '<=', $gridEnd->toDateString());

        if ($platform !== 'all') {
            if ($platform === 'ig_post') {
                $query->where(function ($q) {
                    $q->whereRaw("JSON_CONTAINS(platforms, JSON_QUOTE('ig_post'))")
                      ->orWhereRaw("JSON_CONTAINS(platforms, JSON_QUOTE('ig_feeds'))")
                      ->orWhereRaw("JSON_CONTAINS(platforms, JSON_QUOTE('ig_reels'))");
                });
            } else {
                $query->whereRaw("JSON_CONTAINS(platforms, JSON_QUOTE(?))", [$platform]);
            }
        }
        if ($filterStatus) $query->where('status', $filterStatus);
        if ($filterTipe)   $query->where('tipe_konten', $filterTipe);
        if ($filterSearch) $query->where('judul', 'like', "%{$filterSearch}%");
        if ($filterPillar) {
            $query->whereHas('pillars', fn($p) => $p->where('ep_konten_pillar_id', $filterPillar));
        }
        if ($filterKind === 'regular') $query->where('is_breakdown', 0);
        if ($filterKind === 'khusus')  $query->where('is_breakdown', 1);

        $kontens = $query->orderBy('tanggal_upload')->orderBy('created_at')->get();

        // Group by date (YYYY-MM-DD)
        $byDate = $kontens->groupBy(fn($k) => $k->tanggal_upload?->format('Y-m-d'));

        // Build week rows: array of weeks → each week = 7 day cells
        $weeks  = [];
        $cursor = $gridStart->copy();
        while ($cursor <= $gridEnd) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $cursor->format('Y-m-d');
                $week[] = (object) [
                    'date'      => $cursor->copy(),
                    'in_month'  => $cursor->month === $month,
                    'is_today'  => $cursor->isSameDay(now()),
                    'is_weekend'=> $cursor->isWeekend(),
                    'kontens'   => $byDate[$key] ?? collect(),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        // Total konten in current month (for summary, only in_month days)
        $monthKontens = $kontens->filter(fn($k) => $k->tanggal_upload?->month === $month);
        $monthTotal   = $monthKontens->count();

        return view('crud.ep-konten.calendar', [
            'weeks'         => $weeks,
            'month'         => $month,
            'year'          => $year,
            'monthStart'    => $monthStart,
            'monthLabel'    => $monthStart->translatedFormat('F Y'),
            'platform'      => $platform,
            'filterStatus'  => $filterStatus,
            'filterPillar'  => $filterPillar,
            'filterTipe'    => $filterTipe,
            'filterKind'    => $filterKind,
            'filterSearch'  => $filterSearch,
            'monthTotal'    => $monthTotal,
            'pillars'       => EpKontenPillar::active()->get(),
            'platforms'     => EpKonten::platformOptions(),
            'tipes'         => EpKonten::tipeKontenOptions(),
            'statuses'      => EpKonten::statusOptions(),
            'prios'         => EpKonten::prioritasOptions(),
            'tipeColors'    => EpKonten::tipeKontenColors(),
            'statusColors'  => EpKonten::statusColors(),
            'platformColors'=> EpKonten::platformColors(),
        ]);
    }

    public function create()
    {
        return view('crud.ep-konten.new', $this->formData());
    }

    public function store()
    {
        $data = $this->validateKonten();
        $newId = (string) Str::uuid();

        DB::transaction(function () use ($data, $newId) {
            EpKonten::create(array_merge($data['konten'], [
                'id'         => $newId,
                'created_by' => session('id'),
                'created_at' => now(),
            ]));

            $this->syncPillars($newId, $data['pillar_ids']);
        });

        // AJAX → return JSON, frontend yang handle redirect/notif
        if (request()->expectsJson()) {
            return response()->json([
                'success'  => true,
                'id'       => $newId,
                'message'  => 'Konten "' . $data['konten']['judul'] . '" ditambahkan.',
                'edit_url' => route('epkonten.edit', $newId),
            ]);
        }

        return redirect()->route('epkonten')->with('success', 'Konten "' . $data['konten']['judul'] . '" ditambahkan.');
    }

    public function edit($id)
    {
        $konten = EpKonten::with('pillars', 'campaign')->findOrFail($id);
        $selectedPillarIds = $konten->pillars->pluck('id')->toArray();

        return view('crud.ep-konten.edit', array_merge($this->formData(), compact('konten', 'selectedPillarIds')));
    }

    public function update($id)
    {
        $konten = EpKonten::findOrFail($id);
        $data   = $this->validateKonten();

        DB::transaction(function () use ($konten, $data) {
            $konten->update(array_merge($data['konten'], [
                'updated_by' => session('id'),
                'updated_at' => now(),
            ]));

            DB::table('ep_konten_pillar_pivot')->where('ep_konten_id', $konten->id)->delete();
            $this->syncPillars($konten->id, $data['pillar_ids']);
        });

        // AJAX → return JSON with fields yang dipakai frontend untuk update DOM
        if (request()->expectsJson()) {
            $konten->refresh();
            $statusOpt = EpKonten::statusOptions()[$konten->status] ?? null;
            $statusColors = EpKonten::statusColors()[$konten->status] ?? null;

            return response()->json([
                'success'    => true,
                'message'    => 'Konten berhasil diubah.',
                'updated_at' => $konten->updated_at?->translatedFormat('d M Y H:i'),
                'updated_at_human' => $konten->updated_at?->diffForHumans(),
                'status'     => $konten->status,
                'status_label' => $statusOpt['label'] ?? $konten->status,
                'status_colors' => $statusColors,
            ]);
        }

        return redirect()->route('epkonten.edit', $id)->with('success', 'Konten berhasil diubah.');
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            DB::table('ep_konten_pillar_pivot')->where('ep_konten_id', $id)->delete();
            EpKonten::where('id', $id)->delete();
        });

        if (request()->expectsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Konten dihapus.',
                'redirect_url' => route('epkonten'),
            ]);
        }

        return redirect()->route('epkonten')->with('success', 'Konten dihapus.');
    }

    /**
     * AJAX quick-add dari calendar cell. Minimal fields, return JSON.
     */
    public function quickStore()
    {
        request()->validate([
            'judul'          => 'required|max:255',
            'tanggal_upload' => 'required|date',
            'platforms'      => 'nullable|array',
            'platforms.*'    => 'in:' . implode(',', array_keys(EpKonten::platformOptions())),
            'tipe_konten'    => 'nullable|in:' . implode(',', array_keys(EpKonten::tipeKontenOptions())),
            'status'         => 'required|in:' . implode(',', array_keys(EpKonten::statusOptions())),
            'prioritas'      => 'required|in:' . implode(',', array_keys(EpKonten::prioritasOptions())),
            'pillar_ids'     => 'nullable|array',
            'pillar_ids.*'   => 'exists:ep_konten_pillar,id',
        ]);

        $platforms = array_filter((array) request('platforms', []));
        $pillarIds = array_filter((array) request('pillar_ids', []));

        $id = (string) Str::uuid();

        DB::transaction(function () use ($id, $platforms, $pillarIds) {
            EpKonten::create([
                'id'             => $id,
                'judul'          => request('judul'),
                'tanggal_upload' => request('tanggal_upload'),
                'platforms'      => empty($platforms) ? null : array_values($platforms),
                'tipe_konten'    => request('tipe_konten') ?: null,
                'status'         => request('status'),
                'prioritas'      => request('prioritas', 'normal'),
                'created_by'     => session('id'),
                'created_at'     => now(),
            ]);

            $this->syncPillars($id, $pillarIds);
        });

        return response()->json([
            'success' => true,
            'id'      => $id,
            'message' => 'Konten ditambahkan untuk ' . request('tanggal_upload'),
        ]);
    }

    /**
     * AJAX inline status edit dari list.
     */
    public function updateStatus($id)
    {
        request()->validate([
            'status' => 'required|in:' . implode(',', array_keys(EpKonten::statusOptions())),
        ]);

        $konten = EpKonten::findOrFail($id);
        $konten->update([
            'status'     => request('status'),
            'updated_by' => session('id'),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => $konten->status,
            'label'  => EpKonten::statusOptions()[$konten->status]['label'] ?? $konten->status,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    private function formData(): array
    {
        return [
            'pillars'    => EpKontenPillar::active()->get(),
            'platforms'  => EpKonten::platformOptions(),
            'tipes'      => EpKonten::tipeKontenOptions(),
            'statuses'   => EpKonten::statusOptions(),
            'prios'      => EpKonten::prioritasOptions(),
            'campaigns'  => EpCampaignFramework::orderByDesc('tahun')->orderByDesc('bulan')->limit(100)
                                ->get(['id', 'campaign_name', 'bulan', 'tahun']),
            // Note: tipe colors etc auto-passed via _form.blade.php fallback to model helpers
        ];
    }

    private function validateKonten(): array
    {
        request()->validate([
            'judul'                    => 'required|max:255',
            'deskripsi'                => 'nullable|string|max:5000',
            'platforms'                => 'nullable|array',
            'platforms.*'              => 'in:' . implode(',', array_keys(EpKonten::platformOptions())),
            'tipe_konten'              => 'nullable|in:' . implode(',', array_keys(EpKonten::tipeKontenOptions())),
            'status'                   => 'required|in:' . implode(',', array_keys(EpKonten::statusOptions())),
            'prioritas'                => 'required|in:' . implode(',', array_keys(EpKonten::prioritasOptions())),
            'tanggal_upload'           => 'nullable|date',
            'waktu_pengerjaan'         => 'nullable|max:100',
            'script'                   => 'nullable|string',
            'caption'                  => 'nullable|string',
            'referensi'                => 'nullable|string',
            'framework'                => 'nullable|max:255',
            'visual_plan'              => 'nullable|string',
            'note'                     => 'nullable|string',
            'ep_campaign_framework_id' => 'nullable|exists:ep_campaign_framework,id',
            'pillar_ids'               => 'nullable|array',
            'pillar_ids.*'             => 'exists:ep_konten_pillar,id',
        ]);

        $platforms = array_filter((array) request('platforms', []));

        return [
            'konten' => [
                'judul'                    => request('judul'),
                'deskripsi'                => request('deskripsi'),
                'platforms'                => empty($platforms) ? null : array_values($platforms),
                'tipe_konten'              => request('tipe_konten') ?: null,
                'status'                   => request('status'),
                'prioritas'                => request('prioritas', 'normal'),
                'tanggal_upload'           => request('tanggal_upload') ?: null,
                'waktu_pengerjaan'         => request('waktu_pengerjaan'),
                'script'                   => request('script'),
                'caption'                  => request('caption'),
                'referensi'                => request('referensi'),
                'framework'                => request('framework'),
                'visual_plan'              => request('visual_plan'),
                'note'                     => request('note'),
                'ep_campaign_framework_id' => request('ep_campaign_framework_id') ?: null,
            ],
            'pillar_ids' => array_filter((array) request('pillar_ids', [])),
        ];
    }

    private function syncPillars(string $kontenId, array $pillarIds): void
    {
        if (empty($pillarIds)) return;

        $rows = array_map(fn($pid) => [
            'id'                  => (string) Str::uuid(),
            'ep_konten_id'        => $kontenId,
            'ep_konten_pillar_id' => $pid,
            'created_at'          => now(),
        ], $pillarIds);

        DB::table('ep_konten_pillar_pivot')->insert($rows);
    }

    private function resolvePeriodeShortcut(?string $key): ?array
    {
        if (!$key) return null;
        $now = now();
        return match ($key) {
            'today'      => [$now->copy()->startOfDay(),       $now->copy()->endOfDay()],
            'this_week'  => [$now->copy()->startOfWeek(),      $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(),     $now->copy()->endOfMonth()],
            'next_month' => [$now->copy()->addMonth()->startOfMonth(), $now->copy()->addMonth()->endOfMonth()],
            default      => null,
        };
    }
}
