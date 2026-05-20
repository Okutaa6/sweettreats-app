<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\EpKonten;
use App\Models\EpKontenPillar;
use App\Models\EpCampaignFramework;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Breakdown Konten = ep_konten dengan flag is_breakdown=1.
 * Controller ini thin wrapper untuk view yang fokus ke konten khusus saja.
 * CRUD-nya share underlying model EpKonten.
 */
class EpBreakdownController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;

        $filterStatus = request('status');
        $filterSearch = request('search');

        $query = EpKonten::with('pillars', 'campaign')
            ->breakdown()
            ->orderByDesc('tanggal_upload')
            ->orderByDesc('created_at');

        if ($filterStatus) $query->where('status', $filterStatus);
        if ($filterSearch) $query->where('judul', 'like', "%{$filterSearch}%");

        // Summary count by status (untuk konten breakdown saja)
        $summary = EpKonten::breakdown()
            ->when($filterSearch, fn($q) => $q->where('judul', 'like', "%{$filterSearch}%"))
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $breakdowns = $query->paginate($perPage)->withQueryString();
        $statuses   = EpKonten::statusOptions();
        $statusColors = EpKonten::statusColors();

        return view('crud.ep-breakdown.list', compact(
            'breakdowns', 'statuses', 'statusColors', 'summary',
            'filterStatus', 'filterSearch'
        ));
    }

    public function create()
    {
        return view('crud.ep-breakdown.new', $this->formData());
    }

    public function store()
    {
        $data = $this->validateBreakdown();
        $newId = (string) Str::uuid();

        DB::transaction(function () use ($data, $newId) {
            EpKonten::create(array_merge($data['konten'], [
                'id'           => $newId,
                'is_breakdown' => 1, // FORCE breakdown flag
                'created_by'   => session('id'),
                'created_at'   => now(),
            ]));

            $this->syncPillars($newId, $data['pillar_ids']);
        });

        if (request()->expectsJson()) {
            return response()->json([
                'success'  => true,
                'id'       => $newId,
                'message'  => 'Breakdown "' . $data['konten']['judul'] . '" ditambahkan.',
                'edit_url' => route('epbreakdown.edit', $newId),
            ]);
        }

        return redirect()->route('epbreakdown')
            ->with('success', 'Breakdown "' . $data['konten']['judul'] . '" ditambahkan & otomatis muncul di kalender EP.');
    }

    public function edit($id)
    {
        $konten = EpKonten::with('pillars', 'campaign')->breakdown()->findOrFail($id);
        $selectedPillarIds = $konten->pillars->pluck('id')->toArray();

        return view('crud.ep-breakdown.edit', array_merge(
            $this->formData(),
            compact('konten', 'selectedPillarIds')
        ));
    }

    public function update($id)
    {
        $konten = EpKonten::breakdown()->findOrFail($id);
        $data   = $this->validateBreakdown();

        DB::transaction(function () use ($konten, $data) {
            $konten->update(array_merge($data['konten'], [
                'is_breakdown' => 1, // tetap breakdown
                'updated_by'   => session('id'),
                'updated_at'   => now(),
            ]));

            DB::table('ep_konten_pillar_pivot')->where('ep_konten_id', $konten->id)->delete();
            $this->syncPillars($konten->id, $data['pillar_ids']);
        });

        if (request()->expectsJson()) {
            $konten->refresh();
            $statusOpt = EpKonten::statusOptions()[$konten->status] ?? null;
            $statusColors = EpKonten::statusColors()[$konten->status] ?? null;

            return response()->json([
                'success'          => true,
                'message'          => 'Breakdown berhasil diubah.',
                'updated_at_human' => $konten->updated_at?->diffForHumans(),
                'status'           => $konten->status,
                'status_label'     => $statusOpt['label'] ?? $konten->status,
                'status_colors'    => $statusColors,
            ]);
        }

        return redirect()->route('epbreakdown.edit', $id)
            ->with('success', 'Breakdown berhasil diubah.');
    }

    public function destroy($id)
    {
        $konten = EpKonten::breakdown()->findOrFail($id);

        DB::transaction(function () use ($id) {
            DB::table('ep_konten_pillar_pivot')->where('ep_konten_id', $id)->delete();
            EpKonten::where('id', $id)->delete();
        });

        if (request()->expectsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Breakdown dihapus.',
                'redirect_url' => route('epbreakdown'),
            ]);
        }

        return redirect()->route('epbreakdown')->with('success', 'Breakdown dihapus dari kalender EP.');
    }

    private function formData(): array
    {
        return [
            'pillars'        => EpKontenPillar::active()->get(),
            'platforms'      => EpKonten::platformOptions(),
            'tipes'          => EpKonten::tipeKontenOptions(),
            'statuses'       => EpKonten::statusOptions(),
            'prios'          => EpKonten::prioritasOptions(),
            'campaigns'      => EpCampaignFramework::orderByDesc('tahun')->orderByDesc('bulan')->limit(100)
                                    ->get(['id', 'campaign_name', 'bulan', 'tahun']),
            'tipeColors'     => EpKonten::tipeKontenColors(),
            'statusColors'   => EpKonten::statusColors(),
            'platformColors' => EpKonten::platformColors(),
            'showBreakdownFields' => true,
        ];
    }

    private function validateBreakdown(): array
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
            'lokasi'                   => 'nullable|max:255',
            'acc_by'                   => 'nullable|max:255',
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
                'lokasi'                   => request('lokasi'),
                'acc_by'                   => request('acc_by'),
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
}
