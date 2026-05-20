<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\PettyCashPeriod;
use App\Models\PettyCashTransaction;
use App\Models\TransportCostPeriod;
use App\Models\TransportCostTransaction;
use Illuminate\Http\Request;

/**
 * PettyCashController
 *
 * Mengelola 2 module yang strukturnya identik tapi tabel terpisah:
 *   - tab=petty_cash  → tabel petty_cash_*       (kas kecil umum, float 1.500.000)
 *   - tab=transport   → tabel transport_cost_*   (biaya transport,  float 1.000.000)
 *
 * Semua method (index/store/topup/destroy/close) terima parameter `tab`
 * (default petty_cash) lalu pakai class model yang sesuai.
 */
class PettyCashController extends Controller
{
    private const KINDS = [
        'petty_cash' => [
            'label'         => 'Petty Cash',
            'period_model'  => PettyCashPeriod::class,
            'trx_model'     => PettyCashTransaction::class,
            'default_float' => 1500000,
        ],
        'transport' => [
            'label'         => 'Biaya Transportasi',
            'period_model'  => TransportCostPeriod::class,
            'trx_model'     => TransportCostTransaction::class,
            'default_float' => 1000000,
        ],
    ];

    /* ───────── Helpers ───────── */

    private function kind(Request $request): string
    {
        $k = (string) $request->get('tab', 'petty_cash');
        return isset(self::KINDS[$k]) ? $k : 'petty_cash';
    }

    private function periodClass(string $kind): string { return self::KINDS[$kind]['period_model']; }
    private function trxClass(string $kind):    string { return self::KINDS[$kind]['trx_model']; }
    private function defaultFloat(string $kind): int   { return (int) self::KINDS[$kind]['default_float']; }

    /* ───────── Actions ───────── */

    /**
     * GET /pettycash?tab={kind}
     */
    public function index(Request $request)
    {
        $kind  = $this->kind($request);
        $year  = (int) $request->get('tahun', date('Y'));
        $month = (int) $request->get('bulan', date('n'));

        if ($year < 2000 || $year > 2100)  $year  = (int) date('Y');
        if ($month < 1   || $month > 12)   $month = (int) date('n');

        $search = trim((string) $request->get('q', ''));

        $periodClass = $this->periodClass($kind);
        $trxClass    = $this->trxClass($kind);

        $period = $periodClass::ensurePeriod($year, $month, $this->defaultFloat($kind));

        $transactions = $trxClass::active()
            ->forPeriod($period->id)
            ->when($search !== '', function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%");
            })
            ->orderBy('trx_date')
            ->orderBy('id')
            ->get();

        [$rows, $summary] = $this->buildRows($period, $transactions);

        $now = (int) date('Y');
        $years = collect(range($now - 3, $now + 1))->reverse()->values();

        $bulanLabel = [
            1 => 'Januari',  2 => 'Februari', 3 => 'Maret',
            4 => 'April',    5 => 'Mei',      6 => 'Juni',
            7 => 'Juli',     8 => 'Agustus',  9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $isCurrentMonth = $period->isCurrentMonth();
        $isReadOnly     = (bool) $period->is_closed || ! $isCurrentMonth;

        // Tabs metadata untuk view
        $tabs = collect(self::KINDS)->map(fn ($cfg, $k) => [
            'kind'   => $k,
            'label'  => $cfg['label'],
            'active' => $k === $kind,
        ])->values()->all();

        return view('crud.pettycash.index', compact(
            'period', 'rows', 'summary',
            'year', 'month', 'years', 'bulanLabel',
            'search', 'isCurrentMonth', 'isReadOnly',
            'kind', 'tabs'
        ));
    }

    /**
     * POST /pettycash/expense?tab={kind} → tambah pengeluaran.
     */
    public function store(Request $request)
    {
        $kind = $this->kind($request);
        $periodClass = $this->periodClass($kind);
        $trxClass    = $this->trxClass($kind);

        $tableName = (new $periodClass)->getTable();
        $data = $request->validate([
            'period_id'   => 'required|integer|exists:' . $tableName . ',id',
            'trx_date'    => 'required|date',
            'description' => 'required|string|max:255',
            'amount'      => 'required|numeric|min:1',
            'ref_no'      => 'nullable|string|max:50',
        ]);

        $period = $periodClass::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);

        $trxClass::create([
            'period_id'   => $period->id,
            'trx_date'    => $data['trx_date'],
            'description' => $data['description'],
            'direction'   => $trxClass::DIR_OUT,
            'amount'      => $data['amount'],
            'is_topup'    => 0,
            'ref_no'      => $data['ref_no'] ?? null,
            'created_by'  => session('id'),
        ]);

        return redirect()
            ->route('pettycash', ['tab' => $kind, 'tahun' => $period->period_year, 'bulan' => $period->period_month])
            ->with('success', 'Pengeluaran berhasil ditambahkan.');
    }

    /**
     * POST /pettycash/topup?tab={kind} → top up saldo.
     */
    public function topup(Request $request)
    {
        $kind = $this->kind($request);
        $periodClass = $this->periodClass($kind);
        $trxClass    = $this->trxClass($kind);

        $tableName = (new $periodClass)->getTable();
        $data = $request->validate([
            'period_id'   => 'required|integer|exists:' . $tableName . ',id',
            'trx_date'    => 'required|date',
            'description' => 'nullable|string|max:255',
            'amount'      => 'required|numeric|min:1',
            'ref_no'      => 'nullable|string|max:50',
        ]);

        $period = $periodClass::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);

        $defaultLabel = $kind === 'transport' ? 'Top up biaya transportasi' : 'Top up kasir';

        $trxClass::create([
            'period_id'   => $period->id,
            'trx_date'    => $data['trx_date'],
            'description' => $data['description'] ?: $defaultLabel,
            'direction'   => $trxClass::DIR_IN,
            'amount'      => $data['amount'],
            'is_topup'    => 1,
            'ref_no'      => $data['ref_no'] ?? null,
            'created_by'  => session('id'),
        ]);

        return redirect()
            ->route('pettycash', ['tab' => $kind, 'tahun' => $period->period_year, 'bulan' => $period->period_month])
            ->with('success', 'Top up saldo berhasil dicatat.');
    }

    /**
     * DELETE /pettycash/transaction/{id}?tab={kind}
     */
    public function destroy(Request $request, $id)
    {
        $kind     = $this->kind($request);
        $trxClass = $this->trxClass($kind);

        $trx = $trxClass::active()->findOrFail($id);
        $period = $trx->period;
        $this->abortIfReadOnly($period);

        $trx->deleted_at = now();
        $trx->save();

        return redirect()
            ->route('pettycash', ['tab' => $kind, 'tahun' => $period->period_year, 'bulan' => $period->period_month])
            ->with('success', 'Transaksi dihapus.');
    }

    /**
     * POST /pettycash/period/{id}/close?tab={kind}
     */
    public function close(Request $request, $periodId)
    {
        $kind        = $this->kind($request);
        $periodClass = $this->periodClass($kind);
        $trxClass    = $this->trxClass($kind);

        $period = $periodClass::findOrFail($periodId);
        if (! $period->is_closed) {
            $net = (float) $trxClass::netForPeriod($period->id);
            $period->update([
                'closing_balance' => (float) $period->opening_balance + $net,
                'is_closed'       => 1,
            ]);
        }

        return redirect()
            ->route('pettycash', ['tab' => $kind, 'tahun' => $period->period_year, 'bulan' => $period->period_month])
            ->with('success', 'Periode ditutup.');
    }

    /* ───────── Internal ───────── */

    private function buildRows($period, $transactions): array
    {
        $opening = (float) $period->opening_balance;
        $saldo   = $opening;
        $totalIn = 0.0;
        $totalOut = 0.0;

        $rows = [];
        foreach ($transactions as $t) {
            $cashIn  = $t->direction === 'in'  ? (float) $t->amount : 0.0;
            $cashOut = $t->direction === 'out' ? (float) $t->amount : 0.0;
            $saldo += $cashIn - $cashOut;
            $totalIn  += $cashIn;
            $totalOut += $cashOut;

            $rows[] = (object) [
                'id'          => $t->id,
                'trx_date'    => $t->trx_date,
                'description' => $t->description,
                'is_topup'    => (bool) $t->is_topup,
                'cash_in'     => $cashIn,
                'cash_out'    => $cashOut,
                'saldo'       => $saldo,
                'ref_no'      => $t->ref_no,
            ];
        }

        $summary = [
            'opening'         => $opening,
            'total_in'        => $totalIn,
            'total_out'       => $totalOut,
            'closing'         => $saldo,
            'suggested_topup' => max(0, (float) $period->float_amount - $saldo),
        ];

        return [$rows, $summary];
    }

    private function abortIfReadOnly($period): void
    {
        if ($period->is_closed) {
            abort(403, 'Periode sudah ditutup (read-only).');
        }
    }
}
