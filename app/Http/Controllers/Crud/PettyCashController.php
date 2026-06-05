<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\FundSource;
use App\Models\PettyCashPeriod;
use App\Models\PettyCashTransaction;
use App\Models\TransportCostPeriod;
use App\Models\TransportCostTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $kind = $this->kind($request);

        // Filter sekarang berbasis range tanggal (start_date..end_date),
        // tetap di-anchor ke 1 periode bulanan supaya konsep imprest /
        // top-up / tutup periode tidak bocor lintas bulan.
        //
        // Backward-compat: redirect lama yang lewat ?tahun=Y&bulan=M tetap
        // diterima — kalau start_date tidak ada, jatuh ke tahun/bulan,
        // lalu fallback ke bulan berjalan.
        $today = Carbon::today();

        $startParsed = $this->parseDate($request->get('start_date'));
        $endParsed   = $this->parseDate($request->get('end_date'));

        if ($startParsed) {
            $year  = (int) $startParsed->year;
            $month = (int) $startParsed->month;
        } else {
            $year  = (int) $request->get('tahun', $today->year);
            $month = (int) $request->get('bulan', $today->month);
        }

        if ($year < 2000 || $year > 2100) $year  = $today->year;
        if ($month < 1   || $month > 12)  $month = $today->month;

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd   = $monthStart->copy()->endOfMonth()->startOfDay();

        // Clamp ke dalam bulan periode — kalau user pilih tanggal di luar
        // bulan ini, snap balik ke awal/akhir bulan.
        $startCarbon = ($startParsed && $startParsed->between($monthStart, $monthEnd))
            ? $startParsed->copy()->startOfDay()
            : $monthStart->copy();

        $endCarbon = ($endParsed && $endParsed->between($monthStart, $monthEnd))
            ? $endParsed->copy()->startOfDay()
            : $monthEnd->copy();

        if ($endCarbon->lt($startCarbon)) {
            $endCarbon = $startCarbon->copy();
        }

        $startDate     = $startCarbon->format('Y-m-d');
        $endDate       = $endCarbon->format('Y-m-d');
        $monthStartStr = $monthStart->format('Y-m-d');
        $monthEndStr   = $monthEnd->format('Y-m-d');

        $search = trim((string) $request->get('q', ''));

        $periodClass = $this->periodClass($kind);
        $trxClass    = $this->trxClass($kind);

        $period = $periodClass::ensurePeriod($year, $month, $this->defaultFloat($kind));

        $transactions = $trxClass::active()
            ->with('fundSource')
            ->forPeriod($period->id)
            ->when($search !== '', function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%");
            })
            ->orderBy('trx_date')
            ->orderBy('id')
            ->get();

        $fundSources        = FundSource::active()->orderBy('sort_order')->orderBy('name')->get();
        $fundSourcesGrouped = $fundSources->groupBy('category');

        // Summary cards & source breakdown tetap mencerminkan SELURUH periode
        // — range tanggal cuma menyempitkan baris yang ditampilkan di tabel.
        [$rows, $summary, $sourceBalances] =
            $this->buildRows($period, $transactions, $fundSources, $startDate, $endDate);

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
            'period', 'rows', 'summary', 'sourceBalances',
            'year', 'month', 'bulanLabel',
            'startDate', 'endDate', 'monthStartStr', 'monthEndStr',
            'search', 'isCurrentMonth', 'isReadOnly',
            'kind', 'tabs', 'fundSources', 'fundSourcesGrouped'
        ));
    }

    /**
     * Parse string Y-m-d jadi Carbon. Return null kalau format invalid.
     */
    private function parseDate($value): ?Carbon
    {
        if (! is_string($value) || $value === '') return null;
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
        try {
            $d = Carbon::createFromFormat('!Y-m-d', $value);
            return $d ?: null;
        } catch (\Throwable $e) {
            return null;
        }
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
            'period_id'      => 'required|integer|exists:' . $tableName . ',id',
            'trx_date'       => 'required|date',
            'description'    => 'required|string|max:255',
            'amount'         => 'required|numeric|min:1',
            'fund_source_id' => 'required|integer|exists:fund_sources,id',
            'ref_no'         => 'nullable|string|max:50',
        ]);

        $period = $periodClass::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);

        // Guard: source dana harus punya saldo > 0 di periode ini.
        // Filter dropdown sudah hide source kosong, tapi validasi server-side
        // mencegah bypass via inspect element.
        $sourceBalance = $this->computeSourceBalance($period, (int) $data['fund_source_id'], $trxClass);
        if ($sourceBalance <= 0) {
            return redirect()
                ->route('pettycash', ['tab' => $kind, 'tahun' => $period->period_year, 'bulan' => $period->period_month])
                ->with('error', 'Sumber dana yang dipilih belum punya saldo. Silakan top up dulu.');
        }

        $trxClass::create([
            'period_id'      => $period->id,
            'trx_date'       => $data['trx_date'],
            'description'    => $data['description'],
            'direction'      => $trxClass::DIR_OUT,
            'amount'         => $data['amount'],
            'fund_source_id' => $data['fund_source_id'],
            'is_topup'       => 0,
            'ref_no'         => $data['ref_no'] ?? null,
            'created_by'     => session('id'),
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
            'period_id'      => 'required|integer|exists:' . $tableName . ',id',
            'trx_date'       => 'required|date',
            'description'    => 'nullable|string|max:255',
            'amount'         => 'required|numeric|min:1',
            'fund_source_id' => 'required|integer|exists:fund_sources,id',
            'ref_no'         => 'nullable|string|max:50',
        ]);

        $period = $periodClass::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);

        // Top up imprest hanya boleh ke sumber kategori Cash. Uang baru
        // selalu masuk sebagai cash dulu; isi Bank / E-Wallet dilakukan
        // via Transfer dari Cash. Validasi server-side mencegah bypass
        // via inspect element (UI sudah hide source non-cash dari dropdown
        // & disable tab "Tambah Saldo" untuk source non-cash).
        $sourceCategory = FundSource::where('id', $data['fund_source_id'])->value('category');
        if ($sourceCategory !== FundSource::CAT_CASH) {
            return redirect()
                ->route('pettycash', ['tab' => $kind, 'tahun' => $period->period_year, 'bulan' => $period->period_month])
                ->with('error', 'Top up saldo hanya boleh ke sumber Cash. Untuk isi Bank/E-Wallet, transfer dari Cash.');
        }

        $defaultLabel = $kind === 'transport' ? 'Top up biaya transportasi' : 'Top up kasir';

        $trxClass::create([
            'period_id'      => $period->id,
            'trx_date'       => $data['trx_date'],
            'description'    => $data['description'] ?: $defaultLabel,
            'direction'      => $trxClass::DIR_IN,
            'amount'         => $data['amount'],
            'fund_source_id' => $data['fund_source_id'],
            'is_topup'       => 1,
            'ref_no'         => $data['ref_no'] ?? null,
            'created_by'     => session('id'),
        ]);

        return redirect()
            ->route('pettycash', ['tab' => $kind, 'tahun' => $period->period_year, 'bulan' => $period->period_month])
            ->with('success', 'Top up saldo berhasil dicatat.');
    }

    /**
     * POST /pettycash/transfer?tab={kind} → transfer saldo antar sumber dana.
     *
     * Membuat 2 transaksi terkait via `ref_no`:
     *   - 1 CASH OUT dari fund_source asal
     *   - 1 CASH IN  ke fund_source tujuan
     * Keduanya bertanda is_transfer=1. Total saldo periode tidak berubah,
     * hanya distribusi per source.
     */
    public function transfer(Request $request)
    {
        $kind        = $this->kind($request);
        $periodClass = $this->periodClass($kind);
        $trxClass    = $this->trxClass($kind);

        $tableName = (new $periodClass)->getTable();
        $data = $request->validate([
            'period_id'      => 'required|integer|exists:' . $tableName . ',id',
            'trx_date'       => 'required|date',
            'from_source_id' => 'required|integer|exists:fund_sources,id',
            'to_source_id'   => 'required|integer|exists:fund_sources,id|different:from_source_id',
            'amount'         => 'required|numeric|min:1',
            'description'    => 'nullable|string|max:200',
        ], [
            'to_source_id.different' => 'Sumber tujuan tidak boleh sama dengan sumber asal.',
        ]);

        $period = $periodClass::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);

        // Cek saldo asal cukup
        $fromBalance = $this->computeSourceBalance($period, (int) $data['from_source_id'], $trxClass);
        if ($fromBalance < (float) $data['amount']) {
            return redirect()
                ->route('pettycash', ['tab' => $kind, 'tahun' => $period->period_year, 'bulan' => $period->period_month])
                ->with('error', 'Saldo sumber asal tidak cukup untuk ditransfer (saldo: Rp ' . number_format($fromBalance, 0, ',', '.') . ').');
        }

        $fromSource = FundSource::findOrFail($data['from_source_id']);
        $toSource   = FundSource::findOrFail($data['to_source_id']);
        $ref        = 'TRF-' . $period->id . '-' . time() . '-' . random_int(100, 999);
        $note       = trim((string) ($data['description'] ?? ''));

        $descOut = 'Transfer ke ' . $toSource->name   . ($note !== '' ? ' — ' . $note : '');
        $descIn  = 'Transfer dari ' . $fromSource->name . ($note !== '' ? ' — ' . $note : '');

        DB::transaction(function () use ($trxClass, $period, $data, $fromSource, $toSource, $descOut, $descIn, $ref) {
            // OUT dari source asal
            $trxClass::create([
                'period_id'      => $period->id,
                'trx_date'       => $data['trx_date'],
                'description'    => $descOut,
                'direction'      => $trxClass::DIR_OUT,
                'amount'         => $data['amount'],
                'fund_source_id' => $fromSource->id,
                'is_topup'       => 0,
                'is_transfer'    => 1,
                'ref_no'         => $ref,
                'created_by'     => session('id'),
            ]);
            // IN ke source tujuan
            $trxClass::create([
                'period_id'      => $period->id,
                'trx_date'       => $data['trx_date'],
                'description'    => $descIn,
                'direction'      => $trxClass::DIR_IN,
                'amount'         => $data['amount'],
                'fund_source_id' => $toSource->id,
                'is_topup'       => 0,
                'is_transfer'    => 1,
                'ref_no'         => $ref,
                'created_by'     => session('id'),
            ]);
        });

        $msg = 'Transfer Rp ' . number_format((float) $data['amount'], 0, ',', '.')
            . ' dari ' . $fromSource->name . ' ke ' . $toSource->name . ' berhasil dicatat.';

        return redirect()
            ->route('pettycash', ['tab' => $kind, 'tahun' => $period->period_year, 'bulan' => $period->period_month])
            ->with('success', $msg);
    }

    /**
     * DELETE /pettycash/transaction/{id}?tab={kind}
     *
     * Kalau transaksi adalah bagian dari transfer (is_transfer=1), hapus
     * juga pasangannya yang berbagi ref_no — supaya saldo tidak pincang.
     */
    public function destroy(Request $request, $id)
    {
        $kind     = $this->kind($request);
        $trxClass = $this->trxClass($kind);

        $trx = $trxClass::active()->findOrFail($id);
        $period = $trx->period;
        $this->abortIfReadOnly($period);

        $now = now();
        if ($trx->is_transfer && $trx->ref_no) {
            // Hapus kedua kaki transfer sekaligus
            $trxClass::active()
                ->where('ref_no', $trx->ref_no)
                ->where('is_transfer', 1)
                ->update(['deleted_at' => $now]);
            $msg = 'Transfer dihapus (kedua sisi).';
        } else {
            $trx->deleted_at = $now;
            $trx->save();
            $msg = 'Transaksi dihapus.';
        }

        return redirect()
            ->route('pettycash', ['tab' => $kind, 'tahun' => $period->period_year, 'bulan' => $period->period_month])
            ->with('success', $msg);
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

    private function buildRows($period, $transactions, $fundSources = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $opening = (float) $period->opening_balance;
        $saldo   = $opening;
        $totalIn = 0.0;
        $totalOut = 0.0;

        // Inisialisasi breakdown saldo per source.
        // Opening balance diasumsikan masuk source "Cash" — kalau master cash
        // tidak ditemukan, jatuh ke ember "uncategorized".
        $cashSourceId = null;
        $sourceMeta   = [];
        if ($fundSources) {
            foreach ($fundSources as $fs) {
                $sourceMeta[$fs->id] = [
                    'id'          => $fs->id,
                    'category'    => $fs->category,
                    'name'        => $fs->name,
                    'brand_color' => $fs->effectiveBrandColor(),
                    'short_label' => $fs->effectiveShortLabel(),
                    'logo_url'    => $fs->logo_url,
                    'in'          => 0.0,
                    'out'         => 0.0,
                    'balance'     => 0.0,
                ];
                if ($cashSourceId === null && $fs->category === \App\Models\FundSource::CAT_CASH) {
                    $cashSourceId = $fs->id;
                }
            }
        }
        $uncategorized = ['in' => 0.0, 'out' => 0.0, 'balance' => 0.0];

        if ($cashSourceId !== null && $opening != 0.0) {
            $sourceMeta[$cashSourceId]['balance'] += $opening;
        } elseif ($opening != 0.0) {
            $uncategorized['balance'] += $opening;
        }

        $rows = [];
        foreach ($transactions as $t) {
            $isTransfer = (bool) ($t->is_transfer ?? false);
            $isTopup    = (bool) ($t->is_topup ?? false);
            $cashIn  = $t->direction === 'in'  ? (float) $t->amount : 0.0;
            $cashOut = $t->direction === 'out' ? (float) $t->amount : 0.0;

            // Transfer = pergerakan INTERNAL antar sumber dana, bukan cash
            // flow eksternal. Jadi:
            //   - Saldo total TIDAK berubah (net 0: keluar dari A, masuk ke B)
            //   - Total Cash In / Cash Out di summary atas EXCLUDE transfer
            //   - Saldo running di tabel tetap konstan untuk row transfer
            //
            // Total Cash In secara eksplisit cuma menjumlahkan baris yang
            // is_topup=1 — sumber dana baru cuma datang lewat top-up. Guard
            // ini melindungi dari kemungkinan baris DIR_IN non-topup
            // (mis: koreksi manual, refund) ikut nyemprot summary.
            if (! $isTransfer) {
                $saldo += $cashIn - $cashOut;
                if ($isTopup) {
                    $totalIn += $cashIn;
                }
                $totalOut += $cashOut;
            }

            // Per-source breakdown TETAP include transfer karena dari sudut
            // pandang source individual, dana memang masuk/keluar dari
            // wallet tersebut.
            $sid = $t->fund_source_id;
            if ($sid && isset($sourceMeta[$sid])) {
                $sourceMeta[$sid]['in']      += $cashIn;
                $sourceMeta[$sid]['out']     += $cashOut;
                $sourceMeta[$sid]['balance'] += ($cashIn - $cashOut);
            } else {
                $uncategorized['in']      += $cashIn;
                $uncategorized['out']     += $cashOut;
                $uncategorized['balance'] += ($cashIn - $cashOut);
            }

            // Date-range filter: running saldo & summary tetap dihitung dari
            // SELURUH transaksi periode (supaya angka di summary cards dan
            // saldo running konsisten). Range cuma menyaring baris yang
            // muncul di tabel.
            $trxDateStr = $t->trx_date instanceof \Carbon\Carbon
                ? $t->trx_date->format('Y-m-d')
                : substr((string) $t->trx_date, 0, 10);

            if ($startDate !== null && $trxDateStr < $startDate) continue;
            if ($endDate   !== null && $trxDateStr > $endDate)   continue;

            $fs = $t->fundSource;
            $rows[] = (object) [
                'id'            => $t->id,
                'trx_date'      => $t->trx_date,
                'description'   => $t->description,
                'is_topup'      => (bool) $t->is_topup,
                'is_transfer'   => $isTransfer,
                'cash_in'       => $cashIn,
                'cash_out'      => $cashOut,
                'saldo'         => $saldo,
                'ref_no'        => $t->ref_no,
                'source_name'   => $fs?->name,
                'source_cat'    => $fs?->category,
                'source_color'  => $fs ? $fs->effectiveBrandColor() : null,
                'source_short'  => $fs ? $fs->effectiveShortLabel() : null,
                'source_logo'   => $fs?->logo_url,
            ];
        }

        $summary = [
            'opening'         => $opening,
            'total_in'        => $totalIn,
            'total_out'       => $totalOut,
            'closing'         => $saldo,
            'suggested_topup' => max(0, (float) $period->float_amount - $saldo),
        ];

        // Drop source yang tidak punya aktivitas & balance 0 (selain Cash agar
        // selalu tampil sebagai anchor saldo awal).
        $sourceBalances = [];
        foreach ($sourceMeta as $sid => $m) {
            $hasActivity = $m['in'] != 0.0 || $m['out'] != 0.0 || $m['balance'] != 0.0;
            if ($hasActivity || $sid === $cashSourceId) {
                $sourceBalances[] = (object) $m;
            }
        }
        if ($uncategorized['in'] != 0.0 || $uncategorized['out'] != 0.0 || $uncategorized['balance'] != 0.0) {
            $sourceBalances[] = (object) array_merge($uncategorized, [
                'id'          => null,
                'category'    => null,
                'name'        => \App\Models\FundSource::UNCATEGORIZED_LABEL,
                'brand_color' => '#94a3b8',
                'short_label' => '?',
                'logo_url'    => null,
            ]);
        }

        return [$rows, $summary, $sourceBalances];
    }

    private function abortIfReadOnly($period): void
    {
        if ($period->is_closed) {
            abort(403, 'Periode sudah ditutup (read-only).');
        }
    }

    /**
     * Hitung saldo current untuk satu fund_source di periode tertentu.
     * Opening balance periode diasumsikan masuk source "Cash" (sesuai
     * konvensi buildRows).
     */
    private function computeSourceBalance($period, int $fundSourceId, string $trxClass): float
    {
        $balance = 0.0;

        // Opening balance → masuk Cash
        $cashId = FundSource::where('category', FundSource::CAT_CASH)->value('id');
        if ($cashId && (int) $cashId === $fundSourceId) {
            $balance += (float) $period->opening_balance;
        }

        $rows = $trxClass::active()
            ->forPeriod($period->id)
            ->where('fund_source_id', $fundSourceId)
            ->get(['direction', 'amount']);

        foreach ($rows as $r) {
            $amt = (float) $r->amount;
            $balance += $r->direction === $trxClass::DIR_IN ? $amt : -$amt;
        }

        return $balance;
    }
}
