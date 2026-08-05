<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FundSource;
use App\Models\PettyCashPeriod;
use App\Models\PettyCashTransaction;
use App\Models\RawBranch;
use App\Models\TopupRequest;
use App\Models\Users;
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

    /**
     * GET /finance/pettycash/branch/{branchId}/riwayat
     *
     * Riwayat transaksi 1 cabang untuk 1 bulan — read-only, untuk keperluan
     * monitoring/audit oleh Finance. Menampilkan SELURUH transaksi periode
     * (termasuk yang sudah di-soft-delete, karena deleted_at sengaja
     * dipertahankan sebagai jejak) + info "siapa & kapan" (created_by /
     * updated_by / deleted). Saldo berjalan hanya dihitung dari baris aktif.
     */
    public function branchHistory(Request $request, string $branchId)
    {
        $this->guard();

        $branch = RawBranch::find($branchId);
        if (! $branch) {
            abort(404, 'Cabang tidak ditemukan.');
        }

        $today = Carbon::today();
        $year  = (int) $request->get('year',  $today->year);
        $month = (int) $request->get('month', $today->month);
        if ($year < 2000 || $year > 2100) $year = (int) $today->year;
        if ($month < 1   || $month > 12)  $month = (int) $today->month;

        $period = PettyCashPeriod::ensurePeriod(
            $year,
            $month,
            (float) ($branch->petty_cash_default_float ?? 1500000),
            (string) $branch->id,
        );

        // Opening periode (sum snapshot per-source, fallback scalar legacy).
        $openingPerSource = $period->openingPerSourceResolved();
        $opening = ! empty($openingPerSource)
            ? array_sum($openingPerSource)
            : (float) $period->opening_balance;

        // SEMUA transaksi periode (active() TIDAK dipakai → ikut yang deleted).
        $transactions = PettyCashTransaction::forPeriod($period->id)
            ->with('fundSource')
            ->orderBy('trx_date')
            ->orderBy('id')
            ->get();

        // Resolve nama user (created_by / updated_by) dalam 1 query.
        $userIds = $transactions
            ->flatMap(fn ($t) => [$t->created_by, $t->updated_by])
            ->filter()
            ->unique()
            ->values();
        $userNames = $userIds->isNotEmpty()
            ? Users::whereIn('id', $userIds)->pluck('nama', 'id')
            : collect();

        // Bangun baris + saldo berjalan (hanya baris aktif menggerakkan saldo).
        $saldo    = $opening;
        $cashIn   = 0.0;
        $cashOut  = 0.0;
        $rows     = [];
        foreach ($transactions as $t) {
            $isDeleted = $t->deleted_at !== null;
            $amt       = (float) $t->amount;
            $isIn      = $t->direction === PettyCashTransaction::DIR_IN;

            if (! $isDeleted) {
                $saldo += $isIn ? $amt : -$amt;
                // Ringkasan mengikuti semantik dashboard: cash in = top up saja,
                // cash out = pengeluaran non-transfer.
                if (! $t->is_transfer) {
                    if ($isIn) {
                        if ($t->is_topup) $cashIn += $amt;
                    } else {
                        $cashOut += $amt;
                    }
                }
            }

            // Tabel jejak hanya menampilkan PENGELUARAN (cash out non-transfer).
            // Top up (direction in) & transfer tak masuk tabel — tapi tetap ikut
            // dihitung di ringkasan/saldo di atas.
            $isExpense = $t->direction === PettyCashTransaction::DIR_OUT && ! $t->is_transfer;
            if (! $isExpense) continue;

            $rows[] = (object) [
                'date'           => $t->trx_date instanceof Carbon ? $t->trx_date : Carbon::parse($t->trx_date),
                'description'    => $t->description,
                'category_label' => $t->category ? (PettyCashTransaction::CATEGORIES[$t->category] ?? $t->category) : null,
                'wallet_name'    => $t->fundSource?->name ?? '-',
                'wallet_short'   => $t->fundSource?->effectiveShortLabel() ?? '?',
                'wallet_color'   => $t->fundSource?->effectiveBrandColor() ?? '#64748b',
                'amount'         => $amt,
                'by'             => $t->created_by ? ($userNames[$t->created_by] ?? '—') : '—',
                'recorded_at'    => $t->created_at, // jam asli saat transaksi dicatat ke sistem
                'edited'         => $t->updated_by !== null && $t->edited_at !== null,
                'edited_at'      => $t->edited_at,
                'edited_by'      => $t->updated_by ? ($userNames[$t->updated_by] ?? '—') : null,
                'deleted'        => $isDeleted,
                'ref_no'         => $t->ref_no,
            ];
        }

        $closing = $saldo;

        $bulanLabel = [
            1 => 'Januari',  2 => 'Februari', 3 => 'Maret',
            4 => 'April',    5 => 'Mei',      6 => 'Juni',
            7 => 'Juli',     8 => 'Agustus',  9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $monthName = $bulanLabel[$month] ?? '-';

        // Navigasi bulan sebelum/sesudah.
        $prevDate = Carbon::create($year, $month, 1)->subMonth();
        $nextDate = Carbon::create($year, $month, 1)->addMonth();

        return view('finance.pettycash.branch-history', compact(
            'branch', 'period', 'rows',
            'opening', 'closing', 'cashIn', 'cashOut',
            'year', 'month', 'monthName', 'bulanLabel',
            'prevDate', 'nextDate'
        ));
    }

    /**
     * POST /finance/pettycash/branch/{branchId}/riwayat/reset
     *
     * Reset (hard delete) SELURUH jejak transaksi 1 cabang untuk 1 bulan.
     * Khusus Super Admin. Cakupan hanya periode yang dibuka (branch+bulan);
     * cabang/bulan lain tidak tersentuh. Model TIDAK pakai trait SoftDeletes
     * (deleted_at manual), jadi ->delete() = DELETE SQL sungguhan — ikut
     * membuang baris yang deleted_at-nya terisi supaya tabel benar-benar bersih.
     */
    public function resetBranchHistory(Request $request, string $branchId)
    {
        $this->guard();
        if (! isSuperAdmin()) {
            abort(403, 'Hanya Super Admin yang boleh reset jejak transaksi.');
        }

        $branch = RawBranch::find($branchId);
        if (! $branch) {
            abort(404, 'Cabang tidak ditemukan.');
        }

        $today = Carbon::today();
        $year  = (int) $request->get('year',  $today->year);
        $month = (int) $request->get('month', $today->month);
        if ($year < 2000 || $year > 2100) $year = (int) $today->year;
        if ($month < 1   || $month > 12)  $month = (int) $today->month;

        $period = PettyCashPeriod::ensurePeriod(
            $year,
            $month,
            (float) ($branch->petty_cash_default_float ?? 1500000),
            (string) $branch->id,
        );

        $deleted = PettyCashTransaction::forPeriod($period->id)->delete();

        $bulanLabel = [
            1 => 'Januari',  2 => 'Februari', 3 => 'Maret',
            4 => 'April',    5 => 'Mei',      6 => 'Juni',
            7 => 'Juli',     8 => 'Agustus',  9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $monthName = $bulanLabel[$month] ?? '-';

        return redirect()
            ->route('finance.pettycash.branch-history',
                    ['branchId' => $branch->id, 'year' => $year, 'month' => $month])
            ->with('success', "Jejak transaksi {$branch->name} ({$monthName} {$year}) direset — {$deleted} transaksi dihapus permanen.");
    }

    /**
     * POST /finance/pettycash/review/reset
     *
     * Reset "Riwayat Review Terakhir" — hard delete SEMUA pengajuan top-up yang
     * sudah di-review (approved/rejected). Khusus Super Admin. Pengajuan yang
     * masih pending (antrian) tidak tersentuh. Transaksi petty cash yang sudah
     * terlanjur dibuat dari approval TIDAK ikut terhapus (tak ada cascade;
     * backlink hanya di topup_requests.executed_trx_id) — jadi saldo/ledger aman,
     * yang dibersihkan hanya jejak review-nya.
     */
    public function resetReviewHistory(Request $request)
    {
        $this->guard();
        if (! isSuperAdmin()) {
            abort(403, 'Hanya Super Admin yang boleh reset riwayat review.');
        }

        $deleted = TopupRequest::where('status', '!=', TopupRequest::STATUS_PENDING)->delete();

        return redirect()
            ->route('finance.pettycash')
            ->with('success', "Riwayat review direset — {$deleted} pengajuan yang sudah di-review dihapus. Antrian pending & transaksi petty cash tidak terpengaruh.");
    }
}
