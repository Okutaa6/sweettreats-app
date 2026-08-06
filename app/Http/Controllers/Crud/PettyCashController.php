<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\FundSource;
use App\Models\FundSourceBranchAccount;
use App\Models\PettyCashPeriod;
use App\Models\PettyCashTransaction;
use App\Models\RawBranch;
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
 * Kas kecil per cabang dengan model imprest (dana tetap, default 1.500.000).
 * Tabel: petty_cash_periods + petty_cash_transactions.
 *
 * Branch scoping:
 *   - Sales (session('raw_branch_id') terisi) → lock ke cabangnya, tidak
 *     bisa ganti via ?branch=.
 *   - Admin (canSeeAllBranches()) → dropdown cabang, default = cabang
 *     pertama urut nama. Pilihan persisted via ?branch=<uuid>.
 */
class PettyCashController extends Controller
{
    private const KIND          = 'petty_cash';
    private const LABEL         = 'Petty Cash';
    private const DEFAULT_FLOAT = 1500000;

    /* ───────── Helpers ───────── */

    private function kind(Request $request): string
    {
        return self::KIND;
    }

    private function periodClass(string $kind): string { return PettyCashPeriod::class; }
    private function trxClass(string $kind):    string { return PettyCashTransaction::class; }
    private function defaultFloat(string $kind): int   { return self::DEFAULT_FLOAT; }

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
     * Float default (imprest), dengan override per cabang dari
     * raw_branch.petty_cash_default_float kalau di-set.
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

    /**
     * Gate untuk action yang mengubah data petty cash. Role Finance hanya
     * view-only — tidak boleh tambah/edit/hapus transaksi, top-up, mutasi,
     * tutup periode, dll. Panggil di awal setiap action mutating.
     */
    private function requireWriteAccess(): void
    {
        if (! canEditPettyCash()) {
            abort(403, 'Role Finance hanya bisa melihat data Petty Cash (view-only). Mutasi data wajib dilakukan dari role cabang / admin.');
        }
    }

    /**
     * Gate khusus EDIT & HAPUS transaksi — dibatasi Super Admin (kebijakan
     * audit). Role lain boleh mencatat transaksi baru tapi tidak boleh
     * mengubah/menghapus yang sudah ada. Panggil di update() & destroy().
     */
    private function requireTrxEditor(): void
    {
        if (! isSuperAdmin()) {
            abort(403, 'Hanya Super Admin yang boleh mengedit atau menghapus transaksi petty cash.');
        }
    }

    /* ───────── Actions ───────── */

    /**
     * GET /pettycash
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

        // Validasi category terhadap CATEGORIES milik kind aktif — kalau kind
        // diganti dan key kategori lama tidak ada di kind baru, abaikan.
        $categoryOptions = $trxClass::CATEGORIES;
        $category        = (string) $request->get('category', '');
        if ($category !== '' && ! array_key_exists($category, $categoryOptions)) {
            $category = '';
        }

        $period = $periodClass::ensurePeriod(
            $year,
            $month,
            $this->defaultFloatForBranch($kind, $branchId),
            $branchId,
        );

        $fundSources        = FundSource::active()->orderBy('sort_order')->orderBy('name')->get();
        $fundSourcesGrouped = $fundSources->groupBy('category');

        // Filter wallet — validate ke fund_sources aktif. Invalid → diabaikan.
        $fundSourceId = (int) $request->get('fund_source_id', 0);
        if ($fundSourceId > 0 && ! $fundSources->contains('id', $fundSourceId)) {
            $fundSourceId = 0;
        }

        $transactions = $trxClass::active()
            ->with('fundSource')
            ->forPeriod($period->id)
            ->when($search !== '', function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%");
            })
            ->when($category !== '', function ($q) use ($category) {
                $q->where('category', $category);
            })
            ->when($fundSourceId > 0, function ($q) use ($fundSourceId) {
                $q->where('fund_source_id', $fundSourceId);
            })
            ->orderBy('trx_date')
            ->orderBy('id')
            ->get();

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
        // Role Finance view-only: ikut path read-only yang sudah ada supaya
        // semua tombol write otomatis hilang di view, modal, dan filter.
        $isFinanceViewOnly = ! canEditPettyCash();
        $isReadOnly        = (bool) $period->is_closed || ! $isCurrentMonth || $isFinanceViewOnly;

        // Edit & hapus transaksi dibatasi Super Admin saja (kebijakan audit):
        // role lain (Admin General/Backup, Sales) tetap bisa MENCATAT transaksi
        // baru, tapi tidak boleh mengubah/menghapus yang sudah ada. Terpisah dari
        // $isReadOnly supaya fitur tulis lain (tambah pengeluaran, top up) tetap
        // jalan. Guard yang sama di-enforce server-side di update() & destroy().
        $showTrxActions = ! $isReadOnly && isSuperAdmin();

        $tabs = [[
            'kind'   => $kind,
            'label'  => self::LABEL,
            'active' => true,
        ]];

        // Branch dropdown data — kosong untuk Sales (di-lock), berisi
        // semua cabang untuk admin.
        $sessionBranch = session('raw_branch_id');
        $branches      = $sessionBranch ? collect() : RawBranch::orderBy('name')->get();
        $activeBranch  = $branchId ? RawBranch::find($branchId) : null;

        // Prefill nama pemohon di modal "Ajukan Top Up" (boleh diedit di form).
        $requesterName = (string) (\App\Models\Users::whereKey(session('id'))->value('nama') ?? '');

        // Status pengajuan top-up Sales untuk cabang aktif: kalau ada yang
        // pending, _summary_cards bisa tampil badge "menunggu Finance".
        $pendingTopupCount = $branchId
            ? \App\Models\TopupRequest::pending()->forBranch($branchId)->count()
            : 0;

        return view('crud.pettycash.index', compact(
            'period', 'rows', 'summary', 'sourceBalances',
            'year', 'month', 'bulanLabel',
            'startDate', 'endDate', 'monthStartStr', 'monthEndStr',
            'search', 'category', 'categoryOptions', 'fundSourceId',
            'isCurrentMonth', 'isReadOnly', 'isFinanceViewOnly', 'showTrxActions',
            'kind', 'tabs', 'fundSources', 'fundSourcesGrouped',
            'branches', 'activeBranch', 'branchId', 'sessionBranch',
            'branchAccountMap', 'pendingTopupCount', 'requesterName'
        ));
    }

    /**
     * GET /pettycash/export?tab={kind}&branch=&start_date=&end_date=&category=&q=&fund_source_id=
     *
     * Export CSV transaksi periode aktif untuk cabang aktif. Hanya export
     * baris TOP UP & PENGELUARAN — transfer / tarik tunai di-skip
     * (internal movement, bukan cash flow nyata). Ikut filter yang sama
     * dengan view index: date range, kategori, search keterangan, wallet.
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

        // Filter tambahan — match dengan filter di view index supaya
        // hasil export = data yang user lihat di layar.
        $search       = trim((string) $request->get('q', ''));
        $category     = (string) $request->get('category', '');
        $categoryMap  = $trxClass::CATEGORIES;
        if ($category !== '' && ! array_key_exists($category, $categoryMap)) {
            $category = '';
        }
        $fundSourceId = (int) $request->get('fund_source_id', 0);
        if ($fundSourceId > 0 && ! FundSource::whereKey($fundSourceId)->exists()) {
            $fundSourceId = 0;
        }
        $walletName = $fundSourceId > 0
            ? (string) FundSource::whereKey($fundSourceId)->value('name')
            : '';

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
            ->when($search !== '', function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%");
            })
            ->when($category !== '', function ($q) use ($category) {
                $q->where('category', $category);
            })
            ->when($fundSourceId > 0, function ($q) use ($fundSourceId) {
                $q->where('fund_source_id', $fundSourceId);
            })
            ->orderBy('trx_date')
            ->orderBy('id')
            ->get();

        $branch = $branchId ? RawBranch::find($branchId) : null;
        $branchSlug = $branch
            ? preg_replace('/[^a-z0-9]+/i', '-', strtolower($branch->name))
            : 'all';
        $kindSlug   = 'petty-cash';
        $kindLabel  = self::LABEL;
        $filename   = sprintf('%s_%s_%s_%s.xlsx', $kindSlug, $branchSlug, $startDate, $endDate);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($kindLabel, 0, 31)); // tab name max 31 char

        // ─── Info block (atas) ───
        $sheet->setCellValue('A1', 'Modul');   $sheet->setCellValue('B1', $kindLabel);
        $sheet->setCellValue('A2', 'Cabang');  $sheet->setCellValue('B2', $branch ? $branch->name : '-');
        $sheet->setCellValue('A3', 'Periode'); $sheet->setCellValue('B3', $startDate . ' s/d ' . $endDate);

        // Rangkum filter aktif (kalau ada) jadi 1 baris — biar user tahu
        // export ini hasil dari filter mana.
        $filterParts = [];
        if ($walletName !== '')      $filterParts[] = 'Wallet: ' . $walletName;
        if ($category !== '')        $filterParts[] = 'Kategori: ' . ($categoryMap[$category] ?? $category);
        if ($search !== '')          $filterParts[] = 'Cari: "' . $search . '"';
        $sheet->setCellValue('A4', 'Filter');
        $sheet->setCellValue('B4', empty($filterParts) ? '-' : implode(' · ', $filterParts));
        $sheet->getStyle('A1:A4')->getFont()->setBold(true);

        // ─── Header tabel (row 5) ───
        $headerRow = 5;
        $headerCols = [
            'A' => 'Tanggal', 'B' => 'Jenis', 'C' => 'Wallet',
            'D' => 'Kategori',
            'E' => 'Keterangan', 'F' => 'Cash In', 'G' => 'Cash Out', 'H' => 'Saldo',
        ];
        foreach ($headerCols as $col => $h) {
            $sheet->setCellValue($col . $headerRow, $h);
        }
        $sheet->getStyle("A{$headerRow}:H{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1E40AF']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        // Map kategori key → label untuk tampilan di Excel.
        $categoryMap = $trxClass::CATEGORIES;

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
        $sheet->setCellValue("E{$row}", 'Saldo awal periode');
        $sheet->setCellValueExplicit("H{$row}", $opening, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '475569']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
        ]);
        $sheet->getStyle("A{$row}:H{$row}")->getBorders()->getAllBorders()
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

            $catLabel = $t->category && isset($categoryMap[$t->category])
                ? $categoryMap[$t->category]
                : '-';
            $sheet->setCellValue("A{$row}", $date);
            $sheet->setCellValue("B{$row}", $t->is_topup ? 'Top up' : 'Pengeluaran');
            $sheet->setCellValue("C{$row}", $fs ? $fs->name : '-');
            $sheet->setCellValue("D{$row}", $catLabel);
            $sheet->setCellValue("E{$row}", (string) $t->description);
            if ($cashIn > 0)  $sheet->setCellValueExplicit("F{$row}", $cashIn,  \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            if ($cashOut > 0) $sheet->setCellValueExplicit("G{$row}", $cashOut, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("H{$row}", $saldo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

            // Highlight row Top up dengan background kuning + teks bold —
            // override zebra striping. Pengeluaran biasa pakai zebra normal.
            if ($t->is_topup) {
                $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '78350F']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF59D']],
                ]);
            } elseif ($i % 2 === 1) {
                $sheet->getStyle("A{$row}:H{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('EFF6FF');
            }
            // Border tipis di semua sel
            $sheet->getStyle("A{$row}:H{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DBEAFE'));

            $row++;
        }

        $lastDataRow = $row - 1;

        // Number format ribuan untuk kolom Cash In/Out/Saldo (termasuk opening row)
        $sheet->getStyle("F" . ($headerRow + 1) . ":H{$lastDataRow}")
            ->getNumberFormat()->setFormatCode('#,##0');

        // ─── Footer TOTAL & SALDO AKHIR ───
        $totalRow = $lastDataRow + 2;
        $sheet->setCellValue("E{$totalRow}", 'TOTAL');
        $sheet->setCellValueExplicit("F{$totalRow}", $totalIn,  \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("G{$totalRow}", $totalOut, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("H{$totalRow}", $saldo,    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->getStyle("E{$totalRow}:H{$totalRow}")->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2563EB']]],
        ]);
        $sheet->getStyle("F{$totalRow}:H{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');

        // Auto-width semua kolom
        foreach (['A','B','C','D','E','F','G','H'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Freeze pane di bawah header tabel supaya saat scroll, header tetap kelihatan
        $sheet->freezePane('A' . ($headerRow + 1));

        // Auto-filter di header tabel
        $sheet->setAutoFilter("A{$headerRow}:H{$headerRow}");

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
     *
     * Mendukung upload bukti (struk/bon/transfer) via field 'attachment'.
     * File disimpan di public/uploads/expense-receipts/{YYYY}/{MM}/{uuid}.{ext}.
     */
    public function store(Request $request)
    {
        $this->requireWriteAccess();
        $kind = $this->kind($request);
        $periodClass = $this->periodClass($kind);
        $trxClass    = $this->trxClass($kind);

        $tableName = (new $periodClass)->getTable();
        $categoryKeys = array_keys($trxClass::CATEGORIES);
        $data = $request->validate([
            'period_id'      => 'required|integer|exists:' . $tableName . ',id',
            'trx_date'       => 'required|date',
            'description'    => 'required|string|max:255',
            'category'       => 'required|in:' . implode(',', $categoryKeys),
            'amount'         => 'required|numeric|min:1|max:' . \App\Models\PettyCashTransaction::MAX_AMOUNT,
            'fund_source_id' => 'required|integer|exists:fund_sources,id',
            'ref_no'         => 'nullable|string|max:50',
            // Bukti pengeluaran opsional: gambar (jpg/png/webp) atau PDF.
            // Max 5MB — cukup besar untuk foto HP modern, kecil untuk storage.
            'attachment'     => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], [
            'amount.max' => 'Nominal maksimal Rp 50.000.000.',
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

        $attachmentPath = $this->storeAttachment($request, $data['trx_date']);

        $trxClass::create([
            'period_id'       => $period->id,
            'trx_date'        => $data['trx_date'],
            'description'     => $data['description'],
            'category'        => $data['category'],
            'direction'       => $trxClass::DIR_OUT,
            'amount'          => $data['amount'],
            'fund_source_id'  => $data['fund_source_id'],
            'is_topup'        => 0,
            'ref_no'          => $data['ref_no'] ?? null,
            'attachment_path' => $attachmentPath,
            'created_by'      => session('id'),
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
        $this->requireWriteAccess();
        $kind = $this->kind($request);
        $periodClass = $this->periodClass($kind);
        $trxClass    = $this->trxClass($kind);

        $tableName = (new $periodClass)->getTable();
        $data = $request->validate([
            'period_id'      => 'required|integer|exists:' . $tableName . ',id',
            'trx_date'       => 'required|date',
            'description'    => 'nullable|string|max:255',
            'amount'         => 'required|numeric|min:1|max:' . \App\Models\PettyCashTransaction::MAX_AMOUNT,
            'fund_source_id' => 'required|integer|exists:fund_sources,id',
            'ref_no'         => 'nullable|string|max:50',
            'account_number' => 'nullable|string|max:50',
            // Flag opsional: 1 = top up dana baru (default), 0 = setor cash
            // langsung yang TIDAK dihitung sebagai top up (mis. kembalian
            // kasbon karyawan). Tetap Cash IN ke saldo, tapi summary "Total
            // Top Up" tidak menambahkannya.
            'is_topup'       => 'nullable|boolean',
            'attachment'     => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], [
            'amount.max' => 'Nominal maksimal Rp 50.000.000.',
        ]);

        $period = $periodClass::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);
        $this->abortIfBranchMismatch($request, $period);

        $source = FundSource::findOrFail($data['fund_source_id']);
        $attachmentPath = $this->storeAttachment($request, $data['trx_date']);

        // Mode:
        //   is_topup=1 (default) → top up biasa, dihitung di "Total Top Up"
        //   is_topup=0           → setor cash netral (mis. kembalian kasbon),
        //                          tidak menambah angka "Total Top Up" walau
        //                          saldo fisik tetap naik.
        $isTopup = ! array_key_exists('is_topup', $data) || (bool) $data['is_topup'];
        $successMsg = $isTopup
            ? 'Top up saldo berhasil dicatat.'
            : 'Setoran cash berhasil dicatat (tidak dihitung sebagai top up).';

        $service = new \App\Services\PettyCashTopupService();
        $service->maybeUpsertAccount($period, $source, $data['account_number'] ?? null);
        $service->createTopupTransaction(
            $period,
            $source,
            (float) $data['amount'],
            $data['description'] ?? null,
            $attachmentPath,
            (string) session('id'),
            $isTopup,
            $data['trx_date'],
            $data['ref_no'] ?? null,
        );

        return redirect()
            ->route('pettycash', $this->preserveParams($kind))
            ->with('success', $successMsg);
    }

    /**
     * POST /pettycash/topup-request
     *
     * Submit pengajuan top-up. Tidak langsung create transaksi pettycash —
     * hanya insert ke topup_requests (status pending) + broadcast notif ke
     * admin via dashboard. Finance/admin yang approve di dashboard yang
     * akan create transaksi pettycash sebenarnya.
     *
     * Resolve cabang:
     *   - Sales (session raw_branch_id terisi) → pakai cabang session
     *   - Admin (canSeeAllBranches)            → pakai branch_id dari form
     *     (mereka pilih cabang via ?branch=X di URL pettycash). Untuk
     *     testing — dalam praktik admin lebih sering top-up langsung.
     */
    public function requestTopup(Request $request)
    {
        $this->requireWriteAccess();
        $sessionBranch = session('raw_branch_id');

        if ($sessionBranch) {
            $branchId = (string) $sessionBranch;
        } elseif (canSeeAllBranches()) {
            $submitted = (string) $request->input('branch_id', '');
            if ($submitted === '' || ! RawBranch::whereKey($submitted)->exists()) {
                abort(422, 'Cabang tujuan top-up tidak valid. Buka halaman pettycash cabang dulu.');
            }
            $branchId = $submitted;
        } else {
            abort(403, 'Anda belum ter-assign ke cabang manapun.');
        }

        $data = $request->validate([
            'fund_source_id' => 'required|integer|exists:fund_sources,id',
            'amount'         => 'required|numeric|min:1|max:' . \App\Models\PettyCashTransaction::MAX_AMOUNT,
            'description'    => 'nullable|string|max:255',
            'requester_name' => 'nullable|string|max:255',
            'needed_by'      => 'nullable|date|after_or_equal:today',
            'is_urgent'      => 'nullable|boolean',
        ], [
            'amount.max'              => 'Nominal maksimal Rp 50.000.000.',
            'needed_by.after_or_equal' => 'Tanggal kebutuhan tidak boleh di masa lalu.',
        ]);

        // Nama pemohon: pakai yang diketik di form, fallback ke nama user login.
        $requesterName = trim((string) ($data['requester_name'] ?? ''));
        if ($requesterName === '') {
            $requesterName = (string) (\App\Models\Users::whereKey(session('id'))->value('nama') ?? '');
        }
        $isUrgent = (bool) ($data['is_urgent'] ?? false);

        $req = \App\Models\TopupRequest::create([
            'raw_branch_id'    => $branchId,
            'requested_by'     => (string) session('id'),
            'requester_name'   => $requesterName !== '' ? $requesterName : null,
            'fund_source_id'   => (int) $data['fund_source_id'],
            'amount_requested' => (float) $data['amount'],
            'description'      => $data['description'] ?? null,
            'needed_by'        => $data['needed_by'] ?? null,
            'is_urgent'        => $isUrgent,
            'attachment_path'  => null,
            'status'           => \App\Models\TopupRequest::STATUS_PENDING,
            'requested_at'     => now(),
        ]);

        // Broadcast notif ke admin yang punya akses dashboard. Admin
        // (canSeeAllBranches) otomatis lihat semua branch-scoped notifs via
        // AdminNotification::unreadForUser() extension.
        $branchName = RawBranch::whereKey($branchId)->value('name') ?: 'Cabang';
        $wallet     = FundSource::whereKey($data['fund_source_id'])->value('name') ?: 'wallet';
        $nominal    = number_format((float) $data['amount'], 0, ',', '.');
        \App\Models\AdminNotification::notify(
            'topup_request',
            ($isUrgent ? '[DARURAT] ' : '') . 'Pengajuan top up baru — ' . $branchName,
            $branchName . ' mengajukan top up Rp ' . $nominal . ' untuk ' . $wallet . '.'
                . ($isUrgent ? ' Ditandai DARURAT.' : ''),
            url('finance/pettycash'),
            $branchId,
        );

        return redirect()
            ->route('pettycash', ['branch' => $branchId])
            ->with('success', 'Pengajuan top up berhasil dikirim ke Finance.');
    }

    /**
     * POST /pettycash/account?tab={kind}
     *
     * Inline upsert nomor rekening (Bank) / nomor telepon (E-Wallet)
     * untuk pasangan (cabang, sumber dana) dari modal "Kelola Saldo".
     * Dipakai supaya user bisa lengkapi nomor yang belum di-set saat
     * setoran awal — tanpa harus melalui form top up.
     *
     * Hanya berlaku untuk source kategori Bank / E-Wallet (Cash di-reject).
     */
    public function saveAccount(Request $request)
    {
        $this->requireWriteAccess();
        $kind = $this->kind($request);
        $periodClass = $this->periodClass($kind);

        $tableName = (new $periodClass)->getTable();
        $data = $request->validate([
            'period_id'      => 'required|integer|exists:' . $tableName . ',id',
            'fund_source_id' => 'required|integer|exists:fund_sources,id',
            'account_number' => ['required', 'string', 'max:50', 'regex:/^[0-9]+$/'],
        ], [
            'account_number.regex' => 'Nomor hanya boleh berisi angka (tanpa spasi, huruf, atau tanda baca).',
        ]);

        $period = $periodClass::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);
        $this->abortIfBranchMismatch($request, $period);

        if (! $period->raw_branch_id) {
            return response()->json([
                'ok' => false,
                'message' => 'Periode tidak terikat ke cabang manapun.',
            ], 422);
        }

        $source = FundSource::findOrFail($data['fund_source_id']);
        if (! in_array($source->category, [FundSource::CAT_BANK, FundSource::CAT_EWALLET], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Hanya sumber Bank / E-Wallet yang punya nomor rekening/telepon.',
            ], 422);
        }

        $acct = trim($data['account_number']);
        FundSourceBranchAccount::upsertFor(
            (string) $period->raw_branch_id,
            (int) $source->id,
            $acct,
        );

        return response()->json([
            'ok'             => true,
            'account_number' => $acct,
            'label'          => $source->category === FundSource::CAT_BANK ? 'No. Rek' : 'No. HP',
        ]);
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
        $this->requireWriteAccess();
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
        $this->requireWriteAccess();
        $kind        = $this->kind($request);
        $periodClass = $this->periodClass($kind);
        $trxClass    = $this->trxClass($kind);

        $tableName = (new $periodClass)->getTable();
        $data = $request->validate([
            'period_id'        => 'required|integer|exists:' . $tableName . ',id',
            'trx_date'         => 'required|date',
            'from_source_id'   => 'required|integer|exists:fund_sources,id',
            'to_source_id'     => 'required|integer|exists:fund_sources,id|different:from_source_id',
            'amount'           => 'required|numeric|min:1|max:' . \App\Models\PettyCashTransaction::MAX_AMOUNT,
            'description'      => 'nullable|string|max:200',
            'to_account_number' => 'nullable|string|max:50',
            'attachment'       => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], [
            'to_source_id.different' => 'Sumber tujuan tidak boleh sama dengan sumber asal.',
            'amount.max'             => 'Nominal maksimal Rp 50.000.000.',
        ]);

        $period = $periodClass::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);
        $this->abortIfBranchMismatch($request, $period);

        $attachmentPath = $this->storeAttachment($request, $data['trx_date']);

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

        DB::transaction(function () use ($trxClass, $period, $data, $fromSource, $toSource, $descOut, $descIn, $ref, $attachmentPath) {
            // OUT dari source asal — attach bukti ditempel di sisi OUT
            // sehingga muncul di tabel pada baris pengeluaran dari source asal.
            $trxClass::create([
                'period_id'       => $period->id,
                'trx_date'        => $data['trx_date'],
                'description'     => $descOut,
                'direction'       => $trxClass::DIR_OUT,
                'amount'          => $data['amount'],
                'fund_source_id'  => $fromSource->id,
                'is_topup'        => 0,
                'is_transfer'     => 1,
                'ref_no'          => $ref,
                'attachment_path' => $attachmentPath,
                'created_by'      => session('id'),
            ]);
            // IN ke source tujuan — attach juga ditempel agar bukti
            // discoverable dari sisi tujuan (mis. kalau user lihat dari
            // wallet penerima).
            $trxClass::create([
                'period_id'       => $period->id,
                'trx_date'        => $data['trx_date'],
                'description'     => $descIn,
                'direction'       => $trxClass::DIR_IN,
                'amount'          => $data['amount'],
                'fund_source_id'  => $toSource->id,
                'is_topup'        => 0,
                'is_transfer'     => 1,
                'ref_no'          => $ref,
                'attachment_path' => $attachmentPath,
                'created_by'      => session('id'),
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
     * juga pasangannya + expense bundle yang berbagi ref_no — supaya saldo
     * tidak pincang. Expense leg (is_transfer=0, ref_no=TRF-…) dibuat oleh
     * shortcut "sekalian pengeluaran" di tab Tarik Tunai.
     */
    public function destroy(Request $request, $id)
    {
        $this->requireWriteAccess();
        $this->requireTrxEditor();
        $kind     = $this->kind($request);
        $trxClass = $this->trxClass($kind);

        $trx = $trxClass::active()->findOrFail($id);
        $period = $trx->period;
        $this->abortIfReadOnly($period);
        $this->abortIfBranchMismatch($request, $period);

        $now = now();
        if ($trx->is_transfer && $trx->ref_no) {
            // Hapus seluruh bundle yang share ref_no: 2 leg transfer +
            // (opsional) expense bundle. ref_no transfer pakai prefix "TRF-"
            // jadi tidak collide dgn ref_no manual user.
            $affected = $trxClass::active()
                ->where('ref_no', $trx->ref_no)
                ->update(['deleted_at' => $now]);
            $msg = $affected > 2
                ? 'Transfer + pengeluaran bundel dihapus (' . $affected . ' baris).'
                : 'Transfer dihapus (kedua sisi).';
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
     * PATCH /pettycash/transaction/{id}?tab={kind}
     *
     * Edit transaksi pengeluaran biasa. 3 guard:
     *   1. Periode tidak read-only (bulan berjalan & belum ditutup).
     *   2. Window 24 jam sejak created_at.
     *   3. Hanya pengeluaran biasa — TIDAK boleh edit transfer / top up
     *      (impact-nya ke balance multi-source lebih rumit, deletes-then-recreate).
     *
     * Yang boleh diubah: amount, description, category. Wallet (fund_source_id)
     * & tanggal (trx_date) TIDAK boleh diubah supaya audit/balance tetap stabil
     * (user delete+recreate kalau salah wallet/tanggal).
     */
    public function update(Request $request, $id)
    {
        $this->requireWriteAccess();
        $this->requireTrxEditor();
        $kind     = $this->kind($request);
        $trxClass = $this->trxClass($kind);

        $trx = $trxClass::active()->findOrFail($id);
        $period = $trx->period;
        $this->abortIfReadOnly($period);
        $this->abortIfBranchMismatch($request, $period);

        // Guard: hanya pengeluaran biasa.
        if ($trx->is_transfer || $trx->is_topup || $trx->direction !== $trxClass::DIR_OUT) {
            abort(403, 'Hanya pengeluaran biasa yang bisa diedit. Untuk transfer / top up, gunakan hapus & buat ulang.');
        }

        // Guard: window 24 jam sejak created_at.
        if ($trx->created_at && $trx->created_at->lt(now()->subHours(24))) {
            abort(403, 'Transaksi sudah lebih dari 24 jam — tidak bisa diedit lagi.');
        }

        $categoryKeys = array_keys($trxClass::CATEGORIES);
        $data = $request->validate([
            'amount'      => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
            'category'    => 'required|in:' . implode(',', $categoryKeys),
        ]);

        // Guard tambahan: nominal baru tidak boleh bikin saldo wallet asal jadi
        // negatif. Kasus: user input Rp 100k tadi, mau ubah ke Rp 500k tapi saldo
        // sekarang cuma Rp 300k.
        if ($trx->fund_source_id) {
            $currentBalance = $this->computeSourceBalance($period, (int) $trx->fund_source_id, $trxClass);
            // currentBalance sudah mempertimbangkan trx ini (out). Untuk cek
            // apakah amount baru fit, hitung "balance kalau trx ini dibatalkan":
            $balanceWithoutThis = $currentBalance + (float) $trx->amount;
            if ((float) $data['amount'] > $balanceWithoutThis) {
                return redirect()
                    ->route('pettycash', $this->preserveParams($kind))
                    ->with('error', sprintf(
                        'Nominal baru (Rp %s) melebihi saldo wallet yang tersedia (Rp %s).',
                        number_format((float) $data['amount'], 0, ',', '.'),
                        number_format($balanceWithoutThis, 0, ',', '.'),
                    ));
            }
        }

        $trx->update([
            'amount'      => $data['amount'],
            'description' => $data['description'],
            'category'    => $data['category'],
            'updated_by'  => session('id'),
            'edited_at'   => now(),
        ]);

        return redirect()
            ->route('pettycash', $this->preserveParams($kind))
            ->with('success', 'Transaksi berhasil diperbarui.');
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
        // Gate akses: hanya Super Admin & Admin General yang boleh hard-delete
        // data sumber dana di periode. Role lain mendapat versi locked di UI;
        // kalau coba bypass via DevTools, abort 403 di sini.
        if (! isSuperAdmin() && ! isAdminGeneral()) {
            abort(403, 'Fitur ini hanya untuk Super Admin / Admin General.');
        }

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
        $this->requireWriteAccess();
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

    /**
     * Simpan file bukti yang di-upload ke public/uploads/expense-receipts/{YYYY}/{MM}/{uuid}.{ext}.
     * Return path relatif dari public/ (untuk disimpan di kolom attachment_path), atau null
     * kalau file tidak ada. Folder dipartisi per YYYY/MM supaya tidak membludak.
     */
    private function storeAttachment(Request $request, string $trxDate, string $field = 'attachment'): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }
        $file = $request->file($field);
        $year  = date('Y', strtotime($trxDate));
        $month = date('m', strtotime($trxDate));
        $relDir = "uploads/expense-receipts/{$year}/{$month}";
        $absDir = public_path($relDir);
        if (! is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = \Illuminate\Support\Str::uuid() . '.' . $ext;
        $file->move($absDir, $filename);
        return "{$relDir}/{$filename}";
    }

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
                'id'              => $t->id,
                'trx_date'        => $t->trx_date,
                'description'     => $t->description,
                'category'        => $t->category ?? null,
                'is_topup'        => (bool) $t->is_topup,
                'is_transfer'     => $isTransfer,
                'is_composite'    => false,
                'cash_in'         => $cashIn,
                'cash_out'        => $cashOut,
                'saldo'           => $saldo,
                'ref_no'          => $t->ref_no,
                'attachment_path' => $t->attachment_path,
                'source_name'     => $fs?->name,
                'source_cat'      => $fs?->category,
                'source_color'    => $fs ? $fs->effectiveBrandColor() : null,
                'source_short'    => $fs ? $fs->effectiveShortLabel() : null,
                'source_logo'     => $fs?->logo_url,
                'source_id'       => $fs?->id,
                'created_at'      => $t->created_at,
                'edited_at'       => $t->edited_at ?? null,
                'children'        => [],
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
                'id'              => $outRow->id,
                'trx_date'        => $outRow->trx_date,
                'description'     => $descriptionA,
                'category'        => null,
                'is_topup'        => false,
                'is_transfer'     => true,
                'is_composite'    => true,
                'cash_in'         => $inRow->cash_in,
                'cash_out'        => $outRow->cash_out,
                'saldo'           => $outRow->saldo, // sama untuk kedua kaki
                'ref_no'          => $ref,
                // Attachment di-stempel di kedua kaki transfer (lihat transfer()),
                // jadi pakai salah satu — biasanya OUT row sebagai primary.
                'attachment_path' => $outRow->attachment_path ?? $inRow->attachment_path,
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
