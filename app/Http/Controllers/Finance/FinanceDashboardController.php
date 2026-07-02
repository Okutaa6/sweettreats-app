<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FundSource;
use App\Models\PettyCashPeriod;
use App\Models\PettyCashTransaction;
use App\Models\RawBranch;
use App\Models\TopupRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * FinanceDashboardController
 *
 * Halaman monitoring petty cash untuk role Finance:
 *   - Saldo & cash out lintas cabang bulan berjalan (cross-branch aggregate)
 *   - Antrian pengajuan top-up dari Sales (status pending)
 *   - Grid breakdown per cabang
 *   - Audit feed: 20 request yang sudah di-review terakhir
 *
 * Akses: role Finance, atau admin yang punya canSeeAllBranches().
 */
class FinanceDashboardController extends Controller
{
    private function guard(): void
    {
        // Akses dashboard di-gate via group_access (AuthGuard middleware).
        // Tambahan sanity check di sini: user harus admin-level (Super Admin /
        // Admin General / Admin Backup) supaya tidak bisa diakses Sales walau
        // someone iseng kasih menu access ke role lain.
        if (! canSeeAllBranches()) {
            abort(403, 'Halaman ini hanya untuk admin.');
        }
    }

    public function index(Request $request)
    {
        $this->guard();

        $today = Carbon::today();
        $year  = (int) $request->get('year',  $today->year);
        $month = (int) $request->get('month', $today->month);
        if ($year < 2000 || $year > 2100) $year = (int) $today->year;
        if ($month < 1   || $month > 12)  $month = (int) $today->month;

        $branches    = RawBranch::orderBy('name')->get();
        $fundSources = FundSource::active()->orderBy('sort_order')->orderBy('name')->get();

        // Loop per cabang → ensurePeriod bulan berjalan → hitung opening +
        // net cash flow. Tidak ada scope cross-branch built-in, jadi
        // manual loop. Skala saat ini wajar (puluhan cabang).
        $branchSummaries  = [];
        $totalOpening     = 0.0;
        $totalSaldo       = 0.0;
        $totalCashIn      = 0.0;
        $totalCashOut     = 0.0;
        $latestActivityAt = null;

        foreach ($branches as $br) {
            $period = PettyCashPeriod::ensurePeriod(
                $year,
                $month,
                (float) ($br->petty_cash_default_float ?? 1500000),
                (string) $br->id,
            );

            // Opening dari snapshot per-source (fallback ke scalar bila legacy)
            $openingPerSource = $period->openingPerSourceResolved();
            $opening = ! empty($openingPerSource)
                ? array_sum($openingPerSource)
                : (float) $period->opening_balance;

            // Cash in / out periode aktif (active = non-soft-deleted)
            $rows = PettyCashTransaction::active()
                ->forPeriod($period->id)
                ->get(['direction', 'amount', 'is_topup', 'is_transfer', 'trx_date']);

            $cashIn  = 0.0;
            $cashOut = 0.0;
            $lastActivity = null;
            foreach ($rows as $r) {
                if ($r->is_transfer) continue; // transfer internal — netral
                $amt = (float) $r->amount;
                if ($r->direction === PettyCashTransaction::DIR_IN) {
                    if ($r->is_topup) $cashIn += $amt;
                } else {
                    $cashOut += $amt;
                }
                $d = $r->trx_date instanceof Carbon ? $r->trx_date : Carbon::parse($r->trx_date);
                if ($lastActivity === null || $d->gt($lastActivity)) $lastActivity = $d;
            }

            $closing = $opening + ($cashIn - $cashOut);

            $branchSummaries[] = (object) [
                'id'             => $br->id,
                'name'           => $br->name ?: '(tanpa nama)',
                'opening'        => $opening,
                'cash_in'        => $cashIn,
                'cash_out'       => $cashOut,
                'closing'        => $closing,
                'last_activity'  => $lastActivity,
                'period_id'      => $period->id,
                'period_closed'  => (bool) $period->is_closed,
            ];

            $totalOpening += $opening;
            $totalSaldo   += $closing;
            $totalCashIn  += $cashIn;
            $totalCashOut += $cashOut;

            if ($lastActivity !== null) {
                if ($latestActivityAt === null || $lastActivity->gt($latestActivityAt)) {
                    $latestActivityAt = $lastActivity;
                }
            }
        }

        // ─── Arus kas & MoM ───
        // netFlow bulan ini = cash in − cash out (sudah exclude transfer internal)
        // Opening total dipakai sebagai proxy "saldo akhir bulan lalu" karena
        // periode petty cash carry-forward closing → opening bulan berikutnya.
        $netFlow         = $totalCashIn - $totalCashOut;
        $prevTotalSaldo  = $totalOpening;
        $saldoMoMDelta   = $totalSaldo - $prevTotalSaldo;
        $saldoMoMPct     = $prevTotalSaldo > 0
            ? round(($saldoMoMDelta / $prevTotalSaldo) * 100, 1)
            : null;

        // Antrian request — pending dulu, eager load relasinya
        $pendingRequests = TopupRequest::forFinanceQueue()
            ->with(['branch', 'requester', 'fundSource'])
            ->get();

        // Audit feed — 20 request terakhir yang sudah di-review (approved/rejected)
        $recentReviewed = TopupRequest::where('status', '!=', TopupRequest::STATUS_PENDING)
            ->with(['branch', 'requester', 'reviewer', 'fundSource'])
            ->orderByDesc('reviewed_at')
            ->limit(20)
            ->get();

        $bulanLabel = [
            1 => 'Januari',  2 => 'Februari', 3 => 'Maret',
            4 => 'April',    5 => 'Mei',      6 => 'Juni',
            7 => 'Juli',     8 => 'Agustus',  9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $monthName     = $bulanLabel[$month] ?? '-';
        $prevDate      = Carbon::create($year, $month, 1)->subMonth();
        $prevMonthName = $bulanLabel[$prevDate->month] ?? '-';

        // Cut-off "Data per ..." — pakai timestamp aktivitas terakhir lintas cabang
        // bila ada, fallback ke now() supaya finance tahu data ini freshness-nya.
        $generatedAt = Carbon::now();
        $cutoffAt    = $latestActivityAt ?? $generatedAt;

        // ─── Distribusi saldo untuk progress bar di hero card ───
        // Tampilkan semua cabang dengan saldo > 0 sebagai chip (cap di 4 supaya
        // legend tidak meluap). Cabang dengan saldo <= 0 dihitung sebagai "idle",
        // cabang non-idle yang melebihi cap masuk ke "X cabang lain".
        $sorted    = collect($branchSummaries)->sortByDesc('closing')->values();
        $active    = $sorted->filter(fn ($b) => $b->closing > 0)->values();
        $idleCount = $sorted->count() - $active->count();

        $maxChips  = 4;
        $shown     = $active->take($maxChips);
        $hidden    = $active->slice($maxChips);

        $distribution = collect();
        foreach ($shown as $b) {
            $pct = $totalSaldo > 0 ? round(($b->closing / $totalSaldo) * 100) : 0;
            $distribution->push((object) ['name' => $b->name, 'pct' => $pct, 'color' => null]);
        }
        $distributionRestCount = $hidden->count();
        $distributionRestPct   = $totalSaldo > 0
            ? max(0, 100 - $distribution->sum('pct'))
            : 0;

        // ─── Berapa cabang punya pengeluaran bulan ini ───
        $branchesWithCashOut = collect($branchSummaries)->filter(fn ($b) => $b->cash_out > 0)->count();

        return view('finance.pettycash.index', compact(
            'branches', 'fundSources',
            'branchSummaries', 'pendingRequests', 'recentReviewed',
            'totalOpening', 'totalSaldo', 'totalCashIn', 'totalCashOut', 'netFlow',
            'prevTotalSaldo', 'saldoMoMDelta', 'saldoMoMPct', 'prevMonthName',
            'year', 'month', 'monthName', 'bulanLabel',
            'cutoffAt', 'generatedAt',
            'distribution', 'distributionRestCount', 'distributionRestPct',
            'idleCount', 'branchesWithCashOut'
        ));
    }
}
