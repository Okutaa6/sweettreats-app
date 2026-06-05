<?php

namespace App\Http\Controllers;

use App\Models\RawCusReservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Executive Report — analitik untuk manajemen, rentang tanggal bebas.
 *
 * Menggabungkan dua sumber revenue:
 *   - Actual  : omnicare_sales_detail.net_sell_price (uang nyata POS klinik)
 *   - Booking : raw_cus_reservation.total_reservasi   (nilai reservasi/estimasi)
 * dibandingkan target bulanan per cabang (raw_branch_target.nominal).
 *
 * Achievement (tercapai / sisa / %) dihitung dari ACTUAL vs target; booking
 * ditampilkan sebagai konteks pipeline + indikator konversi reservasi→transaksi.
 *
 * Periode = rentang tanggal bebas (date_from..date_to) + preset cepat. Section
 * yang inherent bulanan/tahunan menyesuaikan:
 *   - Target  : jumlah target bulan-bulan yang tersentuh rentang
 *   - DoD     : per hari dalam rentang
 *   - MoM     : 12 bulan terakhir s/d bulan akhir rentang
 *   - YoY     : rentang yang sama digeser per tahun (5 tahun terakhir)
 *
 * Akses: checkbox-driven via menu access (group_access). Grant menu node
 * 'report/executive' ke group mana pun (Super Admin, Admin General, Executive,
 * dll) lewat UI Akses + `php artisan access:rebuild` → otomatis bisa masuk.
 * Gate di controller karena AuthGuard hanya cek segment-1 'report' (granted
 * luas untuk Laporan Penjualan), jadi sub-halaman ini perlu cek per-node.
 */
class ExecutiveReportController extends Controller
{
    /**
     * Net revenue Omnicare: exclude Voucher Tunai (item_type 'V') & Service
     * Charge — konsisten dengan definisi "Net" di SalesReportController.
     */
    private const NET_EXPR = "COALESCE(SUM(CASE WHEN item_type <> 'V' AND item_name NOT LIKE 'Service Charge%' THEN net_sell_price ELSE 0 END),0)";

    public function index()
    {
        abort_unless(
            hasMenuAccess('report/executive'),
            403,
            'Anda tidak punya akses ke halaman ini. Hubungi admin untuk diberi akses.'
        );

        $today = Carbon::now();
        [$start, $end] = $this->resolveRange($today);
        $anchor = $end->copy(); // bulan acuan untuk target/MoM/YoY label

        // 5 cabang yang ter-mapping ke Omnicare
        $branches = DB::table('raw_branch')
            ->whereNotNull('omnicare_place_id')
            ->orderBy('name')
            ->get(['id', 'name', 'omnicare_place_id']);

        // Filter cabang opsional (?place=<raw_branch.id>)
        $filterId    = request('place') ?: null;
        $placeBranch = $filterId ? $branches->firstWhere('id', $filterId) : null;
        if (! $placeBranch) {
            $filterId = null;
        }
        $placeId   = $placeBranch->omnicare_place_id ?? null; // scope Omnicare
        $branchId  = $placeBranch->id ?? null;                // scope reservasi
        $allPlaces = $branches->pluck('omnicare_place_id')->all();

        // ── Achievement per cabang (target = jumlah bulan yang tersentuh rentang) ──
        $targets         = $this->targetsByBranch($start, $end);
        $actualByPlace   = $this->actualByPlace($start, $end, $allPlaces);
        $bookingByBranch = $this->bookingByBranch($start, $end);

        $rows = $branches
            ->when($filterId, fn (Collection $c) => $c->where('id', $filterId))
            ->map(function ($b) use ($targets, $actualByPlace, $bookingByBranch) {
                $target  = (float) ($targets[$b->id] ?? 0);
                $actual  = (float) ($actualByPlace[$b->omnicare_place_id] ?? 0);
                $booking = (float) ($bookingByBranch[$b->id] ?? 0);

                return (object) [
                    'name'      => $b->name,
                    'target'    => $target,
                    'actual'    => $actual,
                    'booking'   => $booking,
                    'pct'       => $target > 0 ? round($actual / $target * 100, 1) : null,
                    'remaining' => max(0, $target - $actual),
                    'conv'      => $booking > 0 ? round($actual / $booking * 100, 1) : null,
                ];
            })->values();

        $totals = (object) [
            'target'  => (float) $rows->sum('target'),
            'actual'  => (float) $rows->sum('actual'),
            'booking' => (float) $rows->sum('booking'),
        ];
        $totals->pct       = $totals->target > 0 ? round($totals->actual / $totals->target * 100, 1) : null;
        $totals->remaining = max(0, $totals->target - $totals->actual);
        $totals->conv      = $totals->booking > 0 ? round($totals->actual / $totals->booking * 100, 1) : null;

        // ── KPI: total pasien + new vs returning ──
        $patients = $this->patientStats($start, $end, $placeId, $allPlaces);

        // ── DoD: per hari dalam rentang ──
        $daily = $this->buildDaily($start, $end, $placeId, $allPlaces);

        // ── MoM: 12 bulan terakhir s/d bulan akhir rentang ──
        $monthly = $this->buildMonthly($anchor->year, $anchor->month, $placeId, $allPlaces, $branchId);

        // ── YoY: rentang yang sama digeser per tahun ──
        $yoy = $this->buildYoY($start, $end, $placeId, $branches, $allPlaces, $filterId);

        // ── Layer 1: Hero snapshot ──
        $topBranch = $this->buildTopBranch($start, $end, $allPlaces, $branches);
        $txCount   = $this->txCount($start, $end, $placeId, $allPlaces);
        $hero      = $this->buildHero($totals, $yoy, $patients, $topBranch, $txCount);

        // ── Layer 2: Top Treatments + Top Doctors + mix + discount ──
        $topTreatments = $this->buildTopTreatments($start, $end, $placeId, $allPlaces);
        $topDoctors    = $this->buildTopDoctors($start, $end, $placeId, $allPlaces);
        $treatmentMix  = $this->buildTreatmentMix($start, $end, $placeId, $allPlaces);
        $discount      = $this->buildDiscountImpact($start, $end, $placeId, $allPlaces);

        // ── Layer 2: Promo + Patient retention + Cross-branch flow ──
        $topPromos   = $this->buildTopPromos($start, $end, $branchId);
        $retention   = $this->buildPatientRetention($start, $end, $placeId, $allPlaces);
        $crossBranch = $this->buildCrossBranchFlow($start, $end, $allPlaces);

        // ── Modal detail: Patient drill (per cabang + 12-mo trend + top dokter by pasien) ──
        $patientByBranch    = $this->buildPatientByBranch($start, $end, $branches);
        $patientTrend       = $this->buildPatientTrend($end, $placeId, $allPlaces);
        $topDoctorsByPasien = $this->buildTopDoctorsByPatient($start, $end, $placeId, $allPlaces);

        // ── Branch comparison — SELALU semua cabang (filter-independent), tujuannya
        //    membandingkan. Pakai data all-branch yang sudah dihitung di atas. ──
        $branchComparison = $this->buildBranchComparison(
            $start, $end, $branches, $actualByPlace, $bookingByBranch, $targets, $patientByBranch
        );

        // ── Layer 3: Drill trends (12-bulan per top item/dokter, 1 query batched each) ──
        $treatmentTrends = $this->buildTreatmentTrends(
            $topTreatments->pluck('code')->all(), $end, $placeId ? [$placeId] : $allPlaces
        );
        $doctorTrends = $this->buildDoctorTrends(
            $topDoctors->pluck('id')->all(), $end, $placeId ? [$placeId] : $allPlaces
        );

        // Preset cepat + label periode
        $presets    = $this->presets($today);
        $spansMonth = $start->format('Y-m') !== $end->format('Y-m');

        return view('report.executive', array_merge(
            compact('branches', 'filterId', 'rows', 'totals', 'patients', 'daily', 'monthly', 'yoy', 'presets', 'spansMonth', 'hero', 'topTreatments', 'topDoctors', 'treatmentMix', 'discount', 'topPromos', 'retention', 'crossBranch', 'treatmentTrends', 'doctorTrends', 'patientByBranch', 'patientTrend', 'topDoctorsByPasien', 'branchComparison'),
            [
                'start'      => $start->toDateString(),
                'end'        => $end->toDateString(),
                'branchName' => $placeBranch->name ?? 'Semua Cabang',
            ]
        ));
    }

    // ────────────────────────────────────────────────────────────────────
    // Periode
    // ────────────────────────────────────────────────────────────────────

    /** @return array{0:Carbon,1:Carbon} [start(startOfDay), end(endOfDay)] */
    private function resolveRange(Carbon $today): array
    {
        $start = $end = null;
        try {
            if (request('date_from')) {
                $start = Carbon::parse(request('date_from'))->startOfDay();
            }
            if (request('date_to')) {
                $end = Carbon::parse(request('date_to'))->endOfDay();
            }
        } catch (\Throwable $e) {
            $start = $end = null;
        }

        if (! $start || ! $end) {
            // Default: 30 hari terakhir s/d hari ini — lebih menyesatkan kalau pakai
            // bulan berjalan saat awal bulan / data sync belum catch up.
            $start = $today->copy()->subDays(29)->startOfDay();
            $end   = $today->copy()->endOfDay();
        }
        if ($end->lt($start)) {
            [$start, $end] = [$start->copy()->startOfDay(), $end->copy()->endOfDay()];
            // sudah swap di bawah; jaga urutan benar
        }
        if ($end->lt($start)) {
            $tmp = $start; $start = $end; $end = $tmp;
        }

        return [$start, $end];
    }

    private function presets(Carbon $today): array
    {
        return [
            'Hari Ini'   => [$today->toDateString(), $today->toDateString()],
            'Minggu Ini' => [$today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()],
            'Bulan Ini'  => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
            'Bulan Lalu' => [$today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                             $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            '30 Hari'    => [$today->copy()->subDays(29)->toDateString(), $today->toDateString()],
            '90 Hari'    => [$today->copy()->subDays(89)->toDateString(), $today->toDateString()],
            'Tahun Ini'  => [$today->copy()->startOfYear()->toDateString(), $today->toDateString()],
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    // Builders
    // ────────────────────────────────────────────────────────────────────

    /** Target bulanan dijumlah untuk semua bulan yang tersentuh rentang, per cabang. */
    private function targetsByBranch(Carbon $start, Carbon $end): Collection
    {
        $months = [];
        $cur = $start->copy()->startOfMonth();
        while ($cur->lte($end)) {
            $months[] = [(int) $cur->year, (int) $cur->month];
            $cur->addMonth();
        }
        if (empty($months)) {
            return collect();
        }

        return DB::table('raw_branch_target')
            ->where(function ($q) use ($months) {
                foreach ($months as [$y, $m]) {
                    $q->orWhere(fn ($w) => $w->where('tahun', $y)->where('bulan', $m));
                }
            })
            ->groupBy('cabang_id')
            ->selectRaw('cabang_id, COALESCE(SUM(nominal),0) AS total')
            ->pluck('total', 'cabang_id');
    }

    /** Actual net revenue (Omnicare) per place_id untuk rentang tanggal. */
    private function actualByPlace(Carbon $start, Carbon $end, array $places): Collection
    {
        if (empty($places)) {
            return collect();
        }

        return DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('place_id', $places)
            ->selectRaw('place_id, ' . self::NET_EXPR . ' AS net')
            ->groupBy('place_id')
            ->pluck('net', 'place_id');
    }

    /** Booking revenue (reservasi SweetTreats) per raw_branch_id. */
    private function bookingByBranch(Carbon $start, Carbon $end): Collection
    {
        return DB::table('raw_cus_reservation')
            ->whereBetween('date', [$start, $end])
            ->where('status', '!=', RawCusReservation::STATUS_CANCELLED)
            ->selectRaw('raw_branch_id, COALESCE(SUM(total_reservasi),0) AS booking')
            ->groupBy('raw_branch_id')
            ->pluck('booking', 'raw_branch_id');
    }

    /**
     * Total pasien + new vs returning (basis Omnicare).
     * "Baru" = bulan registrasi (4 digit YYMM di p_id Omnicare <prefix>-YYMM-NNNN)
     * jatuh dalam rentang. Returning = sisa pasien yang transaksi di rentang.
     */
    private function patientStats(Carbon $start, Carbon $end, ?string $placeId, array $places): object
    {
        $startYYMM = $start->format('ym');
        $endYYMM   = $end->format('ym');

        $q = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('p_id')->where('p_id', '!=', '');
        $placeId ? $q->where('place_id', $placeId) : $q->whereIn('place_id', $places);

        $row = $q->selectRaw(
            "COUNT(DISTINCT p_id) AS total,
             COUNT(DISTINCT CASE WHEN REGEXP_SUBSTR(p_id, '[0-9]{4}') BETWEEN ? AND ? THEN p_id END) AS baru",
            [$startYYMM, $endYYMM]
        )->first();

        $total = (int) ($row->total ?? 0);
        $baru  = (int) ($row->baru ?? 0);

        return (object) [
            'total'     => $total,
            'baru'      => $baru,
            'returning' => max(0, $total - $baru),
        ];
    }

    /**
     * Series harian dalam rentang untuk chart DoD + tabel.
     * @return array{labels:array,revenue:array,patients:array,rows:array}
     */
    private function buildDaily(Carbon $start, Carbon $end, ?string $placeId, array $places): array
    {
        $q = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$start->toDateString(), $end->toDateString()]);
        $placeId ? $q->where('place_id', $placeId) : $q->whereIn('place_id', $places);

        $byDate = $q->selectRaw(
                'trx_date, ' . self::NET_EXPR . ' AS net,
                 COUNT(DISTINCT p_id) AS patients,
                 COUNT(DISTINCT encounter_id) AS visits'
            )
            ->groupBy('trx_date')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->trx_date)->toDateString());

        $labels = $revenue = $patients = $rows = [];
        $lastActiveRev = null;

        $cur = $start->copy();
        while ($cur->lte($end)) {
            $dateStr = $cur->toDateString();
            $row     = $byDate->get($dateStr);
            $rev     = (float) (optional($row)->net ?? 0);
            $pat     = (int) (optional($row)->patients ?? 0);

            $labels[]   = $cur->format('d/m');
            $revenue[]  = $rev;
            $patients[] = $pat;

            if ($row) {
                $growth = ($lastActiveRev !== null && $lastActiveRev > 0)
                    ? round(($rev - $lastActiveRev) / $lastActiveRev * 100, 1)
                    : null;
                $rows[] = (object) [
                    'date'     => $dateStr,
                    'net'      => $rev,
                    'patients' => $pat,
                    'visits'   => (int) $row->visits,
                    'growth'   => $growth,
                ];
                $lastActiveRev = $rev;
            }
            $cur->addDay();
        }

        return compact('labels', 'revenue', 'patients', 'rows');
    }

    /**
     * Series 12 bulan terakhir untuk chart MoM + tabel (revenue, target, pasien,
     * booking, growth%).
     */
    private function buildMonthly(int $year, int $month, ?string $placeId, array $places, ?string $branchId): array
    {
        $anchor = Carbon::create($year, $month, 1);
        $from   = $anchor->copy()->subMonths(11)->startOfMonth();
        $to     = $anchor->copy()->endOfMonth();

        // Per-month query pakai whereBetween → hit index (place_id, trx_date).
        // Jauh lebih cepat dari 1 query 12-bulan dgn DATE_FORMAT + GROUP BY.
        $actualByMonth = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthStart = $anchor->copy()->subMonths($i)->startOfMonth();
            $monthEnd   = $monthStart->copy()->endOfMonth();
            $q          = DB::table('omnicare_sales_detail')
                ->whereBetween('trx_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            $placeId ? $q->where('place_id', $placeId) : $q->whereIn('place_id', $places);
            $actualByMonth[$monthStart->format('Y-m')] = $q->selectRaw(
                self::NET_EXPR . ' AS net, COUNT(DISTINCT p_id) AS patients'
            )->first();
        }

        $bookingQ = DB::table('raw_cus_reservation')
            ->whereBetween('date', [$from, $to])
            ->where('status', '!=', RawCusReservation::STATUS_CANCELLED);
        if ($branchId) {
            $bookingQ->where('raw_branch_id', $branchId);
        }
        $booking = $bookingQ->selectRaw("DATE_FORMAT(date, '%Y-%m') AS ym, COALESCE(SUM(total_reservasi),0) AS booking")
            ->groupBy('ym')->get()->keyBy('ym');

        $targetQ = DB::table('raw_branch_target')
            ->whereRaw('tahun * 100 + bulan BETWEEN ? AND ?', [
                $from->year * 100 + $from->month,
                $anchor->year * 100 + $anchor->month,
            ]);
        if ($branchId) {
            $targetQ->where('cabang_id', $branchId);
        }
        $target = $targetQ->selectRaw("CONCAT(tahun, '-', LPAD(bulan, 2, '0')) AS ym, COALESCE(SUM(nominal),0) AS target")
            ->groupBy('ym')->get()->keyBy('ym');

        $labels = $revenue = $patients = $bookingArr = $targetArr = $growth = [];
        for ($i = 11; $i >= 0; $i--) {
            $d  = $anchor->copy()->subMonths($i);
            $ym = $d->format('Y-m');

            $labels[]     = $d->format('M Y');
            $row          = $actualByMonth[$ym] ?? null;
            $revenue[]    = (float) ($row->net ?? 0);
            $patients[]   = (int) ($row->patients ?? 0);
            $bookingArr[] = (float) (optional($booking->get($ym))->booking ?? 0);
            $targetArr[]  = (float) (optional($target->get($ym))->target ?? 0);
        }

        foreach ($revenue as $i => $rev) {
            $prev     = $i > 0 ? $revenue[$i - 1] : null;
            $growth[] = ($prev !== null && $prev > 0) ? round(($rev - $prev) / $prev * 100, 1) : null;
        }

        return compact('labels', 'revenue', 'patients', 'bookingArr', 'targetArr', 'growth');
    }

    /**
     * Year-over-Year: rentang yang sama digeser per tahun (5 tahun terakhir,
     * basis actual Omnicare — booking & target hanya tahun berjalan).
     *
     * Mengembalikan ringkasan per tahun (revenue, pasien, growth YoY) + matriks
     * per cabang × tahun — mis. Bintaro Mar-26 vs Mar-25 vs Mar-24.
     */
    private function buildYoY(Carbon $start, Carbon $end, ?string $placeId, Collection $branches, array $allPlaces, ?string $filterId): array
    {
        $offsets = [4, 3, 2, 1, 0]; // tahun ke belakang → terbaru terakhir
        $years   = $labels = [];
        $revenue = $patients = $growth = [];

        $places = $placeId ? [$placeId] : $allPlaces;

        // Per-cabang dalam scope (untuk matriks)
        $scopeBranches = $filterId ? $branches->where('id', $filterId)->values() : $branches;
        $matrixAccum   = [];
        foreach ($scopeBranches as $b) {
            $matrixAccum[$b->omnicare_place_id] = ['name' => $b->name, 'values' => []];
        }
        // PENTING: array_keys() meng-cast numeric-string key (mis. "29957") jadi
        // integer. place_id kolom varchar → binding integer bikin MySQL gagal
        // pakai index (full scan, ~6x lebih lambat). Cast balik ke string.
        $scopePlaceIds = array_map('strval', array_keys($matrixAccum)) ?: ['_none_'];

        foreach ($offsets as $k) {
            $s = $start->copy()->subYears($k);
            $e = $end->copy()->subYears($k);

            $years[]  = $e->year;
            $labels[] = (string) $e->year;

            // Matriks per cabang — sekalian jadi sumber net ringkasan (sum per place)
            $perPlace = DB::table('omnicare_sales_detail')
                ->whereBetween('trx_date', [$s->toDateString(), $e->toDateString()])
                ->whereIn('place_id', $scopePlaceIds)
                ->selectRaw('place_id, ' . self::NET_EXPR . ' AS net')
                ->groupBy('place_id')->pluck('net', 'place_id');
            foreach ($matrixAccum as $pid => &$entry) {
                $entry['values'][] = (float) ($perPlace[(string) $pid] ?? 0);
            }
            unset($entry);

            // Ringkasan net = sum dari perPlace (hindari 1 query NET_EXPR ekstra).
            // Patient count tetap query sendiri (COUNT DISTINCT tak bisa di-sum per place).
            $revenue[]  = (float) $perPlace->sum();
            $patients[] = (int) DB::table('omnicare_sales_detail')
                ->whereBetween('trx_date', [$s->toDateString(), $e->toDateString()])
                ->whereIn('place_id', $places)
                ->distinct()->count('p_id');
        }

        foreach ($revenue as $i => $rev) {
            $prev     = $i > 0 ? $revenue[$i - 1] : null;
            $growth[] = ($prev !== null && $prev > 0) ? round(($rev - $prev) / $prev * 100, 1) : null;
        }

        $matrix = collect($matrixAccum)->map(function ($e) {
            $last = end($e['values']);
            $prev = $e['values'][count($e['values']) - 2] ?? null;

            return (object) [
                'name'   => $e['name'],
                'values' => $e['values'],
                'growth' => ($prev !== null && $prev > 0) ? round(($last - $prev) / $prev * 100, 1) : null,
            ];
        })->values();

        return compact('years', 'labels', 'revenue', 'patients', 'growth', 'matrix');
    }

    // ────────────────────────────────────────────────────────────────────
    // Layer 1 — Hero snapshot
    // ────────────────────────────────────────────────────────────────────

    /** COUNT DISTINCT encounter_id dalam rentang (= jumlah kunjungan/transaksi). */
    private function txCount(Carbon $start, Carbon $end, ?string $placeId, array $places): int
    {
        $q = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('encounter_id')->where('encounter_id', '!=', '');
        $placeId ? $q->where('place_id', $placeId) : $q->whereIn('place_id', $places);

        return (int) $q->distinct()->count('encounter_id');
    }

    /** Cabang dengan revenue actual tertinggi di rentang. Null jika semua 0. */
    private function buildTopBranch(Carbon $start, Carbon $end, array $places, Collection $branches): ?object
    {
        if (empty($places)) {
            return null;
        }

        $top = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('place_id', $places)
            ->selectRaw('place_id, ' . self::NET_EXPR . ' AS net')
            ->groupBy('place_id')
            ->orderByDesc('net')
            ->limit(1)
            ->first();

        if (! $top || (float) $top->net <= 0) {
            return null;
        }

        $branch = $branches->firstWhere('omnicare_place_id', $top->place_id);

        return (object) [
            'name' => $branch->name ?? 'Cabang',
            'net'  => (float) $top->net,
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    // Layer 2 — Top Treatments + Top Doctors
    // ────────────────────────────────────────────────────────────────────

    /**
     * Top perawatan (item_type='P', exclude Service Charge) by net revenue.
     * @return Collection<{code, name, type, qty, net, profit, patients}>
     */
    private function buildTopTreatments(Carbon $start, Carbon $end, ?string $placeId, array $places, int $limit = 10): Collection
    {
        $q = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$start->toDateString(), $end->toDateString()])
            ->where('item_type', 'P')
            ->where('item_name', 'NOT LIKE', 'Service Charge%')
            ->whereNotNull('item_code')->where('item_code', '!=', '');
        $placeId ? $q->where('place_id', $placeId) : $q->whereIn('place_id', $places);

        return $q->selectRaw('
                item_code AS code,
                MAX(item_name) AS name,
                MAX(item_type) AS type,
                COALESCE(SUM(qty_sold), 0) AS qty,
                COALESCE(SUM(net_sell_price), 0) AS net,
                COALESCE(SUM(net_profit), 0) AS profit,
                COUNT(DISTINCT p_id) AS patients
            ')
            ->groupBy('item_code')
            ->orderByDesc('net')
            ->limit($limit)
            ->get();
    }

    /**
     * Top dokter by net revenue. Join `raw_doctor` via omnicare_doc_id ↔
     * sales_detail.doc_reff_id. Fallback ke nama mentah dari sales_detail.doctor
     * jika dokter belum ter-mapping.
     * @return Collection<{id, name, net, patients, visits, avgPerPatient}>
     */
    private function buildTopDoctors(Carbon $start, Carbon $end, ?string $placeId, array $places, int $limit = 10): Collection
    {
        $q = DB::table('omnicare_sales_detail as s')
            ->leftJoin('raw_doctor as d', 'd.omnicare_doc_id', '=', 's.doc_reff_id')
            ->whereBetween('s.trx_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('s.doc_reff_id')->where('s.doc_reff_id', '!=', '');
        $placeId ? $q->where('s.place_id', $placeId) : $q->whereIn('s.place_id', $places);

        return $q->selectRaw("
                s.doc_reff_id AS id,
                COALESCE(MAX(d.name), MAX(s.doctor), '?') AS name,
                COALESCE(SUM(CASE WHEN s.item_type <> 'V' AND s.item_name NOT LIKE 'Service Charge%' THEN s.net_sell_price ELSE 0 END), 0) AS net,
                COUNT(DISTINCT s.p_id) AS patients,
                COUNT(DISTINCT s.encounter_id) AS visits
            ")
            ->groupBy('s.doc_reff_id')
            ->orderByDesc('net')
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                $r->avgPerPatient = $r->patients > 0 ? (float) $r->net / (int) $r->patients : 0;

                return $r;
            });
    }

    /**
     * Komposisi penjualan per item_type (P/D/SC/V/S), Service Charge dipisah dari
     * P via item_name LIKE. Konsisten dengan SalesReportController composition.
     * @return Collection<{type, label, net, qty, pct}>
     */
    private function buildTreatmentMix(Carbon $start, Carbon $end, ?string $placeId, array $places): Collection
    {
        $q = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$start->toDateString(), $end->toDateString()]);
        $placeId ? $q->where('place_id', $placeId) : $q->whereIn('place_id', $places);

        $groupExpr = "CASE WHEN item_name LIKE 'Service Charge%' THEN 'SC' ELSE item_type END";

        $rows = $q->selectRaw("$groupExpr AS type,
                COALESCE(SUM(qty_sold), 0) AS qty,
                COALESCE(SUM(net_sell_price), 0) AS net")
            ->groupBy(DB::raw($groupExpr))
            ->orderByDesc('net')
            ->get();

        $total  = max(1, (float) $rows->sum('net'));
        $labels = ['P' => 'Prosedur', 'D' => 'Obat & BHP', 'SC' => 'Service Charge', 'V' => 'Voucher', 'S' => 'Supply'];

        return $rows->map(fn ($r) => (object) [
            'type'  => $r->type,
            'label' => $labels[$r->type] ?? ('Tipe ' . $r->type),
            'net'   => (float) $r->net,
            'qty'   => (float) $r->qty,
            'pct'   => round((float) $r->net / $total * 100, 1),
        ]);
    }

    /**
     * Trend diskon 12 bulan trailing + breakdown per item_type untuk rentang aktif.
     * @return array{labels:array, discount:array, net:array, pct:array, byType:Collection, totalDiscount:float}
     */
    private function buildDiscountImpact(Carbon $start, Carbon $end, ?string $placeId, array $places): array
    {
        $anchor = $end->copy();

        // Per-month query (hit index, hindari DATE_FORMAT GROUP BY)
        $labels = $discount = $net = $pct = [];
        for ($i = 11; $i >= 0; $i--) {
            $d          = $anchor->copy()->subMonths($i);
            $monthStart = $d->copy()->startOfMonth();
            $monthEnd   = $d->copy()->endOfMonth();
            $labels[]   = $d->format('M Y');

            $q = DB::table('omnicare_sales_detail')
                ->whereBetween('trx_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            $placeId ? $q->where('place_id', $placeId) : $q->whereIn('place_id', $places);
            $row = $q->selectRaw('COALESCE(SUM(discount), 0) AS disc, ' . self::NET_EXPR . ' AS net')->first();

            $disc       = (float) ($row->disc ?? 0);
            $nt         = (float) ($row->net ?? 0);
            $discount[] = $disc;
            $net[]      = $nt;
            $pct[]      = $nt > 0 ? round($disc / $nt * 100, 1) : 0;
        }

        // Breakdown per item type untuk rentang aktif (bukan 12 bulan)
        $qByType = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$start->toDateString(), $end->toDateString()]);
        $placeId ? $qByType->where('place_id', $placeId) : $qByType->whereIn('place_id', $places);

        $groupExpr = "CASE WHEN item_name LIKE 'Service Charge%' THEN 'SC' ELSE item_type END";
        $byType = $qByType->selectRaw("$groupExpr AS type,
                COALESCE(SUM(discount), 0) AS disc")
            ->groupBy(DB::raw($groupExpr))
            ->orderByDesc('disc')
            ->get();

        $totalDiscount = (float) $byType->sum('disc');
        $typeLabels    = ['P' => 'Prosedur', 'D' => 'Obat & BHP', 'SC' => 'Service Charge', 'V' => 'Voucher', 'S' => 'Supply'];
        $byType        = $byType->map(fn ($r) => (object) [
            'type'  => $r->type,
            'label' => $typeLabels[$r->type] ?? ('Tipe ' . $r->type),
            'disc'  => (float) $r->disc,
            'pct'   => $totalDiscount > 0 ? round((float) $r->disc / $totalDiscount * 100, 1) : 0,
        ]);

        return compact('labels', 'discount', 'net', 'pct', 'byType', 'totalDiscount');
    }

    /**
     * Top promo by usage count (raw_cus_reservation.raw_promo_id). Data masih
     * sparse — section akan tampil dgn badge "data masih dikumpulkan" jika 0.
     * @return Collection<{id, code, scope, uses, booking}>
     */
    private function buildTopPromos(Carbon $start, Carbon $end, ?string $branchId, int $limit = 10): Collection
    {
        return DB::table('raw_promo as p')
            ->leftJoin('raw_cus_reservation as r', function ($j) use ($start, $end, $branchId) {
                $j->on('r.raw_promo_id', '=', 'p.id')
                  ->whereBetween('r.date', [$start, $end])
                  ->where('r.status', '!=', RawCusReservation::STATUS_CANCELLED);
                if ($branchId) {
                    $j->where('r.raw_branch_id', $branchId);
                }
            })
            ->selectRaw('p.id, p.promo_code AS code, p.referral_scope AS scope,
                         COUNT(r.id) AS uses,
                         COALESCE(SUM(r.total_reservasi), 0) AS booking')
            ->groupBy('p.id', 'p.promo_code', 'p.referral_scope')
            ->orderByDesc('uses')
            ->orderBy('p.promo_code')
            ->limit($limit)
            ->get();
    }

    /**
     * Patient retention: dari pasien yang transaksi di periode, berapa % yang
     * juga punya transaksi 12 bulan SEBELUM start (= pasien aktif sebelumnya).
     * Lookback dibatasi 12 bulan supaya scan tidak menghantam semua history.
     */
    private function buildPatientRetention(Carbon $start, Carbon $end, ?string $placeId, array $places): object
    {
        $scope = $placeId ? [$placeId] : $places;
        if (empty($scope)) {
            return (object) ['total' => 0, 'repeat' => 0, 'repeatRate' => 0, 'avgVisitsPerPatient' => 0];
        }

        $cur = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('place_id', $scope)
            ->whereNotNull('p_id')->where('p_id', '!=', '')
            ->selectRaw('COUNT(DISTINCT p_id) AS total, COUNT(DISTINCT encounter_id) AS visits')
            ->first();

        $total  = (int) ($cur->total ?? 0);
        $visits = (int) ($cur->visits ?? 0);
        $repeat = 0;

        if ($total > 0) {
            $lookbackFrom = $start->copy()->subMonths(12)->toDateString();
            $pidsInPeriod = DB::table('omnicare_sales_detail')
                ->whereBetween('trx_date', [$start->toDateString(), $end->toDateString()])
                ->whereIn('place_id', $scope)
                ->whereNotNull('p_id')->where('p_id', '!=', '')
                ->distinct()->pluck('p_id');

            if ($pidsInPeriod->isNotEmpty()) {
                $repeat = (int) DB::table('omnicare_sales_detail')
                    ->whereIn('p_id', $pidsInPeriod)
                    ->whereIn('place_id', $scope)
                    ->whereBetween('trx_date', [$lookbackFrom, $start->copy()->subDay()->toDateString()])
                    ->distinct()->count('p_id');
            }
        }

        return (object) [
            'total'               => $total,
            'repeat'              => $repeat,
            'repeatRate'          => $total > 0 ? round($repeat / $total * 100, 1) : 0,
            'avgVisitsPerPatient' => $total > 0 ? round($visits / $total, 2) : 0,
        ];
    }

    /**
     * Pasien per cabang (untuk modal Patient Insights drill).
     * @return Collection<{branchId, name, total, baru, returning, repeatRate}>
     */
    private function buildPatientByBranch(Carbon $start, Carbon $end, Collection $branches): Collection
    {
        $startStr = $start->toDateString();
        $endStr   = $end->toDateString();
        $ym       = $start->format('ym');
        $ymEnd    = $end->format('ym');

        return $branches->map(function ($b) use ($startStr, $endStr, $ym, $ymEnd, $start) {
            $row = DB::table('omnicare_sales_detail')
                ->where('place_id', $b->omnicare_place_id)
                ->whereBetween('trx_date', [$startStr, $endStr])
                ->whereNotNull('p_id')->where('p_id', '!=', '')
                ->selectRaw(
                    "COUNT(DISTINCT p_id) AS total,
                     COUNT(DISTINCT CASE WHEN REGEXP_SUBSTR(p_id, '[0-9]{4}') BETWEEN ? AND ? THEN p_id END) AS baru",
                    [$ym, $ymEnd]
                )->first();

            $total = (int) ($row->total ?? 0);
            $baru  = (int) ($row->baru ?? 0);

            // repeat rate per cabang (lookback 12 bulan)
            $repeat = 0;
            if ($total > 0) {
                $pids = DB::table('omnicare_sales_detail')
                    ->where('place_id', $b->omnicare_place_id)
                    ->whereBetween('trx_date', [$startStr, $endStr])
                    ->whereNotNull('p_id')->where('p_id', '!=', '')
                    ->distinct()->pluck('p_id');
                if ($pids->isNotEmpty()) {
                    $repeat = (int) DB::table('omnicare_sales_detail')
                        ->where('place_id', $b->omnicare_place_id)
                        ->whereIn('p_id', $pids)
                        ->whereBetween('trx_date', [
                            $start->copy()->subMonths(12)->toDateString(),
                            $start->copy()->subDay()->toDateString(),
                        ])
                        ->distinct()->count('p_id');
                }
            }

            return (object) [
                'branchId'   => $b->id,
                'name'       => $b->name,
                'total'      => $total,
                'baru'       => $baru,
                'returning'  => max(0, $total - $baru),
                'repeatRate' => $total > 0 ? round($repeat / $total * 100, 1) : 0,
            ];
        });
    }

    /**
     * Branch comparison — SELALU semua cabang (tidak terpengaruh filter ?place=),
     * karena tujuannya membandingkan. Gabung revenue (actual all-branch) + booking
     * + target% + patient stats + YoY delta per cabang.
     * @return Collection<{name, branchId, revenue, booking, targetPct, patients, baru, returning, repeatRate, yoyDelta}>
     */
    private function buildBranchComparison(
        Carbon $start, Carbon $end, Collection $branches,
        Collection $actualByPlace, Collection $bookingByBranch, Collection $targets, Collection $patientByBranch
    ): Collection {
        // Revenue periode sama tahun lalu per place (untuk YoY delta per cabang)
        $prevStart = $start->copy()->subYear();
        $prevEnd   = $end->copy()->subYear();
        $places    = $branches->pluck('omnicare_place_id')->filter()->all();
        $prevByPlace = empty($places) ? collect() : DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->whereIn('place_id', $places)
            ->selectRaw('place_id, ' . self::NET_EXPR . ' AS net')
            ->groupBy('place_id')
            ->pluck('net', 'place_id');

        return $branches->map(function ($b) use ($actualByPlace, $bookingByBranch, $targets, $patientByBranch, $prevByPlace) {
            $revenue = (float) ($actualByPlace[$b->omnicare_place_id] ?? 0);
            $booking = (float) ($bookingByBranch[$b->id] ?? 0);
            $target  = (float) ($targets[$b->id] ?? 0);
            $prevRev = (float) ($prevByPlace[$b->omnicare_place_id] ?? 0);
            $p       = $patientByBranch->firstWhere('branchId', $b->id);

            return (object) [
                'name'       => $b->name,
                'branchId'   => $b->id,
                'revenue'    => $revenue,
                'booking'    => $booking,
                'targetPct'  => $target > 0 ? round($revenue / $target * 100, 1) : null,
                'patients'   => $p->total ?? 0,
                'baru'       => $p->baru ?? 0,
                'returning'  => $p->returning ?? 0,
                'repeatRate' => $p->repeatRate ?? 0,
                'yoyDelta'   => $prevRev > 0 ? round(($revenue - $prevRev) / $prevRev * 100, 1) : null,
            ];
        })->sortByDesc('revenue')->values();
    }

    /**
     * Trend pasien 12 bulan trailing (jumlah distinct p_id per bulan).
     * @return array{labels:array, total:array, baru:array}
     */
    private function buildPatientTrend(Carbon $end, ?string $placeId, array $places): array
    {
        $anchor = $end->copy();
        $labels = $total = $baru = [];
        for ($i = 11; $i >= 0; $i--) {
            $d          = $anchor->copy()->subMonths($i);
            $monthStart = $d->copy()->startOfMonth();
            $monthEnd   = $d->copy()->endOfMonth();
            $ym         = $d->format('ym');

            $labels[] = $d->format('M Y');
            $q        = DB::table('omnicare_sales_detail')
                ->whereBetween('trx_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->whereNotNull('p_id')->where('p_id', '!=', '');
            $placeId ? $q->where('place_id', $placeId) : $q->whereIn('place_id', $places);
            $row = $q->selectRaw(
                "COUNT(DISTINCT p_id) AS total,
                 COUNT(DISTINCT CASE WHEN REGEXP_SUBSTR(p_id, '[0-9]{4}') = ? THEN p_id END) AS baru",
                [$ym]
            )->first();

            $total[] = (int) ($row->total ?? 0);
            $baru[]  = (int) ($row->baru ?? 0);
        }

        return compact('labels', 'total', 'baru');
    }

    /**
     * Top dokter by jumlah pasien unik (untuk modal Patient Insights).
     * @return Collection<{id, name, patients, visits, net}>
     */
    private function buildTopDoctorsByPatient(Carbon $start, Carbon $end, ?string $placeId, array $places, int $limit = 5): Collection
    {
        $q = DB::table('omnicare_sales_detail as s')
            ->leftJoin('raw_doctor as d', 'd.omnicare_doc_id', '=', 's.doc_reff_id')
            ->whereBetween('s.trx_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('s.doc_reff_id')->where('s.doc_reff_id', '!=', '')
            ->whereNotNull('s.p_id')->where('s.p_id', '!=', '');
        $placeId ? $q->where('s.place_id', $placeId) : $q->whereIn('s.place_id', $places);

        return $q->selectRaw("
                s.doc_reff_id AS id,
                COALESCE(MAX(d.name), MAX(s.doctor), '?') AS name,
                COUNT(DISTINCT s.p_id) AS patients,
                COUNT(DISTINCT s.encounter_id) AS visits,
                COALESCE(SUM(CASE WHEN s.item_type <> 'V' AND s.item_name NOT LIKE 'Service Charge%' THEN s.net_sell_price ELSE 0 END), 0) AS net
            ")
            ->groupBy('s.doc_reff_id')
            ->orderByDesc('patients')
            ->limit($limit)
            ->get();
    }

    /**
     * Cross-branch flow: pasien yang transaksi di >1 cabang dalam rentang.
     */
    private function buildCrossBranchFlow(Carbon $start, Carbon $end, array $places): object
    {
        if (empty($places)) {
            return (object) ['total' => 0, 'cross' => 0, 'crossRate' => 0];
        }

        $counts = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('place_id', $places)
            ->whereNotNull('p_id')->where('p_id', '!=', '')
            ->selectRaw('p_id, COUNT(DISTINCT place_id) AS branch_count')
            ->groupBy('p_id')
            ->get();

        $total = $counts->count();
        $cross = $counts->where('branch_count', '>', 1)->count();

        return (object) [
            'total'     => $total,
            'cross'     => $cross,
            'crossRate' => $total > 0 ? round($cross / $total * 100, 1) : 0,
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    // Layer 3 — Drill trends (batched 1-query each)
    // ────────────────────────────────────────────────────────────────────

    /** 12-bulan revenue + qty trend untuk daftar item_code. 1 query total. */
    private function buildTreatmentTrends(array $itemCodes, Carbon $end, array $places, int $monthsBack = 12): array
    {
        if (empty($itemCodes) || empty($places)) {
            return [];
        }
        $anchor = $end->copy();
        $from   = $anchor->copy()->subMonths($monthsBack - 1)->startOfMonth();
        $to     = $anchor->copy()->endOfMonth();

        $rows = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('item_code', $itemCodes)
            ->whereIn('place_id', $places)
            ->where('item_type', 'P')
            ->where('item_name', 'NOT LIKE', 'Service Charge%')
            ->selectRaw("item_code, DATE_FORMAT(trx_date, '%Y-%m') AS ym,
                COALESCE(SUM(net_sell_price), 0) AS net,
                COALESCE(SUM(qty_sold), 0) AS qty")
            ->groupBy('item_code', 'ym')
            ->get();

        return $this->pivotTrend($rows, 'item_code', $itemCodes, $anchor, $monthsBack);
    }

    /** 12-bulan revenue + visit trend untuk daftar doc_reff_id. 1 query total. */
    private function buildDoctorTrends(array $doctorIds, Carbon $end, array $places, int $monthsBack = 12): array
    {
        if (empty($doctorIds) || empty($places)) {
            return [];
        }
        $anchor = $end->copy();
        $from   = $anchor->copy()->subMonths($monthsBack - 1)->startOfMonth();
        $to     = $anchor->copy()->endOfMonth();

        $rows = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('doc_reff_id', $doctorIds)
            ->whereIn('place_id', $places)
            ->selectRaw("doc_reff_id, DATE_FORMAT(trx_date, '%Y-%m') AS ym,
                COALESCE(SUM(CASE WHEN item_type <> 'V' AND item_name NOT LIKE 'Service Charge%' THEN net_sell_price ELSE 0 END), 0) AS net,
                COUNT(DISTINCT encounter_id) AS qty")
            ->groupBy('doc_reff_id', 'ym')
            ->get();

        return $this->pivotTrend($rows, 'doc_reff_id', $doctorIds, $anchor, $monthsBack);
    }

    /**
     * Pivot rows[key, ym, net, qty] → [key => {labels, revenue, qty}] dengan
     * months trailing $monthsBack s/d $anchor.
     */
    private function pivotTrend($rows, string $keyCol, array $keys, Carbon $anchor, int $monthsBack): array
    {
        $months = [];
        for ($i = $monthsBack - 1; $i >= 0; $i--) {
            $months[] = $anchor->copy()->subMonths($i);
        }
        $labels = array_map(fn ($m) => $m->format('M Y'), $months);
        $ymKeys = array_map(fn ($m) => $m->format('Y-m'), $months);

        $byKey = $rows->groupBy($keyCol);
        $out   = [];
        foreach ($keys as $k) {
            $revenue = $qty = [];
            $byYm    = ($byKey->get($k) ?? collect())->keyBy('ym');
            foreach ($ymKeys as $ym) {
                $r         = $byYm->get($ym);
                $revenue[] = (float) (optional($r)->net ?? 0);
                $qty[]     = (float) (optional($r)->qty ?? 0);
            }
            $out[$k] = compact('labels', 'revenue', 'qty');
        }

        return $out;
    }

    /**
     * Compose hero data dari subset yang sudah dihitung. YoY delta diambil dari
     * element terakhir $yoy['growth'] (tahun terbaru vs tahun sebelumnya).
     */
    private function buildHero(object $totals, array $yoy, object $patients, ?object $topBranch, int $txCount): object
    {
        $n        = count($yoy['years']);
        $yoyDelta = $n > 1 ? ($yoy['growth'][$n - 1] ?? null) : null;
        $prevYear = $n > 1 ? $yoy['years'][$n - 2] : null;

        return (object) [
            'revenue'       => (float) $totals->actual,
            'yoyDelta'      => $yoyDelta,
            'yoyLabel'      => $prevYear ? 'vs ' . $prevYear : 'baseline',
            'targetPct'     => $totals->pct,
            'patientsTotal' => $patients->total,
            'txCount'       => $txCount,
            'topBranchName' => $topBranch->name ?? null,
            'topBranchRev'  => $topBranch->net ?? 0,
        ];
    }
}
