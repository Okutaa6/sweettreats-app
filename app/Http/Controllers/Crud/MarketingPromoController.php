<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\MarketingPromo;
use App\Models\RawBranch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketingPromoController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;

        $filterStatus   = request('status');
        $filterCampaign = request('campaign_type');
        $filterBranch   = request('branch_id');
        $filterSearch   = request('search');
        $filterAktifPada = request('aktif_pada');   // tanggal: cek promo yang masih dalam periode pada tgl ini
        $filterPeriode   = request('periode');      // shortcut: today | this_month | next_month

        // Build base query (semua filter KECUALI status — buat summary count per status)
        $baseFilters = function ($q) use ($filterCampaign, $filterBranch, $filterSearch, $filterAktifPada, $filterPeriode) {
            if ($filterCampaign) $q->where('campaign_type', $filterCampaign);
            if ($filterBranch)   $q->forBranch($filterBranch);
            if ($filterSearch)   $q->where('nama_promo', 'like', "%{$filterSearch}%");
            if ($filterAktifPada) $q->withinPeriode(\Carbon\Carbon::parse($filterAktifPada));

            $periodeRange = $this->resolvePeriodeShortcut($filterPeriode);
            if ($periodeRange) {
                [$rs, $re] = $periodeRange;
                $q->where(fn($w) => $w->whereNull('periode_start')->orWhere('periode_start', '<=', $re))
                  ->where(fn($w) => $w->whereNull('periode_end')->orWhere('periode_end', '>=', $rs));
            }
        };

        // Summary count by status (filter lain ikut, status TIDAK)
        $summary = MarketingPromo::query()
            ->tap($baseFilters)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        // Listing query — semua filter ikut
        $query = MarketingPromo::with('branches')
            ->tap($baseFilters)
            ->orderBy('created_at', 'desc');

        if ($filterStatus) {
            $query->where('status', $filterStatus);
        }

        $promos    = $query->paginate($perPage)->withQueryString();
        $branches  = RawBranch::orderBy('name')->get();
        $campaigns = MarketingPromo::campaignTypes();
        $statuses  = MarketingPromo::statusOptions();

        return view('crud.marketing-promo.list', compact(
            'promos', 'branches', 'campaigns', 'statuses', 'summary',
            'filterStatus', 'filterCampaign', 'filterBranch', 'filterSearch',
            'filterAktifPada', 'filterPeriode'
        ));
    }

    private function resolvePeriodeShortcut(?string $key): ?array
    {
        if (!$key) return null;
        $now = now();
        return match ($key) {
            'today'      => [$now->copy()->startOfDay(),       $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(),     $now->copy()->endOfMonth()],
            'next_month' => [$now->copy()->addMonth()->startOfMonth(), $now->copy()->addMonth()->endOfMonth()],
            default      => null,
        };
    }

    public function create()
    {
        $branches  = RawBranch::orderBy('name')->get();
        $campaigns = MarketingPromo::campaignTypes();
        $statuses  = MarketingPromo::statusOptions();

        return view('crud.marketing-promo.new', compact('branches', 'campaigns', 'statuses'));
    }

    public function store()
    {
        $data = $this->validatePromo();

        DB::transaction(function () use ($data) {
            $id = (string) Str::uuid();

            MarketingPromo::create(array_merge($data['promo'], [
                'id'         => $id,
                'created_by' => session('id'),
                'created_at' => now(),
            ]));

            $this->syncBranches($id, $data['branch_ids']);
        });

        return redirect()->route('marketingpromo')->with('success', 'Promo berhasil ditambahkan');
    }

    public function edit($id)
    {
        $promo     = MarketingPromo::with('branches')->findOrFail($id);
        $branches  = RawBranch::orderBy('name')->get();
        $campaigns = MarketingPromo::campaignTypes();
        $statuses  = MarketingPromo::statusOptions();
        $selectedBranchIds = $promo->branches->pluck('id')->toArray();

        return view('crud.marketing-promo.edit', compact(
            'promo', 'branches', 'campaigns', 'statuses', 'selectedBranchIds'
        ));
    }

    public function update($id)
    {
        $promo = MarketingPromo::findOrFail($id);
        $data  = $this->validatePromo();

        DB::transaction(function () use ($promo, $data) {
            $promo->update(array_merge($data['promo'], [
                'updated_by' => session('id'),
                'updated_at' => now(),
            ]));

            DB::table('marketing_promo_branch')->where('marketing_promo_id', $promo->id)->delete();
            $this->syncBranches($promo->id, $data['branch_ids']);
        });

        return redirect()->route('marketingpromo.edit', $id)->with('success', 'Promo berhasil diubah');
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            DB::table('marketing_promo_branch')->where('marketing_promo_id', $id)->delete();
            MarketingPromo::where('id', $id)->delete();
        });

        return redirect()->route('marketingpromo')->with('success', 'Promo berhasil dihapus');
    }

    /**
     * AJAX: ubah status promo langsung dari list (planning/aktif/selesai).
     */
    public function updateStatus($id)
    {
        request()->validate([
            'status' => 'required|in:planning,aktif,selesai',
        ]);

        $promo = MarketingPromo::findOrFail($id);
        $promo->update([
            'status'     => request('status'),
            'updated_by' => session('id'),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => $promo->status,
            'label'  => MarketingPromo::statusOptions()[$promo->status]['label'] ?? $promo->status,
        ]);
    }

    /**
     * API: list promo aktif yang tersedia untuk cabang tertentu di tanggal tertentu.
     * Dipanggil dari form reservasi (sales picker).
     */
    public function apiActiveByBranch()
    {
        $branchId = request('branch_id');
        $on       = request('date') ? \Carbon\Carbon::parse(request('date')) : now();

        $promos = MarketingPromo::aktif()
            ->forBranch($branchId)
            ->withinPeriode($on)
            ->orderBy('nama_promo')
            ->get(['id', 'nama_promo', 'campaign_type', 'harga_promo', 'harga_normal', 'voucher_tambahan_pasien', 'periode_start', 'periode_end']);

        return response()->json([
            'data' => $promos->map(fn($p) => [
                'id'                      => $p->id,
                'nama_promo'              => $p->nama_promo,
                'campaign_label'          => MarketingPromo::campaignTypes()[$p->campaign_type] ?? $p->campaign_type,
                'harga_promo'             => $p->harga_promo,
                'harga_normal'            => $p->harga_normal,
                'voucher_tambahan_pasien' => $p->voucher_tambahan_pasien,
                'periode'                 => trim(
                    ($p->periode_start ? $p->periode_start->format('d/m/Y') : '') .
                    ' – ' .
                    ($p->periode_end ? $p->periode_end->format('d/m/Y') : '')
                ),
            ]),
        ]);
    }

    private function validatePromo(): array
    {
        request()->validate([
            'nama_promo'              => 'required|string|max:255',
            'campaign_type'           => 'required|in:' . implode(',', array_keys(MarketingPromo::campaignTypes())),
            'detail_promo'            => 'nullable|string',
            'syarat_ketentuan'        => 'nullable|string',
            'cetakan'                 => 'nullable|string|max:255',
            'periode_start'           => 'nullable|date',
            'periode_end'             => 'nullable|date|after_or_equal:periode_start',
            'status'                  => 'required|in:planning,aktif,selesai',
            'harga_normal'            => 'nullable|numeric|min:0',
            'harga_promo'             => 'nullable|numeric|min:0',
            'cogs_fee_dokter'         => 'nullable|numeric|min:0',
            'voucher_tambahan_pasien' => 'nullable|numeric|min:0',
            'is_active'               => 'nullable',
            'branch_ids'              => 'nullable|array',
            'branch_ids.*'            => 'exists:raw_branch,id',
        ]);

        $hargaPromo    = (float) request('harga_promo', 0);
        $cogsFeeDokter = (float) request('cogs_fee_dokter', 0);
        $marginRp      = $hargaPromo - $cogsFeeDokter;
        $marginPct     = $hargaPromo > 0 ? round(($marginRp / $hargaPromo) * 100, 4) : null;

        $periodeStart = request('periode_start');
        $bulan = $periodeStart ? \Carbon\Carbon::parse($periodeStart)->format('F') : null;
        $tahun = $periodeStart ? (int) \Carbon\Carbon::parse($periodeStart)->format('Y') : null;

        return [
            'promo' => [
                'nama_promo'              => request('nama_promo'),
                'campaign_type'           => request('campaign_type'),
                'detail_promo'            => request('detail_promo'),
                'syarat_ketentuan'        => request('syarat_ketentuan'),
                'cetakan'                 => request('cetakan'),
                'bulan_promo'             => $bulan,
                'tahun_promo'             => $tahun,
                'periode_start'           => $periodeStart ?: null,
                'periode_end'             => request('periode_end') ?: null,
                'status'                  => request('status'),
                'harga_normal'            => request('harga_normal'),
                'harga_promo'             => $hargaPromo ?: null,
                'cogs_fee_dokter'         => $cogsFeeDokter ?: null,
                'margin_rp'               => $hargaPromo ? $marginRp : null,
                'margin_pct'              => $marginPct,
                'voucher_tambahan_pasien' => request('voucher_tambahan_pasien') ?: null,
                'is_active'               => request('is_active', 0) ? 1 : 0,
            ],
            'branch_ids' => array_filter((array) request('branch_ids', [])),
        ];
    }

    private function syncBranches(string $promoId, array $branchIds): void
    {
        if (empty($branchIds)) return; // kosong = semua cabang

        $rows = array_map(fn($branchId) => [
            'id'                 => (string) Str::uuid(),
            'marketing_promo_id' => $promoId,
            'raw_branch_id'      => $branchId,
            'created_at'         => now(),
        ], $branchIds);

        DB::table('marketing_promo_branch')->insert($rows);
    }
}
