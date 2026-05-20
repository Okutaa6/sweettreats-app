<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\EpCampaignFramework;
use App\Models\EpCampaignWeek;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EpCampaignController extends Controller
{
    public function index()
    {
        $filterTahun  = (int) request('tahun', now()->year);
        $filterStatus = request('status');
        $filterSearch = request('search');

        $query = EpCampaignFramework::with('weeks')->withCount('kontens')
            ->where('tahun', $filterTahun)
            ->orderBy('bulan');

        if ($filterStatus) $query->where('status', $filterStatus);
        if ($filterSearch) $query->where('campaign_name', 'like', "%{$filterSearch}%");

        $campaigns = $query->get()->groupBy('bulan');

        // Years available (untuk navigation)
        $availableYears = EpCampaignFramework::selectRaw('DISTINCT tahun')->orderByDesc('tahun')->pluck('tahun');
        if ($availableYears->isEmpty() || !$availableYears->contains(now()->year)) {
            $availableYears->prepend(now()->year);
        }

        $statuses = EpCampaignFramework::statusOptions();
        $bulanOpt = EpCampaignFramework::bulanOptions();

        return view('crud.ep-campaign.list', compact(
            'campaigns', 'filterTahun', 'filterStatus', 'filterSearch',
            'availableYears', 'statuses', 'bulanOpt'
        ));
    }

    public function create()
    {
        return view('crud.ep-campaign.new', $this->formData());
    }

    public function store()
    {
        $data = $this->validateCampaign();
        $newId = (string) Str::uuid();

        DB::transaction(function () use ($data, $newId) {
            EpCampaignFramework::create(array_merge($data['campaign'], [
                'id'         => $newId,
                'created_by' => session('id'),
                'created_at' => now(),
            ]));

            $this->syncWeeks($newId, $data['weeks']);
        });

        if (request()->expectsJson()) {
            return response()->json([
                'success'  => true,
                'id'       => $newId,
                'message'  => 'Campaign ditambahkan.',
                'edit_url' => route('epcampaign.edit', $newId),
            ]);
        }

        return redirect()->route('epcampaign')
            ->with('success', 'Campaign "' . $data['campaign']['campaign_name'] . '" ditambahkan.');
    }

    public function edit($id)
    {
        $campaign = EpCampaignFramework::with('weeks', 'kontens')->findOrFail($id);

        return view('crud.ep-campaign.edit', array_merge(
            $this->formData(),
            compact('campaign')
        ));
    }

    public function update($id)
    {
        $campaign = EpCampaignFramework::findOrFail($id);
        $data     = $this->validateCampaign();

        DB::transaction(function () use ($campaign, $data) {
            $campaign->update(array_merge($data['campaign'], [
                'updated_by' => session('id'),
                'updated_at' => now(),
            ]));

            EpCampaignWeek::where('ep_campaign_framework_id', $campaign->id)->delete();
            $this->syncWeeks($campaign->id, $data['weeks']);
        });

        if (request()->expectsJson()) {
            $campaign->refresh();
            $statusOpt = EpCampaignFramework::statusOptions()[$campaign->status] ?? null;
            $statusColors = \App\Models\EpKonten::statusColors()[$campaign->status] ?? null;

            return response()->json([
                'success'          => true,
                'message'          => 'Campaign berhasil diubah.',
                'updated_at_human' => $campaign->updated_at?->diffForHumans(),
                'status'           => $campaign->status,
                'status_label'     => $statusOpt['label'] ?? $campaign->status,
                'status_colors'    => $statusColors,
            ]);
        }

        return redirect()->route('epcampaign.edit', $id)
            ->with('success', 'Campaign berhasil diubah.');
    }

    public function destroy($id)
    {
        $campaign = EpCampaignFramework::withCount('kontens')->findOrFail($id);

        if ($campaign->kontens_count > 0) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak bisa dihapus — masih dipakai di ' . $campaign->kontens_count . ' konten EP.',
                ], 422);
            }
            return redirect()->route('epcampaign')
                ->with('error', 'Tidak bisa dihapus — masih dipakai di ' . $campaign->kontens_count . ' konten EP. Lepas linkage di EP Konten dulu.');
        }

        DB::transaction(function () use ($id) {
            EpCampaignWeek::where('ep_campaign_framework_id', $id)->delete();
            EpCampaignFramework::where('id', $id)->delete();
        });

        if (request()->expectsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Campaign dihapus.',
                'redirect_url' => route('epcampaign'),
            ]);
        }

        return redirect()->route('epcampaign')->with('success', 'Campaign dihapus.');
    }

    private function formData(): array
    {
        return [
            'statuses' => EpCampaignFramework::statusOptions(),
            'bulanOpt' => EpCampaignFramework::bulanOptions(),
        ];
    }

    private function validateCampaign(): array
    {
        request()->validate([
            'campaign_name' => 'required|max:255',
            'bulan'         => 'required|integer|between:1,12',
            'tahun'         => 'required|integer|min:2020|max:2100',
            'big_idea'      => 'nullable|string',
            'target'        => 'nullable|string',
            'status'        => 'required|in:' . implode(',', array_keys(EpCampaignFramework::statusOptions())),
            'weeks'         => 'nullable|array',
            'weeks.*.fokus'        => 'nullable|max:500',
            'weeks.*.deliverables' => 'nullable|string',
        ]);

        return [
            'campaign' => [
                'campaign_name' => request('campaign_name'),
                'bulan'         => (int) request('bulan'),
                'tahun'         => (int) request('tahun'),
                'big_idea'      => request('big_idea'),
                'target'        => request('target'),
                'status'        => request('status'),
            ],
            'weeks' => (array) request('weeks', []),
        ];
    }

    private function syncWeeks(string $campaignId, array $weeks): void
    {
        $rows = [];
        foreach ($weeks as $idx => $w) {
            $fokus = trim($w['fokus'] ?? '');
            $delv  = trim($w['deliverables'] ?? '');
            if ($fokus === '' && $delv === '') continue; // skip empty week

            $rows[] = [
                'id'                       => (string) Str::uuid(),
                'ep_campaign_framework_id' => $campaignId,
                'minggu_ke'                => (int) $idx + 1,
                'fokus'                    => $fokus ?: null,
                'deliverables'             => $delv ?: null,
                'created_at'               => now(),
            ];
        }

        if (!empty($rows)) {
            DB::table('ep_campaign_week')->insert($rows);
        }
    }
}
