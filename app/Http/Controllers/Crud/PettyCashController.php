<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\FundSource;
use App\Models\FundSourceBranchAccount;
use App\Models\PettyCashPeriod;
use App\Models\PettyCashTransaction;
use App\Models\RawBranch;
use App\Models\TransportCostPeriod;
use App\Models\TransportCostTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * PettyCashController
 *
 * Mengelola 2 module yang strukturnya identik tapi tabel terpisah:
 *   - tab=petty_cash  → tabel petty_cash_*       (kas kecil umum, float 1.500.000)
 *   - tab=transport   → tabel transport_cost_*   (biaya transport,  float 1.000.000)
 *
 * Semua method (index/store/topup/destroy/close) terima parameter `tab`
 * (default petty_cash) lalu pakai class model yang sesuai.
 *
 * Branch scoping:
 *   - Sales (session('raw_branch_id') terisi) → lock ke cabangnya, tidak
 *     bisa ganti via ?branch=.
 *   - Admin (canSeeAllBranches()) → dropdown cabang, default = cabang
 *     pertama urut nama. Pilihan persisted via ?branch=<uuid>.
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

    /**
     * Resolve branch yang sedang diakses:
     *   - Sales (punya session('raw_branch_id')) → selalu cabang dia.
     *   - Admin → ambil dari ?branch=, validasi, default ke cabang pertama
     *     kalau tidak ada / invalid.
     * Return null kalau di DB belum ada cabang sama sekali (case ekstrem).
     */
    private function resolveBranchId(Request $request): ?string
    {
        $session = session('raw_branch_id');
        if ($session) {
            return (string) $session;
        }
        if (! canSeeAllBranches()) {
            abort(403, 'Anda belum di-assign ke cabang manapun.');
        }
        $requested = $request->get('branch');
        if ($requested) {
            $exists = RawBranch::whereKey($requested)->exists();
            if ($exists) return (string) $requested;
        }
        $first = RawBranch::orderBy('name')->first();
        if (! $first) {
            abort(503, 'Belum ada cabang terdaftar — tambahkan minimal satu cabang dulu di menu Kelola Cabang.');
        }
        return $first->id;
    }

    /**
     * Float default (imprest) per kind, dengan override per cabang dari
     * raw_branch.{petty_cash,transport_cost}_default_float kalau di-set.
     */
    private function defaultFloatForBranch(string $kind, ?string $branchId): float
    {
        $fallback = (float) $this->defaultFloat($kind);
        if (! $branchId) return $fallback;
        $branch = RawBranch::find($branchId);
        return $branch ? $branch->defaultFloatFor($kind, $fallback) : $fallback;
    }

    /**
     * Param yang harus di-preserve di semua redirect/route — tab + branch
     * (kalau branch dari query, bukan session-lock).
     */
    private function preserveParams(string $kind): array
    {
        $params = ['tab' => $kind];
        if (! session('raw_branch_id') && request()->filled('branch')) {
            $params['branch'] = request()->get('branch');
        }
        return $params;
    }

    /* ───────── Actions ───────── */

    /**
     * GET /pettycash?tab={kind}
     */
    public function index(Request $request)
    {
        $kind     = $this->kind($request);
        $branchId = $this->resolveBranchId($request);

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

        $period = $periodClass::ensurePeriod(
            $year,
            $month,
            $this->defaultFloatForBranch($kind, $branchId),
            $branchId,
        );

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

        // Map: source_id → account_number untuk cabang ini. Dipakai untuk
        // pre-fill input account di modal top-up & untuk tampil di card.
        $branchAccountMap = FundSourceBranchAccount::mapForBranch($branchId);

        // Summary cards & source breakdown tetap mencerminkan SELURUH periode
        // — range tanggal cuma menyempitkan baris yang ditampilkan di tabel.
        [$rows, $summary, $sourceBalances] =
            $this->buildRows($period, $transactions, $fundSources, $startDate, $endDate, $branchAccountMap);

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

        // Branch dropdown data — kosong untuk Sales (di-lock), berisi
        // semua cabang untuk admin.
        $sessionBranch = session('raw_branch_id');
        $branches      = $sessionBranch ? collect() : RawBranch::orderBy('name')->get();
        $activeBranch  = $branchId ? RawBranch::find($branchId) : null;

        return view('crud.pettycash.index', compact(
            'period', 'rows', 'summary', 'sourceBalances',
            'year', 'month', 'bulanLabel',
            'startDate', 'endDate', 'monthStartStr', 'monthEndStr',
            'search', 'isCurrentMonth', 'isReadOnly',
            'kind', 'tabs', 'fundSources', 'fundSourcesGrouped',
            'branches', 'activeBranch', 'branchId', 'sessionBranch',
            'branchAccountMap'
        ));
    }

    /**
     * GET /pettycash/export?tab={kind}&branch=&start_date=&end_date=
     *
     * Export CSV transaksi periode aktif untuk cabang aktif. Hanya export
     * baris TOP UP & PENGELUARAN — transfer / tarik tunai di-skip
     * (internal movement, bukan cash flow nyata). Ikut filter date range
     * yang sama dengan view index.
     */
    public function export(Request $request)
    {
        $kind        = $this->kind($request);
        $branchId    = $this->resolveBranchId($request);
        $periodClass = $this->periodClass($kind);
        $trxClass    = $this->trxClass($kind);

        // Range tanggal: pakai parameter kalau ada, default bulan aktif.
        $today       = Carbon::today();
        $startParsed = $this->parseDate($request->get('start_date'));
        $endParsed   = $this->parseDate($request->get('end_date'));

        $year  = $startParsed ? (int) $startParsed->year  : (int) $today->year;
        $month = $startParsed ? (int) $startParsed->month : (int) $today->month;

        $period = $periodClass::ensurePeriod(
            $year, $month,
            $this->defaultFloatForBranch($kind, $branchId),
            $branchId,
        );

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd   = $monthStart->copy()->endOfMonth()->startOfDay();
        $startCarbon = ($startParsed && $startParsed->between($monthStart, $monthEnd))
            ? $startParsed->copy()->startOfDay()
            : $monthStart->copy();
        $endCarbon = ($endParsed && $endParsed->between($monthStart, $monthEnd))
            ? $endParsed->copy()->startOfDay()
            : $monthEnd->copy();
        if ($endCarbon->lt($startCarbon)) $endCarbon = $startCarbon->copy();

        $startDate = $startCarbon->format('Y-m-d');
        $endDate   = $endCarbon->format('Y-m-d');

        // Tarik HANYA top up & pengeluaran (skip transfer).
        $transactions = $trxClass::active()
            ->with('fundSource')
            ->forPeriod($period->id)
            ->where(function ($q) {
                $q->where('is_topup', 1)
                  ->orWhere(function ($qq) {
                      $qq->where('is_topup', 0)->where('is_transfer', 0);
                  });
            })
            ->whereDate('trx_date', '>=', $startDate)
            ->whereDate('trx_date', '<=', $endDate)
            ->orderBy('trx_date')
            ->orderBy('id')
            ->get();

        $branch = $branchId ? RawBranch::find($branchId) : null;
        $branchSlug = $branch
            ? preg_replace('/[^a-z0-9]+/i', '-', strtolower($branch->name))
            : 'all';
        $kindSlug   = $kind === 'transport' ? 'biaya-transportasi' : 'petty-cash';
        $kindLabel  = $kind === 'transport' ? 'Biaya Transportasi' : 'Petty Cash';
        $filename   = sprintf('%s_%s_%s_%s.xlsx', $kindSlug, $branchSlug, $startDate, $endDate);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($kindLabel, 0, 31)); // tab name max 31 char

        // ─── Info block (atas) ───
        $sheet->setCellValue('A1', 'Modul');   $sheet->setCellValue('B1', $kindLabel);
        $sheet->setCellValue('A2', 'Cabang');  $sheet->setCellValue('B2', $branch ? $branch->name : '-');
        $sheet->setCellValue('A3', 'Periode'); $sheet->setCellValue('B3', $startDate . ' s/d ' . $endDate);
        $sheet->getStyle('A1:A3')->getFont()->setBold(true);

        // ─── Header tabel (row 5) ───
        $headerRow = 5;
        $headerCols = [
            'A' => 'Tanggal', 'B' => 'Jenis', 'C' => 'Sumber Dana',
            'D' => 'Keterangan', 'E' => 'Cash In', 'F' => 'Cash Out', 'G' => 'Saldo',
        ];
        foreach ($headerCols as $col => $h) {
            $sheet->setCellValue($col . $headerRow, $h);
        }
        $sheet->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1E40AF']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        // ─── Saldo awal row (row 6) ───
        // Pakai sum snapshot per-source (sama seperti dashboard buildRows())
        // supaya angka di export konsisten dengan card "Saldo Akhir" & breakdown
        // per sumber dana di layar. Scalar opening_balance bisa drift dari sum
        // snapshot kalau ada transaksi prev period dengan fund_source_id NULL
        // (computeClosingPerSource skip yang tanpa source, netForPeriod tidak).
        $openingPerSource = $period->openingPerSourceResolved();
        $opening = ! empty($openingPerSource)
            ? array_sum($openingPerSource)
            : (float) $period->opening_balance;
        $saldo   = $opening;
        $row     = $headerRow + 1;

        $sheet->setCellValue("A{$row}", $startDate);
        $sheet->setCellValue("D{$row}", 'Saldo awal periode');
        $sheet->setCellValueExplicit("G{$row}", $opening, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '475569']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
        ]);
        $sheet->getStyle("A{$row}:G{$row}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DBEAFE'));
        $row++;

        // ─── Data rows ───
        $totalIn  = 0.0;
        $totalOut = 0.0;
        $dataStartRow = $row;
        foreach ($transactions as $i => $t) {
            $cashIn  = $t->direction === 'in'  ? (float) $t->amount : 0.0;
            $cashOut = $t->direction === 'out' ? (float) $t->amount : 0.0;
            $totalIn  += $cashIn;
            $totalOut += $cashOut;
            $saldo   += ($cashIn - $cashOut);

            $fs = $t->fundSource;
            $date = $t->trx_date instanceof \Carbon\Carbon
                ? $t->trx_date->format('Y-m-d')
                : substr((string) $t->trx_date, 0, 10);

            $sheet->setCellValue("A{$row}", $date);
            $sheet->setCellValue("B{$row}", $t->is_topup ? 'Top up' : 'Pengeluaran');
            $sheet->setCellValue("C{$row}", $fs ? $fs->name : '-');
            $sheet->setCellValue("D{$row}", (string) $t->description);
            if ($cashIn > 0)  $sheet->setCellValueExplicit("E{$row}", $cashIn,  \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            if ($cashOut > 0) $sheet->setCellValueExplicit("F{$row}", $cashOut, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("G{$row}", $saldo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

            // Highlight row Top up dengan background kuning + teks bold —
            // override zebra striping. Pengeluaran biasa pakai zebra normal.
            if ($t->is_topup) {
                $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '78350F']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF59D']],
                ]);
            } elseif ($i % 2 === 1) {
                $sheet->getStyle("A{$row}:G{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('EFF6FF');
            }
            // Border tipis di semua sel
            $sheet->getStyle("A{$row}:G{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DBEAFE'));

            $row++;
        }

        $lastDataRow = $row - 1;

        // Number format ribuan untuk kolom Cash In/Out/Saldo (termasuk opening row)
        $sheet->getStyle("E" . ($headerRow + 1) . ":G{$lastDataRow}")
            ->getNumberFormat()->setFormatCode('#,##0');

        // ─── Footer TOTAL & SALDO AKHIR ───
        $totalRow = $lastDataRow + 2;
        $sheet->setCellValue("D{$totalRow}", 'TOTAL');
        $sheet->setCellValueExplicit("E{$totalRow}", $totalIn,  \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("F{$totalRow}", $totalOut, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("G{$totalRow}", $saldo,    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->getStyle("D{$totalRow}:G{$totalRow}")->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2563EB']]],
        ]);
        $sheet->getStyle("E{$totalRow}:G{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');

        // Auto-width semua kolom
        foreach (['A','B','C','D','E','F','G'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Freeze pane di bawah header tabel supaya saat scroll, header tetap kelihatan
        $sheet->freezePane('A' . ($headerRow + 1));

        // Auto-filter di header tabel
        $sheet->setAutoFilter("A{$headerRow}:G{$headerRow}");

        // ─── Output ───
        $headers = [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache',
        ];
        $callback = function () use ($spreadsheet) {
            (new XlsxWriter($spreadsheet))->save('php://output');
        };
        return response()->stream($callback, 200, $headers);
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
        $this->abortIfBranchMismatch($request, $period);

        // Guard: source dana harus punya saldo > 0 di periode ini.
        // Filter dropdown sudah hide source kosong, tapi validasi server-side
        // mencegah bypass via inspect element.
        $sourceBalance = $this->computeSourceBalance($period, (int) $data['fund_source_id'], $trxClass);
        if ($sourceBalance <= 0) {
            return redirect()
                ->route('pettycash', $this->preserveParams($kind))
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
            ->route('pettycash', $this->preserveParams($kind))
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
            'account_number' => 'nullable|string|max:50',
        ]);

        $period = $periodClass::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);
        $this->abortIfBranchMismatch($request, $period);

        // Account number opsional — diisi user di form top up untuk Bank
        // (no rekening) atau E-Wallet (no telepon). Cash diabaikan karena
        // tidak punya nomor. Disimpan di tabel fund_source_branch_accounts
        // per (cabang, source) — bank cabang A ≠ bank cabang B.
        $acct = isset($data['account_number']) ? trim((string) $data['account_number']) : '';
        if ($acct !== '' && $period->raw_branch_id) {
            $source = FundSource::find($data['fund_source_id']);
            if ($source && in_array($source->category, [FundSource::CAT_BANK, FundSource::CAT_EWALLET], true)) {
                FundSourceBranchAccount::upsertFor(
                    (string) $period->raw_branch_id,
                    (int) $source->id,
                    $acct,
                );
            }
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
            ->route('pettycash', $this->preserveParams($kind))
            ->with('success', 'Top up saldo berhasil dicatat.');
    }

    /**
     * POST /pettycash/opening?tab={kind} → set saldo awal / migrasi multi-source
     * sekaligus.
     *
     * Update kolom source_opening_balances JSON pada period (snapshot per-source),
     * tanpa membuat transaksi. Dipakai saat pertama kali pakai sistem (migrasi
     * dana yang sudah berjalan operasional). Scalar opening_balance ikut di-sync
     * ke sum(snapshot) supaya konsisten dengan dashboard & export.
     *
     * Request body: balances[<fund_source_id>] = "1500000" (string atau numeric).
     * Source dengan nominal 0 / kosong di-skip (tidak masuk snapshot).
     */
    public function setOpening(Request $request)
    {
        $kind        = $this->kind($request);
        $periodClass = $this->periodClass($kind);

        $tableName = (new $periodClass)->getTable();
        $request->validate([
            'period_id' => 'required|integer|exists:' . $tableName . ',id',
            'balances'  => 'required|array',
        ]);

        $period = $periodClass::findOrFail($request->input('period_id'));
        $this->abortIfReadOnly($period);
        $this->abortIfBranchMismatch($request, $period);

        // Parse balances: strip non-digit char (handle format "1.500.000"),
        // validate fund_source_id exists, skip yang 0 / kosong.
        $validSources = FundSource::whereIn('category', [FundSource::CAT_CASH, FundSource::CAT_BANK, FundSource::CAT_EWALLET])
            ->get(['id', 'category']);
        $sourceCategoryMap = $validSources->pluck('category', 'id')->all();
        $snapshot = [];
        foreach ((array) $request->input('balances', []) as $sid => $raw) {
            $sid = (int) $sid;
            if (! isset($sourceCategoryMap[$sid])) continue;
            $clean = preg_replace('/\D/', '', (string) $raw);
            $amt = $clean === '' ? 0.0 : (float) $clean;
            if ($amt > 0) {
                $snapshot[$sid] = $amt;
            }
        }

        $period->source_opening_balances = $snapshot;
        $period->opening_balance         = array_sum($snapshot);
        $period->save();

        // Upsert account number per source (Bank → no rekening, E-Wallet → no HP).
        // Hanya simpan kalau ada cabang yang valid & source kategorinya bank/ewallet.
        if ($period->raw_branch_id) {
            foreach ((array) $request->input('accounts', []) as $sid => $acct) {
                $sid = (int) $sid;
                $acct = trim((string) $acct);
                if ($acct === '' || ! isset($sourceCategoryMap[$sid])) continue;
                if (! in_array($sourceCategoryMap[$sid], [FundSource::CAT_BANK, FundSource::CAT_EWALLET], true)) continue;
                FundSourceBranchAccount::upsertFor(
                    (string) $period->raw_branch_id,
                    $sid,
                    $acct,
                );
            }
        }

        return redirect()
            ->route('pettycash', $this->preserveParams($kind))
            ->with('success', 'Setoran awal sumber dana berhasil disimpan.');
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
            'period_id'        => 'required|integer|exists:' . $tableName . ',id',
            'trx_date'         => 'required|date',
            'from_source_id'   => 'required|integer|exists:fund_sources,id',
            'to_source_id'     => 'required|integer|exists:fund_sources,id|different:from_source_id',
            'amount'           => 'required|numeric|min:1',
            'description'      => 'nullable|string|max:200',
            'to_account_number' => 'nullable|string|max:50',
        ], [
            'to_source_id.different' => 'Sumber tujuan tidak boleh sama dengan sumber asal.',
        ]);

        $period = $periodClass::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);
        $this->abortIfBranchMismatch($request, $period);

        // Cek saldo asal cukup
        $fromBalance = $this->computeSourceBalance($period, (int) $data['from_source_id'], $trxClass);
        if ($fromBalance < (float) $data['amount']) {
            return redirect()
                ->route('pettycash', $this->preserveParams($kind))
                ->with('error', 'Saldo sumber asal tidak cukup untuk ditransfer (saldo: Rp ' . number_format($fromBalance, 0, ',', '.') . ').');
        }

        $fromSource = FundSource::findOrFail($data['from_source_id']);
        $toSource   = FundSource::findOrFail($data['to_source_id']);
        $ref        = 'TRF-' . $period->id . '-' . time() . '-' . random_int(100, 999);
        $note       = trim((string) ($data['description'] ?? ''));

        // Pilih verb sesuai semantik arah dana:
        //   - tujuan = Cash       → "Tarik tunai" (cairin saldo Bank/E-Wallet)
        //   - asal   = Cash       → "Deposit"    (setor tunai ke rekening/e-wallet)
        //   - selain itu          → "Transfer"   (mis: Bank ↔ Bank, Bank ↔ E-Wallet)
        $isCashOut = $toSource->category   === FundSource::CAT_CASH;
        $isDeposit = $fromSource->category === FundSource::CAT_CASH && ! $isCashOut;
        $verb      = $isCashOut ? 'Tarik tunai' : ($isDeposit ? 'Deposit' : 'Transfer');

        // No rek / telp tujuan opsional — sama mekanisme dengan top up.
        // Kalau user isi & tujuan = Bank / E-Wallet, upsert ke per-branch.
        $toAcct = isset($data['to_account_number']) ? trim((string) $data['to_account_number']) : '';
        if ($toAcct !== '' && $period->raw_branch_id
            && in_array($toSource->category, [FundSource::CAT_BANK, FundSource::CAT_EWALLET], true)
        ) {
            FundSourceBranchAccount::upsertFor(
                (string) $period->raw_branch_id,
                (int) $toSource->id,
                $toAcct,
            );
        }

        $descOut = $verb . ' ke ' . $toSource->name   . ($note !== '' ? ' — ' . $note : '');
        $descIn  = $verb . ' dari ' . $fromSource->name . ($note !== '' ? ' — ' . $note : '');

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

        $msg = $verb . ' Rp ' . number_format((float) $data['amount'], 0, ',', '.')
            . ' dari ' . $fromSource->name . ' ke ' . $toSource->name . ' berhasil dicatat.';

        return redirect()
            ->route('pettycash', $this->preserveParams($kind))
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
        $this->abortIfBranchMismatch($request, $period);

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
            ->route('pettycash', $this->preserveParams($kind))
            ->with('success', $msg);
    }

    /**
     * POST /pettycash/source/destroy?tab={kind}
     *
     * Hapus seluruh data 1 sumber dana di periode aktif:
     *   - Soft delete semua transaksi yang fund_source_id-nya = source ini
     *   - Soft delete sibling kaki transfer (rows lain dengan ref_no yang sama)
     *   - Buang entry source ini dari source_opening_balances snapshot
     *   - Re-sync opening_balance scalar = sum(snapshot)
     *
     * Dipakai untuk reset data sumber dana spesifik dalam periode (mis: salah
     * migrasi, salah input setoran awal). Tidak menghapus master fund source.
     */
    public function destroySource(Request $request)
    {
        $kind        = $this->kind($request);
        $periodClass = $this->periodClass($kind);
        $trxClass    = $this->trxClass($kind);

        $tableName = (new $periodClass)->getTable();
        $data = $request->validate([
            'period_id'      => 'required|integer|exists:' . $tableName . ',id',
            'fund_source_id' => 'required|integer|exists:fund_sources,id',
        ]);

        $period = $periodClass::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);
        $this->abortIfBranchMismatch($request, $period);

        $sourceId = (int) $data['fund_source_id'];
        $now      = now();

        // Step 1: kumpulkan ref_no dari row transfer yang melibatkan source ini.
        // Dipakai di step 3 untuk hapus pasangan kaki transfer-nya.
        $refNos = $trxClass::active()
            ->forPeriod($period->id)
            ->where('fund_source_id', $sourceId)
            ->where('is_transfer', 1)
            ->whereNotNull('ref_no')
            ->pluck('ref_no')
            ->all();

        // Step 2: soft-delete semua transaksi yang langsung pakai source ini.
        $trxClass::active()
            ->forPeriod($period->id)
            ->where('fund_source_id', $sourceId)
            ->update(['deleted_at' => $now]);

        // Step 3: soft-delete sibling kaki transfer (source LAIN dgn ref_no sama).
        if (! empty($refNos)) {
            $trxClass::active()
                ->forPeriod($period->id)
                ->whereIn('ref_no', $refNos)
                ->where('is_transfer', 1)
                ->update(['deleted_at' => $now]);
        }

        // Step 4: buang entry source dari snapshot opening, re-sync scalar.
        $snapshot = $period->source_opening_balances ?? [];
        if (! is_array($snapshot)) $snapshot = [];
        unset($snapshot[$sourceId]);
        $period->source_opening_balances = $snapshot;
        $period->opening_balance         = array_sum($snapshot);
        $period->save();

        return redirect()
            ->route('pettycash', $this->preserveParams($kind))
            ->with('success', 'Data sumber dana di periode ini berhasil dihapus.');
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
        $this->abortIfBranchMismatch($request, $period);
        if (! $period->is_closed) {
            $net = (float) $trxClass::netForPeriod($period->id);
            $period->update([
                'closing_balance' => (float) $period->opening_balance + $net,
                'is_closed'       => 1,
            ]);
        }

        return redirect()
            ->route('pettycash', $this->preserveParams($kind))
            ->with('success', 'Periode ditutup.');
    }

    /* ───────── Internal ───────── */

    private function buildRows($period, $transactions, $fundSources = null, ?string $startDate = null, ?string $endDate = null, array $branchAccountMap = []): array
    {
        // Opening per source = single source of truth (snapshot JSON).
        // Self-heal di openingPerSourceResolved() juga sync scalar
        // opening_balance, jadi setelah call ini scalar & snapshot pasti
        // konsisten. Pakai sum snapshot sebagai opening total supaya
        // saldo akhir di summary cards = sum saldo per source.
        $openingPerSource = $period->openingPerSourceResolved();
        $opening = ! empty($openingPerSource)
            ? array_sum($openingPerSource)
            : (float) $period->opening_balance;
        $saldo   = $opening;
        $totalIn = 0.0;
        $totalOut = 0.0;

        // Inisialisasi breakdown saldo per source.
        $cashSourceId = null;
        $sourceMeta   = [];
        if ($fundSources) {
            foreach ($fundSources as $fs) {
                $sourceMeta[$fs->id] = [
                    'id'             => $fs->id,
                    'category'       => $fs->category,
                    'name'           => $fs->name,
                    'brand_color'    => $fs->effectiveBrandColor(),
                    'short_label'    => $fs->effectiveShortLabel(),
                    'logo_url'       => $fs->logo_url,
                    'account_number' => $branchAccountMap[$fs->id] ?? null,
                    'in'             => 0.0,
                    'out'            => 0.0,
                    'balance'        => 0.0,
                ];
                if ($cashSourceId === null && $fs->category === \App\Models\FundSource::CAT_CASH) {
                    $cashSourceId = $fs->id;
                }
            }
        }
        $uncategorized = ['in' => 0.0, 'out' => 0.0, 'balance' => 0.0];

        // Distribusi opening ke per-source (pakai $openingPerSource yang
        // sudah di-resolve di atas). Backward compat: kalau snapshot
        // kosong tapi scalar non-zero (data lama pre-fund-source),
        // dump ke Cash supaya saldo total tidak hilang.
        if (! empty($openingPerSource)) {
            foreach ($openingPerSource as $sid => $amt) {
                $amt = (float) $amt;
                if (isset($sourceMeta[$sid])) {
                    $sourceMeta[$sid]['balance'] += $amt;
                } else {
                    $uncategorized['balance'] += $amt;
                }
            }
        } elseif ($opening != 0.0) {
            if ($cashSourceId !== null) {
                $sourceMeta[$cashSourceId]['balance'] += $opening;
            } else {
                $uncategorized['balance'] += $opening;
            }
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
                'is_composite'  => false,
                'cash_in'       => $cashIn,
                'cash_out'      => $cashOut,
                'saldo'         => $saldo,
                'ref_no'        => $t->ref_no,
                'source_name'   => $fs?->name,
                'source_cat'    => $fs?->category,
                'source_color'  => $fs ? $fs->effectiveBrandColor() : null,
                'source_short'  => $fs ? $fs->effectiveShortLabel() : null,
                'source_logo'   => $fs?->logo_url,
                'children'      => [],
            ];
        }

        // Collapse transfer pair (2 rows ref_no sama: OUT dari A + IN ke B)
        // jadi 1 composite row supaya tabel tidak penuh duplikat. Detail-
        // nya disimpan sebagai children — di view bisa di-expand on click.
        // Posisi composite di tabel = posisi row OUT-nya (yang pertama
        // muncul setelah sort by trx_date, id).
        $rows = $this->collapseTransferPairs($rows);

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
                'id'             => null,
                'category'       => null,
                'name'           => \App\Models\FundSource::UNCATEGORIZED_LABEL,
                'brand_color'    => '#94a3b8',
                'short_label'    => '?',
                'logo_url'       => null,
                'account_number' => null,
            ]);
        }

        return [$rows, $summary, $sourceBalances];
    }

    /**
     * Gabungkan baris pasangan transfer (ref_no sama) jadi 1 composite
     * row. Properti penting:
     *   - source_from / source_to : badge gabungan (mis. "BSI → Cash")
     *   - description gabungan: "Tarik tunai BSI → Cash" / "Transfer A → B"
     *   - cash_in & cash_out tetap di-tampilkan dari masing-masing kaki
     *     (untuk visual "ada pergerakan 50k" walau net 0)
     *   - children: 2 baris asli (untuk dropdown detail di view)
     *   - is_composite = true
     *
     * Single-leg transfer (mis: lawan-nya sudah dihapus orphan) akan
     * di-render normal (tidak di-collapse).
     */
    private function collapseTransferPairs(array $rows): array
    {
        // Index baris transfer per ref_no
        $byRef = [];
        foreach ($rows as $i => $r) {
            if ($r->is_transfer && $r->ref_no) {
                $byRef[$r->ref_no][] = $i;
            }
        }

        // Build composite + buang index baris yang di-merge
        $dropIndices = [];
        $composites  = []; // first-index => composite obj
        foreach ($byRef as $ref => $idxs) {
            if (count($idxs) < 2) continue; // orphan — biarkan

            // Sort 2 baris: OUT di index pertama, IN di kedua
            usort($idxs, function ($a, $b) use ($rows) {
                $cmp = $rows[$b]->cash_out <=> $rows[$a]->cash_out;
                if ($cmp !== 0) return $cmp;
                return $a <=> $b;
            });
            $outRow = $rows[$idxs[0]];
            $inRow  = $rows[$idxs[1]];

            $isCashOut    = $inRow->source_cat  === \App\Models\FundSource::CAT_CASH;
            $isDeposit    = $outRow->source_cat === \App\Models\FundSource::CAT_CASH && ! $isCashOut;
            $verb         = $isCashOut ? 'Tarik tunai' : ($isDeposit ? 'Deposit' : 'Transfer');
            $descriptionA = $verb . ': ' . $outRow->source_name . ' → ' . $inRow->source_name;

            // Kalau user tambahin keterangan (mis: "konsolidasi saldo"),
            // ekstrak dari description anak — ada di format "... — note".
            // Kalau ada, append ke description composite.
            $note = '';
            if (preg_match('/ — (.+)$/', (string) $outRow->description, $m)) {
                $note = trim($m[1]);
            }
            if ($note !== '') $descriptionA .= ' — ' . $note;

            // Composite ngewarisin id dari OUT row (untuk action hapus —
            // controller destroy() pakai ref_no kalau row is_transfer,
            // jadi cukup salah satu id).
            $composite = (object) [
                'id'            => $outRow->id,
                'trx_date'      => $outRow->trx_date,
                'description'   => $descriptionA,
                'is_topup'      => false,
                'is_transfer'   => true,
                'is_composite'  => true,
                'cash_in'       => $inRow->cash_in,
                'cash_out'      => $outRow->cash_out,
                'saldo'         => $outRow->saldo, // sama untuk kedua kaki
                'ref_no'        => $ref,
                // Source utama composite = OUT (asal) — dipakai badge
                // primary, sementara source_to dipakai untuk panah → tujuan.
                'source_name'   => $outRow->source_name,
                'source_cat'    => $outRow->source_cat,
                'source_color'  => $outRow->source_color,
                'source_short'  => $outRow->source_short,
                'source_logo'   => $outRow->source_logo,
                'source_to'     => (object) [
                    'name'  => $inRow->source_name,
                    'cat'   => $inRow->source_cat,
                    'color' => $inRow->source_color,
                    'short' => $inRow->source_short,
                    'logo'  => $inRow->source_logo,
                ],
                'children'      => [$outRow, $inRow],
            ];

            $composites[$idxs[0]] = $composite;
            $dropIndices[$idxs[1]] = true; // IN row di-drop (digantikan composite di posisi OUT)
        }

        $result = [];
        foreach ($rows as $i => $r) {
            if (isset($dropIndices[$i])) continue;
            if (isset($composites[$i])) {
                $result[] = $composites[$i];
                continue;
            }
            $result[] = $r;
        }

        return $result;
    }

    private function abortIfReadOnly($period): void
    {
        if ($period->is_closed) {
            abort(403, 'Periode sudah ditutup (read-only).');
        }
    }

    /**
     * Pastikan periode yang sedang dimodifikasi memang milik cabang yang
     * boleh diakses user saat ini:
     *   - Sales (session('raw_branch_id')) → period.raw_branch_id harus
     *     == session-nya.
     *   - Admin → period.raw_branch_id harus salah satu yang ada (sudah
     *     dijamin oleh foreign key, jadi pass).
     * Melindungi dari user yang menebak period_id cabang lain via form
     * tampering.
     */
    private function abortIfBranchMismatch(Request $request, $period): void
    {
        $session = session('raw_branch_id');
        if ($session && (string) $period->raw_branch_id !== (string) $session) {
            abort(403, 'Anda tidak boleh mengubah data cabang lain.');
        }
    }

    /**
     * Hitung saldo current untuk satu fund_source di periode tertentu.
     * Opening per source diambil dari snapshot JSON
     * source_opening_balances (di-resolve via self-heal kalau NULL).
     * Fallback legacy: kalau snapshot kosong, opening scalar di-dump ke
     * Cash (perilaku lama).
     */
    private function computeSourceBalance($period, int $fundSourceId, string $trxClass): float
    {
        $openingPerSource = $period->openingPerSourceResolved();
        if (! empty($openingPerSource)) {
            $balance = (float) ($openingPerSource[$fundSourceId] ?? 0.0);
        } else {
            // Legacy fallback — opening scalar dianggap di Cash
            $cashId = FundSource::where('category', FundSource::CAT_CASH)->value('id');
            $balance = ($cashId && (int) $cashId === $fundSourceId)
                ? (float) $period->opening_balance
                : 0.0;
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
