<?php

namespace App\Http\Controllers;

use App\Models\RawBranch;
use App\Models\RawCusReservation;
use App\Models\RawDocService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExecutiveDashboardController extends Controller
{
    public function index()
    {
        $year  = (int) request('year', Carbon::now()->year);
        $month = (int) request('month', Carbon::now()->month);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        $branches = RawBranch::orderBy('name')->get();

        // ── Revenue per Branch (bulan ini) ──
        $revenueByBranch = RawCusReservation::select(
                'raw_branch_id',
                DB::raw('COUNT(*) as total_reservasi_count'),
                DB::raw('SUM(total_reservasi) as total_revenue')
            )
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', RawCusReservation::STATUS_CANCELLED)
            ->groupBy('raw_branch_id')
            ->get()
            ->keyBy('raw_branch_id');

        // ── Target per Branch (bulan ini) ──
        $targets = DB::table('raw_branch_target')
            ->where('tahun', $year)
            ->where('bulan', $month)
            ->get()
            ->keyBy('cabang_id');

        // Build combined data
        $branchData = $branches->map(function ($branch) use ($revenueByBranch, $targets) {
            $rev = $revenueByBranch->get($branch->id);
            $target = $targets->get($branch->id);
            $actual = $rev->total_revenue ?? 0;
            $targetNominal = $target->nominal ?? 0;
            $pct = $targetNominal > 0 ? round(($actual / $targetNominal) * 100, 1) : 0;

            return [
                'name'    => $branch->name,
                'count'   => $rev->total_reservasi_count ?? 0,
                'revenue' => $actual,
                'target'  => $targetNominal,
                'pct'     => $pct,
            ];
        });

        // ── Grand Totals ──
        $totalRevenue = $branchData->sum('revenue');
        $totalTarget  = $branchData->sum('target');
        $totalCount   = $branchData->sum('count');
        $totalPct     = $totalTarget > 0 ? round(($totalRevenue / $totalTarget) * 100, 1) : 0;

        // ── Funnel: Stat_New → Stat_Leads → Stat_Rsv (bulan ini) ──
        // Stat_New/Leads = level contact (funnel nomor). Stat_Rsv ditulis
        // ganda (contact-level + customer-level) sejak model 1-nomor-banyak-
        // pasien; hitung Stat_Rsv per raw_customer_id saja supaya tidak
        // menggelembung ~2x dibanding new/leads.
        $funnel = DB::table('raw_cus_journey')
            ->select('journey_label', DB::raw(
                "COUNT(DISTINCT CASE
                    WHEN journey_label = 'Stat_Rsv' THEN raw_customer_id
                    ELSE COALESCE(raw_customer_id, raw_contact_id)
                END) as total"
            ))
            ->whereIn('journey_label', ['Stat_New', 'Stat_Leads', 'Stat_Rsv'])
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('journey_label')
            ->pluck('total', 'journey_label');

        $funnelData = [
            'new'   => $funnel->get('Stat_New', 0),
            'leads' => $funnel->get('Stat_Leads', 0),
            'rsv'   => $funnel->get('Stat_Rsv', 0),
        ];

        // ── Top 10 Layanan (bulan ini) ──
        $topServices = DB::table('raw_cus_res_service')
            ->join('raw_cus_reservation', 'raw_cus_res_service.raw_cus_reservation_id', '=', 'raw_cus_reservation.id')
            ->join('raw_doc_service', 'raw_cus_res_service.raw_doc_service_id', '=', 'raw_doc_service.id')
            ->select(
                'raw_doc_service.name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(raw_doc_service.price) as revenue')
            )
            ->whereBetween('raw_cus_reservation.date', [$startOfMonth, $endOfMonth])
            ->where('raw_cus_reservation.status', '!=', 'cancelled')
            ->groupBy('raw_doc_service.id', 'raw_doc_service.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // ── Top 5 Dokter (bulan ini) ──
        $topDoctors = RawCusReservation::select(
                'raw_doctor_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(total_reservasi) as revenue')
            )
            ->with('doctor:id,name')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', 'cancelled')
            ->groupBy('raw_doctor_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // ── Monthly Revenue Trend (12 bulan terakhir) ──
        $trendStart = Carbon::create($year, $month, 1)->subMonths(11)->startOfDay();
        $monthlyTrend = RawCusReservation::select(
                DB::raw('YEAR(date) as y'),
                DB::raw('MONTH(date) as m'),
                DB::raw('SUM(total_reservasi) as revenue'),
                DB::raw('COUNT(*) as cnt')
            )
            ->where('date', '>=', $trendStart)
            ->where('date', '<=', $endOfMonth)
            ->where('status', '!=', 'cancelled')
            ->groupBy('y', 'm')
            ->orderBy('y')
            ->orderBy('m')
            ->get();

        $trendLabels = [];
        $trendRevenue = [];
        $trendCount = [];
        for ($i = 11; $i >= 0; $i--) {
            $d = Carbon::create($year, $month, 1)->subMonths($i);
            $trendLabels[] = $d->format('M Y');
            $found = $monthlyTrend->first(fn($r) => $r->y == $d->year && $r->m == $d->month);
            $trendRevenue[] = $found ? (float) $found->revenue : 0;
            $trendCount[] = $found ? $found->cnt : 0;
        }

        // ── Commission summary ──
        $commissionSummary = DB::table('raw_commission')
            ->join('raw_cus_reservation', 'raw_commission.raw_cus_reservation_id', '=', 'raw_cus_reservation.id')
            ->select(
                DB::raw("SUM(CASE WHEN raw_commission.status = 'pending' THEN raw_commission.amount ELSE 0 END) as pending_total"),
                DB::raw("SUM(CASE WHEN raw_commission.status = 'approved' THEN raw_commission.amount ELSE 0 END) as approved_total"),
                DB::raw("SUM(CASE WHEN raw_commission.status = 'paid' THEN raw_commission.amount ELSE 0 END) as paid_total")
            )
            ->whereBetween('raw_cus_reservation.date', [$startOfMonth, $endOfMonth])
            ->first();

        // ── Status distribution (bulan ini) ──
        $statusDist = RawCusReservation::select('status', DB::raw('COUNT(*) as total'))
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->groupBy('status')
            ->get();

        $statusOptions = RawCusReservation::statusOptions();

        // ── OMNICARE DATA (live mirror) ──
        $omnicare = $this->buildOmnicareSection($startOfMonth, $endOfMonth);

        return view('dashboard.executive', compact(
            'year', 'month', 'branches',
            'branchData', 'totalRevenue', 'totalTarget', 'totalCount', 'totalPct',
            'funnelData', 'topServices', 'topDoctors',
            'trendLabels', 'trendRevenue', 'trendCount',
            'commissionSummary', 'statusDist', 'statusOptions',
            'omnicare'
        ));
    }

    /**
     * Prepare Omnicare mirror-table widgets for the executive dashboard.
     * Date range follows the user-selected month/year filter.
     */
    private function buildOmnicareSection(Carbon $startOfMonth, Carbon $endOfMonth): array
    {
        $startYmd = $startOfMonth->format('Y-m-d');
        // Cap end at today (no future projection for in-progress month)
        $endYmd = min($endOfMonth->format('Y-m-d'), Carbon::now()->format('Y-m-d'));

        // ── Summary cards ──
        $summary = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$startYmd, $endYmd])
            ->selectRaw('
                COALESCE(SUM(net_sell_price), 0) AS gross_revenue,
                COALESCE(SUM(net_profit), 0)    AS net_profit,
                COALESCE(SUM(discount), 0)      AS total_discount,
                COUNT(*)                         AS total_lines,
                COUNT(DISTINCT encounter_id)     AS total_visits,
                COUNT(DISTINCT p_id)             AS unique_patients
            ')
            ->first();

        // ── Per-branch breakdown ──
        // Left-join omnicare_location agar branch tanpa sales bulan ini tetap muncul (revenue 0).
        $perBranch = DB::table('omnicare_location as ol')
            ->leftJoin('omnicare_sales_detail as os', function ($j) use ($startYmd, $endYmd) {
                $j->on('os.place_id', '=', 'ol.id')
                  ->whereBetween('os.trx_date', [$startYmd, $endYmd]);
            })
            ->whereIn('ol.id', config('omnicare.active_branches', []))
            ->selectRaw('
                ol.id AS place_id,
                ol.name AS branch_name,
                ol.is_parent,
                COALESCE(SUM(os.net_sell_price), 0) AS revenue,
                COALESCE(SUM(os.net_profit), 0)    AS profit,
                COUNT(os.trx_id)                    AS transactions,
                COUNT(DISTINCT os.encounter_id)     AS visits,
                MAX(os.trx_date)                    AS last_trx_date
            ')
            ->groupBy('ol.id', 'ol.name', 'ol.is_parent')
            ->orderByDesc('revenue')
            ->get();

        // Add freshness indicator: stale kalau last sales >= 7 hari yang lalu
        $today = Carbon::now()->startOfDay();
        $perBranch->transform(function ($row) use ($today) {
            $daysSinceLast = $row->last_trx_date
                ? $today->diffInDays(Carbon::parse($row->last_trx_date))
                : null;
            $row->days_since_last = $daysSinceLast;
            $row->status = $daysSinceLast === null ? 'no_data'
                : ($daysSinceLast >= 7 ? 'stale'
                : ($daysSinceLast >= 3 ? 'warning' : 'active'));
            return $row;
        });

        // ── Top 10 procedures ──
        $topProcedures = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$startYmd, $endYmd])
            ->where('item_type', 'P')
            ->selectRaw('
                item_code,
                item_name,
                SUM(qty_sold) AS qty,
                SUM(net_sell_price) AS revenue
            ')
            ->groupBy('item_code', 'item_name')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        // ── Top 10 doctors by Omnicare revenue ──
        $topDoctors = DB::table('omnicare_sales_detail as os')
            ->leftJoin('raw_doctor as rd', 'rd.omnicare_doc_id', '=', 'os.doc_reff_id')
            ->whereBetween('os.trx_date', [$startYmd, $endYmd])
            ->whereNotNull('os.doc_reff_id')
            ->where('os.doc_reff_id', '!=', '')
            ->selectRaw('
                os.doc_reff_id,
                COALESCE(rd.name, os.doctor) AS display_name,
                rd.id AS sweettreats_doctor_id,
                SUM(os.net_sell_price) AS revenue,
                COUNT(DISTINCT os.encounter_id) AS visits,
                COUNT(*) AS line_items
            ')
            ->groupBy('os.doc_reff_id', 'display_name', 'rd.id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // ── Daily revenue trend (last 30 days from end date) ──
        $trendStart = Carbon::parse($endYmd)->subDays(29);
        $dailyRows = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$trendStart->format('Y-m-d'), $endYmd])
            ->selectRaw('trx_date, SUM(net_sell_price) AS revenue, COUNT(DISTINCT encounter_id) AS visits')
            ->groupBy('trx_date')
            ->orderBy('trx_date')
            ->get()
            ->keyBy(fn($r) => Carbon::parse($r->trx_date)->format('Y-m-d'));

        $trendLabels = [];
        $trendRevenue = [];
        $trendVisits = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = Carbon::parse($endYmd)->subDays($i);
            $key = $d->format('Y-m-d');
            $trendLabels[] = $d->format('d M');
            $trendRevenue[] = (float) ($dailyRows->get($key)->revenue ?? 0);
            $trendVisits[] = (int) ($dailyRows->get($key)->visits ?? 0);
        }

        // ── Payment status distribution ──
        // Note: `lines` reserved word di MariaDB — pakai `line_count`.
        $paymentStatus = DB::table('omnicare_sales_detail')
            ->whereBetween('trx_date', [$startYmd, $endYmd])
            ->selectRaw('payment_status, COUNT(*) AS line_count, SUM(net_sell_price) AS revenue')
            ->groupBy('payment_status')
            ->get();

        // ── Last sync info ──
        $lastSync = DB::table('omnicare_sync_log')
            ->where('endpoint', '/get/sales_detail')
            ->orderByDesc('id')
            ->first();

        $syncStats = DB::table('omnicare_sync_log')
            ->selectRaw('
                MAX(finished_at) AS last_run,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) AS success_count,
                SUM(CASE WHEN status = "error" THEN 1 ELSE 0 END) AS error_count
            ')
            ->where('started_at', '>=', Carbon::now()->subDays(1))
            ->first();

        return [
            'summary'        => $summary,
            'perBranch'      => $perBranch,
            'topProcedures'  => $topProcedures,
            'topDoctors'     => $topDoctors,
            'trendLabels'    => $trendLabels,
            'trendRevenue'   => $trendRevenue,
            'trendVisits'    => $trendVisits,
            'paymentStatus'  => $paymentStatus,
            'lastSync'       => $lastSync,
            'syncStats'      => $syncStats,
            'rangeStart'     => $startYmd,
            'rangeEnd'       => $endYmd,
        ];
    }
}
