<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\CashDepositPeriod;
use App\Models\CashDepositTransaction;
use App\Models\RawBranch;
use App\Models\RawCustomer;
use Illuminate\Http\Request;

/**
 * CashDepositController
 *
 * Halaman Setoran — pasien bayar cash → masuk ke cash in.
 * Setor tunai → cash out (is_setor=1), saldo balik ke 0.
 *
 * Scoping:
 *   - Sales (session('raw_branch_id') tertentu): dropdown pasien & transaksi
 *     ke-filter ke cabang dia.
 *   - Admin: lihat semua, ada dropdown filter cabang.
 */
class CashDepositController extends Controller
{
    /**
     * GET /setoran
     */
    public function index(Request $request)
    {
        $year   = (int) $request->get('tahun', date('Y'));
        $month  = (int) $request->get('bulan', date('n'));
        $search = trim((string) $request->get('q', ''));
        $filterBranch = $request->get('branch', 'all');

        if ($year < 2000 || $year > 2100) $year  = (int) date('Y');
        if ($month < 1 || $month > 12)    $month = (int) date('n');

        $sessionBranch = session('raw_branch_id');
        // Sales: paksa filter ke cabang-nya, tidak bisa ganti.
        if ($sessionBranch) {
            $filterBranch = $sessionBranch;
        }

        $period = CashDepositPeriod::ensurePeriod($year, $month);

        $query = CashDepositTransaction::active()
            ->forPeriod($period->id)
            ->with(['customer:id,name,phone', 'branch:id,name']);

        if ($filterBranch !== 'all' && $filterBranch) {
            $query->where(function ($q) use ($filterBranch) {
                // Setor tunai (is_setor=1) tampilkan ke semua — tidak punya branch.
                $q->where('raw_branch_id', $filterBranch)
                  ->orWhere('is_setor', 1);
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($qq) use ($search) {
                      $qq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $transactions = $query->orderBy('trx_date')->orderBy('id')->get();

        [$rows, $summary] = $this->buildRows($period, $transactions);

        $now = (int) date('Y');
        $years = collect(range($now - 3, $now + 1))->reverse()->values();
        $bulanLabel = [
            1 => 'Januari',  2 => 'Februari', 3 => 'Maret',
            4 => 'April',    5 => 'Mei',      6 => 'Juni',
            7 => 'Juli',     8 => 'Agustus',  9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        // Cabang untuk filter admin (Sales lock, tidak butuh dropdown)
        $branches = $sessionBranch ? collect() : RawBranch::orderBy('name')->get();
        $activeBranch = $filterBranch !== 'all' && $filterBranch
            ? RawBranch::find($filterBranch)
            : null;

        $isCurrentMonth = $period->isCurrentMonth();
        $isReadOnly     = (bool) $period->is_closed || ! $isCurrentMonth;

        return view('crud.setoran.index', compact(
            'period', 'rows', 'summary',
            'year', 'month', 'years', 'bulanLabel',
            'search', 'filterBranch',
            'branches', 'activeBranch',
            'sessionBranch', 'isCurrentMonth', 'isReadOnly'
        ));
    }

    /**
     * POST /setoran/payment → pasien bayar (cash in).
     */
    public function storePayment(Request $request)
    {
        $data = $request->validate([
            'period_id'       => 'required|integer|exists:cash_deposit_periods,id',
            'trx_date'        => 'required|date',
            'raw_customer_id' => 'required|exists:raw_customer,id',
            'description'     => 'nullable|string|max:255',
            'amount'          => 'required|numeric|min:1',
            'ref_no'          => 'nullable|string|max:50',
        ]);

        $period = CashDepositPeriod::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);

        $customer = RawCustomer::findOrFail($data['raw_customer_id']);

        // Sales tidak boleh assign customer dari cabang lain.
        $sessionBranch = session('raw_branch_id');
        if ($sessionBranch && $customer->raw_branch_id && $customer->raw_branch_id !== $sessionBranch) {
            return back()->with('error', 'Pasien tidak terdaftar di cabang Anda.');
        }

        CashDepositTransaction::create([
            'period_id'       => $period->id,
            'trx_date'        => $data['trx_date'],
            'raw_customer_id' => $customer->id,
            'raw_branch_id'   => $customer->raw_branch_id,
            'description'     => trim((string) ($data['description'] ?? '')),
            'direction'       => CashDepositTransaction::DIR_IN,
            'amount'          => $data['amount'],
            'is_setor'        => 0,
            'ref_no'          => $data['ref_no'] ?? null,
            'created_by'      => session('id'),
        ]);

        return redirect()
            ->route('setoran', ['tahun' => $period->period_year, 'bulan' => $period->period_month])
            ->with('success', 'Pembayaran pasien berhasil dicatat.');
    }

    /**
     * POST /setoran/setor → setor tunai (cash out, is_setor=1).
     */
    public function storeSetor(Request $request)
    {
        $data = $request->validate([
            'period_id'   => 'required|integer|exists:cash_deposit_periods,id',
            'trx_date'    => 'required|date',
            'description' => 'nullable|string|max:255',
            'amount'      => 'required|numeric|min:1',
            'ref_no'      => 'nullable|string|max:50',
        ]);

        $period = CashDepositPeriod::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);

        CashDepositTransaction::create([
            'period_id'       => $period->id,
            'trx_date'        => $data['trx_date'],
            'raw_customer_id' => null,
            'raw_branch_id'   => null, // setor tunai = global, tidak per-cabang
            'description'     => $data['description'] ?: 'SETOR TUNAI',
            'direction'       => CashDepositTransaction::DIR_OUT,
            'amount'          => $data['amount'],
            'is_setor'        => 1,
            'ref_no'          => $data['ref_no'] ?? null,
            'created_by'      => session('id'),
        ]);

        return redirect()
            ->route('setoran', ['tahun' => $period->period_year, 'bulan' => $period->period_month])
            ->with('success', 'Setor tunai berhasil dicatat.');
    }

    /**
     * DELETE /setoran/transaction/{id}
     */
    public function destroy($id)
    {
        $trx = CashDepositTransaction::active()->findOrFail($id);
        $period = $trx->period;
        $this->abortIfReadOnly($period);

        $trx->deleted_at = now();
        $trx->save();

        return redirect()
            ->route('setoran', ['tahun' => $period->period_year, 'bulan' => $period->period_month])
            ->with('success', 'Transaksi dihapus.');
    }

    /* ───────── Helpers ───────── */

    private function buildRows(CashDepositPeriod $period, $transactions): array
    {
        $opening  = (float) $period->opening_balance;
        $saldo    = $opening;
        $totalIn  = 0.0;
        $totalOut = 0.0;

        $rows = [];
        foreach ($transactions as $t) {
            $cashIn  = $t->direction === CashDepositTransaction::DIR_IN  ? (float) $t->amount : 0.0;
            $cashOut = $t->direction === CashDepositTransaction::DIR_OUT ? (float) $t->amount : 0.0;
            $saldo += $cashIn - $cashOut;
            $totalIn  += $cashIn;
            $totalOut += $cashOut;

            $rows[] = (object) [
                'id'          => $t->id,
                'trx_date'    => $t->trx_date,
                'description' => $t->description,
                'is_setor'    => (bool) $t->is_setor,
                'cash_in'     => $cashIn,
                'cash_out'    => $cashOut,
                'saldo'       => $saldo,
                'ref_no'      => $t->ref_no,
                'customer'    => $t->customer,
                'branch'      => $t->branch,
            ];
        }

        $summary = [
            'opening'   => $opening,
            'total_in'  => $totalIn,
            'total_out' => $totalOut,
            'closing'   => $saldo,
        ];

        return [$rows, $summary];
    }

    private function abortIfReadOnly(CashDepositPeriod $period): void
    {
        if ($period->is_closed) {
            abort(403, 'Periode sudah ditutup (read-only).');
        }
    }
}
