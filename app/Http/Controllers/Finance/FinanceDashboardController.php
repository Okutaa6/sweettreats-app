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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

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

            // cashIn = SEMUA uang masuk non-transfer: top up maupun setoran
            // non-topup (pengembalian kasbon, setor cash, koreksi). Keduanya
            // sama-sama menggerakkan saldo, jadi kalau hanya top-up yang
            // dihitung, closing tak akan pernah cocok dengan opening + in − out.
            $cashIn       = 0.0;
            $cashOut      = 0.0;
            $lastActivity = null;
            foreach ($rows as $r) {
                if ($r->is_transfer) continue; // transfer internal — netral
                $amt = (float) $r->amount;
                if ($r->direction === PettyCashTransaction::DIR_IN) {
                    $cashIn += $amt;
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

        [$year, $month] = $this->resolveHistoryPeriod($request);

        $data       = $this->buildBranchHistory($request, $branch, $year, $month);
        $bulanLabel = $this->bulanLabel();
        $monthName  = $bulanLabel[$month] ?? '-';

        // Navigasi bulan sebelum/sesudah.
        $prevDate = Carbon::create($year, $month, 1)->subMonth();
        $nextDate = Carbon::create($year, $month, 1)->addMonth();

        return view('finance.pettycash.branch-history', array_merge($data, compact(
            'branch', 'year', 'month', 'monthName', 'bulanLabel', 'prevDate', 'nextDate'
        )));
    }

    /**
     * GET /finance/pettycash/branch/{branchId}/riwayat/export
     *
     * Export jejak transaksi (pengeluaran) 1 cabang + 1 bulan ke Excel. Baris
     * yang diexport = baris yang tampil di layar — filter kategori & pencarian
     * ikut terbawa. Gaya workbook mengikuti export di halaman petty cash:
     * blok info di atas, header berwarna, zebra striping, border tipis,
     * freeze pane + auto filter, dan baris TOTAL di bawah.
     */
    public function branchHistoryExport(Request $request, string $branchId)
    {
        $this->guard();

        $branch = RawBranch::find($branchId);
        if (! $branch) {
            abort(404, 'Cabang tidak ditemukan.');
        }

        [$year, $month] = $this->resolveHistoryPeriod($request);

        $data       = $this->buildBranchHistory($request, $branch, $year, $month);
        $bulanLabel = $this->bulanLabel();
        $monthName  = $bulanLabel[$month] ?? '-';
        $rows       = $data['rows'];

        // Rangkum filter aktif jadi 1 baris info — biar pembaca file tahu
        // export ini hasil saringan mana (pola sama dgn export petty cash).
        $filterParts = [];
        if (! empty($data['categories'])) {
            $filterParts[] = 'Kategori: ' . implode(', ', array_map(
                fn ($c) => $data['categoryOptions'][$c] ?? $c,
                $data['categories'],
            ));
        }
        if ($data['search'] !== '') {
            $filterParts[] = 'Cari: "' . $data['search'] . '"';
        }

        $branchSlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $branch->name));
        $filename   = sprintf('jejak-transaksi_%s_%04d-%02d.xlsx', $branchSlug, $year, $month);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jejak Transaksi');

        // ─── Info block (atas) ───
        // Periode ditulis eksplisit sebagai STRING; kalau dibiarkan default,
        // "September 2026" gampang ditebak Excel jadi tanggal dan tampil "Sep-26".
        $sheet->setCellValue('A1', 'Modul');   $sheet->setCellValue('B1', 'Jejak Transaksi Petty Cash');
        $sheet->setCellValue('A2', 'Cabang');  $sheet->setCellValue('B2', (string) $branch->name);
        $sheet->setCellValue('A3', 'Periode');
        $sheet->setCellValueExplicit('B3', $monthName . ' ' . $year, DataType::TYPE_STRING);
        $sheet->setCellValue('A4', 'Filter');
        $sheet->setCellValue('B4', empty($filterParts) ? '-' : implode(' · ', $filterParts));
        $sheet->getStyle('A1:A4')->getFont()->setBold(true);

        // ─── Header tabel (row 5) ───
        $headerRow  = 5;
        $headerCols = [
            'A' => 'Tanggal',  'B' => 'Jam',    'C' => 'Keterangan', 'D' => 'Kategori',
            'E' => 'Wallet',   'F' => 'Jumlah', 'G' => 'Status',     'H' => 'Bukti',
            'I' => 'Dicatat oleh', 'J' => 'Diedit', 'K' => 'Ref',
        ];
        foreach ($headerCols as $col => $h) {
            $sheet->setCellValue($col . $headerRow, $h);
        }
        $sheet->getStyle("A{$headerRow}:K{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1E40AF']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        // ─── Data rows ───
        $row       = $headerRow + 1;
        $rowsTotal = 0.0;
        foreach ($rows as $i => $r) {
            if (! $r->deleted) {
                $rowsTotal += (float) $r->amount;
            }

            $sheet->setCellValue("A{$row}", $r->date->format('Y-m-d'));
            $sheet->setCellValueExplicit("B{$row}", $r->recorded_at ? $r->recorded_at->format('H:i') : '-', DataType::TYPE_STRING);
            $sheet->setCellValue("C{$row}", (string) $r->description);
            $sheet->setCellValue("D{$row}", (string) ($r->category_label ?: '-'));
            $sheet->setCellValue("E{$row}", (string) $r->wallet_name);
            $sheet->setCellValueExplicit("F{$row}", (float) $r->amount, DataType::TYPE_NUMERIC);
            $sheet->setCellValue("G{$row}", $r->deleted ? 'Dihapus' : 'Aktif');
            $sheet->setCellValue("H{$row}", $r->attachment_path ? 'Ada' : '-');
            $sheet->setCellValue("I{$row}", (string) $r->by);
            $sheet->setCellValue("J{$row}", $r->edited
                ? (($r->edited_at ? $r->edited_at->format('Y-m-d H:i') : '-') . ' — ' . ($r->edited_by ?: '-'))
                : '-');
            $sheet->setCellValue("K{$row}", (string) ($r->ref_no ?: '-'));

            // Baris terhapus = jejak audit: merah muda + coret supaya tak
            // ikut terbaca sebagai pengeluaran yang sah. Sisanya zebra biasa.
            if ($r->deleted) {
                $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
                    'font' => ['strikethrough' => true, 'color' => ['rgb' => '991B1B']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']],
                ]);
            } elseif ($i % 2 === 1) {
                $sheet->getStyle("A{$row}:K{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('EFF6FF');
            }
            $sheet->getStyle("A{$row}:K{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->setColor(new Color('DBEAFE'));

            // Keterangan bisa panjang — biarkan wrap, kolom lain tetap rapi.
            $sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("B{$row}:B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
        }

        $lastDataRow = $row - 1;
        $hasData     = $lastDataRow >= $headerRow + 1;

        if ($hasData) {
            $sheet->getStyle("F" . ($headerRow + 1) . ":F{$lastDataRow}")
                ->getNumberFormat()->setFormatCode('#,##0');
        }

        // ─── Footer TOTAL ───
        // Angka yang dijumlah = baris AKTIF yang tampil. Kalau filter menyala,
        // total sebulan penuh ikut ditulis di bawahnya supaya tak salah baca.
        $totalRow = ($hasData ? $lastDataRow : $headerRow) + 2;
        $sheet->setCellValue("C{$totalRow}", empty($filterParts)
            ? 'TOTAL PENGELUARAN (transaksi aktif)'
            : 'TOTAL PENGELUARAN YANG DITAMPILKAN (transaksi aktif)');
        $sheet->setCellValueExplicit("F{$totalRow}", $rowsTotal, DataType::TYPE_NUMERIC);
        $sheet->getStyle("C{$totalRow}:K{$totalRow}")->applyFromArray([
            'font'    => ['bold' => true],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2563EB']]],
        ]);
        $sheet->getStyle("F{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("F{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        if (! empty($filterParts)) {
            $noteRow = $totalRow + 1;
            $sheet->setCellValue("C{$noteRow}", 'Total satu bulan penuh (tanpa filter)');
            $sheet->setCellValueExplicit("F{$noteRow}", (float) $data['cashOut'], DataType::TYPE_NUMERIC);
            $sheet->getStyle("C{$noteRow}:K{$noteRow}")->getFont()
                ->setItalic(true)->getColor()->setRGB('475569');
            $sheet->getStyle("F{$noteRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("F{$noteRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // Auto-width semua kolom; keterangan dikunci manual karena auto-size
        // + wrap text bikin kolomnya melar mengikuti baris terpanjang.
        foreach (['A', 'B', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('C')->setWidth(46);

        // Header tetap kelihatan saat scroll + filter bawaan Excel.
        $sheet->freezePane('A' . ($headerRow + 1));
        $sheet->setAutoFilter("A{$headerRow}:K{$headerRow}");

        $headers = [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache',
        ];

        return response()->stream(function () use ($spreadsheet) {
            (new XlsxWriter($spreadsheet))->save('php://output');
        }, 200, $headers);
    }

    /** Label bulan Indonesia [1..12] — dipakai picker & judul halaman. */
    private function bulanLabel(): array
    {
        return [
            1 => 'Januari',  2 => 'Februari', 3 => 'Maret',
            4 => 'April',    5 => 'Mei',      6 => 'Juni',
            7 => 'Juli',     8 => 'Agustus',  9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
    }

    /** Baca & clamp year/month dari query string. */
    private function resolveHistoryPeriod(Request $request): array
    {
        $today = Carbon::today();
        $year  = (int) $request->get('year',  $today->year);
        $month = (int) $request->get('month', $today->month);
        if ($year < 2000 || $year > 2100) $year = (int) $today->year;
        if ($month < 1   || $month > 12)  $month = (int) $today->month;

        return [$year, $month];
    }

    /**
     * Kategori pilihan user dari query string (?categories[]=...), divalidasi
     * terhadap peta kategori yang ada. Nilai tak dikenal diabaikan.
     */
    private function parseCategories(Request $request, array $categoryOptions): array
    {
        $raw = $request->get('categories', $request->get('category', []));
        if (! is_array($raw)) {
            $raw = ($raw === '' || $raw === null) ? [] : [(string) $raw];
        }

        $valid = [];
        foreach ($raw as $c) {
            $c = (string) $c;
            if ($c !== '' && array_key_exists($c, $categoryOptions) && ! in_array($c, $valid, true)) {
                $valid[] = $c;
            }
        }

        return $valid;
    }

    /**
     * Susun jejak transaksi 1 cabang + 1 bulan: ringkasan periode (opening,
     * cash in/out, closing) + baris pengeluaran yang tampil.
     *
     * Filter kategori & pencarian SENGAJA tidak di-apply ke query — saldo dan
     * summary cards harus selalu mencerminkan posisi kas sebenarnya. Filter
     * cuma menyaring baris yang dirender (pola sama dengan halaman petty
     * cash), jadi total di bawah tabel dipisah: $cashOut = seluruh bulan,
     * $rowsTotal = yang tampil.
     */
    private function buildBranchHistory(Request $request, RawBranch $branch, int $year, int $month): array
    {
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

        // categoryOptions = peta label (aktif + nonaktif) untuk badge & export;
        // categoryFilterOptions = isi dropdown filter, hanya kategori aktif
        // (plus yang sedang dipilih) supaya kategori pensiunan tak muncul lagi.
        $categoryOptions = PettyCashTransaction::categoryMap();
        $categories      = $this->parseCategories($request, $categoryOptions);
        $search          = trim((string) $request->get('q', ''));

        $categoryFilterOptions = PettyCashTransaction::activeCategoryOptions();
        foreach ($categories as $c) {
            if (! array_key_exists($c, $categoryFilterOptions) && isset($categoryOptions[$c])) {
                $categoryFilterOptions[$c] = $categoryOptions[$c];
            }
        }

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
        $saldo     = $opening;
        $cashIn    = 0.0;
        $cashOut   = 0.0;
        $rowsTotal = 0.0;
        $rows      = [];
        foreach ($transactions as $t) {
            $isDeleted = $t->deleted_at !== null;
            $amt       = (float) $t->amount;
            $isIn      = $t->direction === PettyCashTransaction::DIR_IN;

            if (! $isDeleted) {
                $saldo += $isIn ? $amt : -$amt;
                // Ringkasan mengikuti semantik dashboard: cash in = semua uang
                // masuk non-transfer (top up + setoran non-topup), cash out =
                // pengeluaran non-transfer.
                if (! $t->is_transfer) {
                    if ($isIn) {
                        $cashIn += $amt;
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

            // Saringan tampilan (kategori + cari keterangan).
            if (! empty($categories) && ! in_array((string) $t->category, $categories, true)) continue;
            if ($search !== '' && mb_stripos((string) $t->description, $search) === false) continue;

            if (! $isDeleted) {
                $rowsTotal += $amt;
            }

            $rows[] = (object) [
                'date'            => $t->trx_date instanceof Carbon ? $t->trx_date : Carbon::parse($t->trx_date),
                'description'     => $t->description,
                'category'        => $t->category,
                'category_label'  => $t->category ? ($categoryOptions[$t->category] ?? $t->category) : null,
                'wallet_name'     => $t->fundSource?->name ?? '-',
                'wallet_short'    => $t->fundSource?->effectiveShortLabel() ?? '?',
                'wallet_color'    => $t->fundSource?->effectiveBrandColor() ?? '#64748b',
                'amount'          => $amt,
                'by'              => $t->created_by ? ($userNames[$t->created_by] ?? '—') : '—',
                'recorded_at'     => $t->created_at, // jam asli saat transaksi dicatat ke sistem
                'edited'          => $t->updated_by !== null && $t->edited_at !== null,
                'edited_at'       => $t->edited_at,
                'edited_by'       => $t->updated_by ? ($userNames[$t->updated_by] ?? '—') : null,
                'deleted'         => $isDeleted,
                'ref_no'          => $t->ref_no,
                'attachment_path' => $t->attachment_path,
            ];
        }

        return [
            'period'          => $period,
            'rows'            => $rows,
            'opening'         => $opening,
            'closing'         => $saldo,
            'cashIn'          => $cashIn,
            'cashOut'         => $cashOut,
            'rowsTotal'       => $rowsTotal,
            'categoryOptions'       => $categoryOptions,
            'categoryFilterOptions' => $categoryFilterOptions,
            'categories'            => $categories,
            'search'          => $search,
            'hasFilter'       => ! empty($categories) || $search !== '',
        ];
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
        if (! pettyCashResetEnabled()) {
            abort(404); // fitur reset tak tersedia di lingkungan ini (prod)
        }
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

        // Periode yang belum pernah ada TIDAK dibuat dari sini. Endpoint ini
        // tugasnya MENGHAPUS; membuat periode sebagai efek samping berarti
        // POST dengan year/month karangan bisa melahirkan periode masa depan
        // berikut carry-forward-nya — baris ledger fantom dari tombol hapus.
        $period = PettyCashPeriod::where('period_year', $year)
            ->where('period_month', $month)
            ->where('raw_branch_id', (string) $branch->id)
            ->first();

        if (! $period) {
            return back()->with('error', 'Tidak ada periode petty cash untuk bulan itu — tak ada yang perlu direset.');
        }

        // Periode yang sudah DITUTUP itu angka yang sudah di-sign-off. Semua
        // jalur mutasi lain menghormatinya (top-up approve bahkan abort 409);
        // tombol reset tak boleh jadi satu-satunya pintu belakang.
        if ($period->is_closed) {
            return back()->with('error',
                'Periode ' . $month . '/' . $year . ' cabang ' . $branch->name
                . ' sudah DITUTUP — angkanya beku dan tidak bisa direset. Buka kembali periodenya dulu bila memang perlu.');
        }

        // Catat DULU, sebelum barisnya hilang: siapa, kapan, berapa banyak, dan
        // berapa nilainya. Aksi paling destruktif di modul uang tak boleh jadi
        // yang paling senyap — sebelumnya jejaknya cuma pesan sekilas di layar.
        $ringkas = PettyCashTransaction::forPeriod($period->id)
            ->selectRaw('COUNT(*) n, COALESCE(SUM(amount),0) total')->first();

        \Illuminate\Support\Facades\Log::warning('[pettycash] RESET jejak transaksi', [
            'oleh'      => session('name') . ' (' . session('id') . ')',
            'cabang'    => $branch->name,
            'periode'   => $month . '/' . $year,
            'jumlah'    => (int) ($ringkas->n ?? 0),
            'nilai'     => (float) ($ringkas->total ?? 0),
        ]);

        $deleted = PettyCashTransaction::forPeriod($period->id)->delete();

        // Saldo periode SESUDAHNYA disnapshot dari periode ini saat dibuat.
        // Menghapus transaksi tanpa menghitung ulang meninggalkan selisih hantu
        // yang terbawa selamanya, tanpa peringatan apa pun.
        $terkoreksi = $this->hitungUlangSaldoBerantai($period);

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
            ->with('success', "Jejak transaksi {$branch->name} ({$monthName} {$year}) direset — {$deleted} transaksi dihapus permanen."
                . ($terkoreksi ? " Saldo awal {$terkoreksi} periode sesudahnya ikut dihitung ulang." : ''));
    }

    /**
     * Hitung ulang saldo awal periode-periode SESUDAH sebuah periode berubah.
     *
     * `opening_balance` dan `source_opening_balances` itu SNAPSHOT yang diambil
     * saat periode dibuat (lihat PettyCashPeriod::ensurePeriod) — bukan turunan
     * hidup. Jadi begitu transaksi periode lama berubah, semua periode
     * sesudahnya membawa angka yang tak lagi cocok, diam-diam, selamanya.
     *
     * Periode yang sudah DITUTUP sengaja dilewati DAN menghentikan rantai:
     * angkanya sudah di-sign-off, dan menyentuhnya akan mengubah laporan yang
     * sudah dipakai. Kalau rantai berhenti di situ, selisihnya memang tetap ada
     * — tapi itu keputusan sadar yang terlihat, bukan kerusakan senyap.
     *
     * @return int jumlah periode yang saldo awalnya diperbarui
     */
    private function hitungUlangSaldoBerantai(PettyCashPeriod $dari): int
    {
        $berikut = PettyCashPeriod::where('raw_branch_id', $dari->raw_branch_id)
            ->where(function ($q) use ($dari) {
                $q->where('period_year', '>', $dari->period_year)
                  ->orWhere(function ($qq) use ($dari) {
                      $qq->where('period_year', $dari->period_year)
                         ->where('period_month', '>', $dari->period_month);
                  });
            })
            ->orderBy('period_year')->orderBy('period_month')
            ->get();

        $sebelumnya = $dari->fresh();
        $n = 0;

        foreach ($berikut as $p) {
            if ($p->is_closed) break;   // beku — dan rantai berhenti di sini

            $opening = $sebelumnya->closing_balance !== null
                ? (float) $sebelumnya->closing_balance
                : (float) $sebelumnya->opening_balance + (float) PettyCashTransaction::netForPeriod($sebelumnya->id);

            $p->update([
                'opening_balance'         => $opening,
                'source_opening_balances' => $sebelumnya->computeClosingPerSource(),
            ]);

            $sebelumnya = $p->fresh();
            $n++;
        }

        return $n;
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
        if (! pettyCashResetEnabled()) {
            abort(404); // fitur reset tak tersedia di lingkungan ini (prod)
        }
        if (! isSuperAdmin()) {
            abort(403, 'Hanya Super Admin yang boleh reset riwayat review.');
        }

        // Dipersempit ke apa yang BENAR-BENAR terlihat di layar. Judulnya
        // "Riwayat Review Terakhir" dan isinya 20 baris, tapi perintah lamanya
        // menghapus SELURUH cabang sepanjang masa — termasuk satu-satunya
        // catatan siapa menyetujui berapa dan kapan, untuk top-up yang uangnya
        // sudah cair. Transaksi kasnya bertahan dengan ref_no 'REQ-{id}' yang
        // menunjuk baris yang tak ada lagi: asal-usul uang putus permanen.
        //
        // Sekarang: hanya baris yang sedang ditampilkan (id-nya dikirim form).
        // Tak ada id → tak menghapus apa pun, bukan menghapus semuanya.
        $ids = array_filter((array) $request->input('ids', []));
        if (! $ids) {
            return back()->with('error',
                'Tidak ada riwayat yang dipilih. Muat ulang halamannya lalu coba lagi.');
        }

        $baris = TopupRequest::whereIn('id', $ids)
            ->where('status', '!=', TopupRequest::STATUS_PENDING)
            ->get(['id', 'raw_branch_id', 'amount_approved', 'amount_requested', 'status', 'reviewed_by']);

        \Illuminate\Support\Facades\Log::warning('[pettycash] RESET riwayat review', [
            'oleh'   => session('name') . ' (' . session('id') . ')',
            'jumlah' => $baris->count(),
            'nilai'  => (float) $baris->sum(fn ($b) => $b->amount_approved ?? $b->amount_requested),
            'ids'    => $baris->pluck('id')->all(),
        ]);

        $deleted = TopupRequest::whereIn('id', $baris->pluck('id'))->delete();

        return redirect()
            ->route('finance.pettycash')
            ->with('success', "Riwayat review direset — {$deleted} pengajuan yang tampil di layar dihapus. Antrian pending, riwayat lain, & transaksi petty cash tidak terpengaruh.");
    }
}
