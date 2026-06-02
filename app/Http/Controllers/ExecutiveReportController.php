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
 * Akses: admin saja (Super Admin / Admin General) — belum ada role executive
 * khusus. Gate di controller karena AuthGuard hanya cek segment-1 'report'.
 */
class ExecutiveReportController extends Controller
{
    private const ADMIN_GROUPS = ['Super Admin', 'Admin General'];

    /**
     * Net revenue Omnicare: exclude Voucher Tunai (item_type 'V') & Service
     * Charge — konsisten dengan definisi "Net" di SalesReportController.
     */
    private const NET_EXPR = "COALESCE(SUM(CASE WHEN item_type <> 'V' AND item_name NOT LIKE 'Service Charge%' THEN net_sell_price ELSE 0 END),0)";

    public function index()
    {
        abort_unless(
            in_array(session('group_name'), self::ADMIN_GROUPS, true),
            403,
            'Halaman ini khusus admin.'
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

        // Preset cepat + label periode
        $presets    = $this->presets($today);
        $spansMonth = $start->format('Y-m') !== $end->format('Y-m');

        return view('report.executive', array_merge(
            compact('branches', 'filterId', 'rows', 'totals', 'patients', 'daily', 'monthly', 'yoy', 'presets', 'spansMonth'),
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
            $start = $today->copy()->startOfMonth();
            $end   = $today->copy()->endOfMonth();
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

        $actualQ = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$from->toDateString(), $to->toDateString()]);
        $placeId ? $actualQ->where('place_id', $placeId) : $actualQ->whereIn('place_id', $places);
        $actual = $actualQ->selectRaw(
                "DATE_FORMAT(trx_date, '%Y-%m') AS ym, " . self::NET_EXPR . " AS net,
                 COUNT(DISTINCT p_id) AS patients"
            )
            ->groupBy('ym')->get()->keyBy('ym');

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
            $revenue[]    = (float) (optional($actual->get($ym))->net ?? 0);
            $patients[]   = (int) (optional($actual->get($ym))->patients ?? 0);
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

        foreach ($offsets as $k) {
            $s = $start->copy()->subYears($k);
            $e = $end->copy()->subYears($k);

            $years[]  = $e->year;
            $labels[] = (string) $e->year;

            // Ringkasan (scoped filter)
            $agg = DB::table('omnicare_sales_detail')
                ->whereBetween('trx_date', [$s->toDateString(), $e->toDateString()])
                ->whereIn('place_id', $places)
                ->selectRaw(self::NET_EXPR . ' AS net, COUNT(DISTINCT p_id) AS patients')
                ->first();
            $revenue[]  = (float) ($agg->net ?? 0);
            $patients[] = (int) ($agg->patients ?? 0);

            // Matriks per cabang
            $perPlace = DB::table('omnicare_sales_detail')
                ->whereBetween('trx_date', [$s->toDateString(), $e->toDateString()])
                ->whereIn('place_id', array_keys($matrixAccum) ?: ['_none_'])
                ->selectRaw('place_id, ' . self::NET_EXPR . ' AS net')
                ->groupBy('place_id')->pluck('net', 'place_id');
            foreach ($matrixAccum as $pid => &$entry) {
                $entry['values'][] = (float) ($perPlace[$pid] ?? 0);
            }
            unset($entry);
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
}
