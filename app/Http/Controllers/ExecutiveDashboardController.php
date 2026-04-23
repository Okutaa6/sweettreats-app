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
        $funnel = DB::table('raw_cus_journey')
            ->select('journey_label', DB::raw('COUNT(DISTINCT COALESCE(raw_customer_id, raw_contact_id)) as total'))
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

        return view('dashboard.executive', compact(
            'year', 'month', 'branches',
            'branchData', 'totalRevenue', 'totalTarget', 'totalCount', 'totalPct',
            'funnelData', 'topServices', 'topDoctors',
            'trendLabels', 'trendRevenue', 'trendCount',
            'commissionSummary', 'statusDist', 'statusOptions'
        ));
    }
}
