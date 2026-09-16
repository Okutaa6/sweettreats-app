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

    /**
     * Satu-satunya istilah untuk perpindahan uang antar wallet.
     *
     * Dulu ada 3 verb tergantung arah ("Tarik tunai" kalau tujuannya Cash,
     * "Deposit" kalau asalnya Cash, "Transfer" untuk sisanya) — satu perbuatan
     * yang sama tapi 3 nama, bikin operator cabang salah pilih menu. Sekarang
     * semuanya "Pindah saldo".
     */
    private const MOVE_VERB = 'Pindah saldo';

    /* ───────── Helpers ───────── */

    private function kind(Request $request): string
    {
        return self::KIND;
    }

    private function periodClass(string $kind): string { return PettyCashPeriod::class; }
    private function trxClass(string $kind):    string { return PettyCashTransaction::class; }
    private function defaultFloat(string $kind): int   { return self::DEFAULT_FLOAT; }

    /**
     * Cabang efektif (rolling-aware) untuk user yang TERKUNCI cabang.
     *   - Admin / canSeeAllBranches (tak punya session lock) → null (dropdown jalan).
     *   - Terkunci + punya posisi aktif hari ini → cabang rolling/home dari
     *     sys_user_position (rolling menang selama window, auto-revert sesudahnya).
     *   - Terkunci tanpa posisi terkonfirmasi → fallback session('raw_branch_id')
     *     (perilaku lama) supaya user tanpa checklist position tetap jalan.
     * Satu sumber kebenaran untuk resolve DAN guard mutasi — keduanya harus
     * memakai ini supaya user rolling tidak kena 403 saat mengubah data.
     */
    private function lockedBranchId(): ?string
    {
        $session = session('raw_branch_id');
        if (! $session) return null;                 // admin → biarkan dropdown
        $eff = positionBranchOn((string) session('id'), \Carbon\Carbon::today());
        return $eff ?: (string) $session;            // fallback aman ke home
    }

    /**
     * Resolve branch yang sedang diakses:
     *   - User terkunci cabang → cabang efektif rolling-aware (lockedBranchId).
     *   - Admin → ambil dari ?branch=, validasi, default ke cabang pertama
     *     kalau tidak ada / invalid.
     * Return null kalau di DB belum ada cabang sama sekali (case ekstrem).
     */
    private function resolveBranchId(Request $request): ?string
    {
        $locked = $this->lockedBranchId();
        if ($locked) {
            return $locked;
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
        // Pertahankan filter aktif (kategori, cari, wallet, rentang tanggal,
        // bulan/tahun) supaya setelah mutasi (pengeluaran, top up, transfer,
        // edit, hapus) filter TIDAK ter-reset sendiri. Nilai dibawa lewat
        // hidden input di tiap form (_filter_hidden.blade.php); filter hanya
        // hilang saat user hapus manual.
        foreach (['start_date', 'end_date', 'bulan', 'tahun', 'fund_source_id', 'q'] as $key) {
            if (request()->filled($key)) {
                $params[$key] = request()->get($key);
            }
        }
        $cats = array_values(array_filter(
            (array) request('categories', []),
            fn ($c) => $c !== null && $c !== '',
        ));
        if (! empty($cats)) {
            $params['categories'] = $cats;
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
     * Batas waktu EDIT & HAPUS transaksi (kebijakan audit).
     *   - Super Admin  → bebas, tanpa batas waktu.
     *   - User biasa    → PettyCashTransaction::EDIT_WINDOW_HOURS (8 jam) sejak
     *                     created_at; setelah itu terkunci (perbaikan lewat
     *                     koreksi transaksi baru).
     * Dipakai bersama oleh update() ($verb='diedit') & destroy()
     * ($verb='dihapus'). Guard yang sama disembunyikan di UI lewat
     * $canEdit/$canDelete di _table_rows.
     */
    private function abortIfBeyondEditWindow($trx, string $verb): void
    {
        if (isSuperAdmin()) {
            return; // Super Admin: bypass total.
        }
        $hours = \App\Models\PettyCashTransaction::EDIT_WINDOW_HOURS;
        if ($trx->created_at && $trx->created_at->lt(now()->subHours($hours))) {
            abort(403, "Transaksi sudah lebih dari {$hours} jam — tidak bisa {$verb} lagi.");
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

        // Filter kategori MULTI-SELECT — user bisa pilih beberapa kategori
        // sekaligus (categories[]=makan&categories[]=transport). Backward
        // compat: param lama `category` tunggal tetap diterima. Array kosong
        // = semua kategori.
        // $categoryOptions = peta LABEL (aktif + nonaktif) — dipakai untuk
        // validasi param & menerjemahkan key jadi label di strip filter aktif.
        // Daftar centang di dropdown filter sendiri hanya kategori AKTIF,
        // supaya kategori yang sudah dipensiunkan tidak muncul lagi. Kategori
        // nonaktif yang sedang dipilih lewat URL tetap ditampilkan agar
        // filternya bisa dilihat & dilepas.
        $categoryOptions = \App\Models\PettyCashTransaction::categoryMap();
        $categories      = $this->parseCategories($request, $categoryOptions);
        $categoryFilterOptions = \App\Models\PettyCashTransaction::activeCategoryOptions();
        foreach ($categories as $c) {
            if (! array_key_exists($c, $categoryFilterOptions) && isset($categoryOptions[$c])) {
                $categoryFilterOptions[$c] = $categoryOptions[$c];
            }
        }

        $period = $periodClass::ensurePeriod(
            $year,
            $month,
            $this->defaultFloatForBranch($kind, $branchId),
            $branchId,
        );

        // Wallet yang ditawarkan = aktif se-klinik DIKURANGI yang sudah
        // dipensiunkan untuk cabang ini lewat Migrasi Saldo. Cabang yang
        // belum dimigrasi tetap melihat wallet lamanya.
        $fundSources        = FundSource::activeForBranch($branchId);
        $fundSourcesGrouped = $fundSources->groupBy('category');

        // Filter wallet — validate ke fund_sources aktif. Invalid → diabaikan.
        $fundSourceId = (int) $request->get('fund_source_id', 0);
        if ($fundSourceId > 0 && ! $fundSources->contains('id', $fundSourceId)) {
            $fundSourceId = 0;
        }

        // PENTING: filter kategori / search / wallet TIDAK di-apply di query.
        // Ambil SELURUH transaksi periode supaya saldo & summary cards selalu
        // mencerminkan posisi sebenarnya (uang tunai fisik), lalu filter cuma
        // menyaring baris yang tampil di tabel (di-handle dalam buildRows,
        // pola sama dengan filter tanggal). Kalau filter di-apply di query,
        // saldo ikut dihitung ulang dari subset → angka menyesatkan
        // (mis. filter 1 kategori bikin saldo tampil saldo_awal − kategori itu).
        $transactions = $trxClass::active()
            ->with('fundSource')
            ->forPeriod($period->id)
            ->orderBy('trx_date')
            ->orderBy('id')
            ->get();

        // Map: source_id → account_number untuk cabang ini. Dipakai untuk
        // pre-fill input account di modal top-up & untuk tampil di card.
        $branchAccountMap = FundSourceBranchAccount::mapForBranch($branchId);

        // Summary cards & source breakdown mencerminkan SELURUH transaksi periode
        // — filter tanggal/kategori/wallet/search cuma menyempitkan baris tabel.
        [$rows, $summary, $sourceBalances] =
            $this->buildRows($period, $transactions, $fundSources, $startDate, $endDate, $branchAccountMap, $categories, $search, $fundSourceId);

        // Kategori aktif + label untuk strip "filter aktif" (buildRows sengaja
        // tidak tahu CATEGORIES — labelnya di-inject di sini).
        $summary['filter_categories']        = $categories;
        $summary['filter_categories_labels'] = array_map(
            fn($c) => $categoryOptions[$c] ?? $c,
            $categories,
        );

        // Chip "dicatat oleh": peta id→nama pencatat, tampil bila ada >1 pencatat
        // berbeda di periode ini (mis. operator backup mencatat bareng FO resident).
        $nameIds = collect($rows)->pluck('created_by')->filter()->unique()->values();
        $recorderNames = $nameIds->isEmpty()
            ? collect()
            : \App\Models\Users::whereIn('id', $nameIds)->pluck('nama', 'id');
        $showRecorder = $nameIds->count() > 1;

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
        // Read-only bila: periode ditutup / bukan bulan ini / role Finance.
        $isReadOnly        = (bool) $period->is_closed || ! $isCurrentMonth || $isFinanceViewOnly;

        // Edit & hapus transaksi terbuka untuk semua role penulis (Admin
        // General/Backup, Sales) — Finance tetap view-only lewat $isReadOnly
        // ($isFinanceViewOnly). Batas waktu 24 jam sejak dibuat di-enforce
        // per-baris di _table_rows ($canEdit / $canDelete) & server-side di
        // update()/destroy(). Setiap edit/hapus meninggalkan jejak di tab
        // "Riwayat" (updated_by/edited_at & deleted_by/deleted_at).
        $showTrxActions = ! $isReadOnly;

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

        // Jendela Migrasi Saldo (cut-over). handover_at terisi tapi
        // opening_set_by masih kosong = Super Admin sudah membuka, operator
        // cabang belum mengisi. Lihat resetOpening().
        $openingWindowOpen = $period->handover_at !== null && $period->opening_set_by === null;
        $openingSetByName  = $period->opening_set_by
            ? (string) (\App\Models\Users::whereKey($period->opening_set_by)->value('nama') ?? '—')
            : null;

        // Status pengajuan top-up Sales untuk cabang aktif: kalau ada yang
        // pending, _summary_cards tampil badge "menunggu Finance" yang bisa
        // diklik → modal rincian pengajuan (read-only). Ambil koleksi lengkap
        // (bukan cuma count) supaya modal punya detail nominal/wallet/alasan.
        $pendingTopups = $branchId
            ? \App\Models\TopupRequest::with('fundSource')
                ->pending()->forBranch($branchId)
                ->orderByDesc('is_urgent')
                ->orderBy('requested_at', 'asc')
                ->get()
            : collect();
        $pendingTopupCount = $pendingTopups->count();

        // Jejak transaksi (tab "Riwayat"): transaksi periode ini yang pernah
        // diedit (masih aktif, edited_at != NULL) atau dihapus (deleted_at !=
        // NULL). scopeActive() sengaja TIDAK dipakai supaya baris terhapus
        // ikut tertarik. Read-only — hanya untuk audit siapa & kapan.
        $riwayatTrx = $trxClass::with('fundSource')
            ->forPeriod($period->id)
            ->where(function ($q) {
                $q->whereNotNull('deleted_at')->orWhereNotNull('edited_at');
            })
            ->orderByRaw('COALESCE(deleted_at, edited_at) DESC')
            ->get();

        $actorIds   = $riwayatTrx->pluck('deleted_by')
            ->merge($riwayatTrx->pluck('updated_by'))
            ->filter()->unique()->values();
        $actorNames = $actorIds->isEmpty()
            ? collect()
            : \App\Models\Users::whereIn('id', $actorIds)->pluck('nama', 'id');

        $rowsRiwayat = $riwayatTrx->map(function ($t) use ($actorNames) {
            $isDeleted = ! empty($t->deleted_at);
            $actorId   = $isDeleted ? $t->deleted_by : $t->updated_by;
            return (object) [
                'trx_date'    => $t->trx_date,
                'description' => $t->is_transfer
                    ? self::normalizeMoveVerb($t->description)
                    : $t->description,
                'source_name' => $t->fundSource?->name,
                'amount'      => (float) $t->amount,
                'direction'   => $t->direction,
                'is_topup'    => (bool) $t->is_topup,
                'is_transfer' => (bool) $t->is_transfer,
                'action'      => $isDeleted ? 'deleted' : 'edited',
                'actor'       => $actorId ? ($actorNames[$actorId] ?? '—') : '—',
                'at'          => $isDeleted ? $t->deleted_at : $t->edited_at,
            ];
        });

        return view('crud.pettycash.index', compact(
            'period', 'rows', 'summary', 'sourceBalances',
            'year', 'month', 'bulanLabel',
            'startDate', 'endDate', 'monthStartStr', 'monthEndStr',
            'search', 'categories', 'categoryOptions', 'categoryFilterOptions', 'fundSourceId',
            'isCurrentMonth', 'isReadOnly', 'isFinanceViewOnly', 'showTrxActions',
            'kind', 'tabs', 'fundSources', 'fundSourcesGrouped',
            'branches', 'activeBranch', 'branchId', 'sessionBranch',
            'branchAccountMap', 'pendingTopupCount', 'pendingTopups', 'requesterName',
            'rowsRiwayat', 'recorderNames', 'showRecorder',
            'openingWindowOpen', 'openingSetByName'
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
        // Batas bulan periode — beda dari $startDate/$endDate yang bisa
        // dipersempit user. Dipakai meloloskan baris nyasar (lihat query).
        $monthStartStr = $monthStart->format('Y-m-d');
        $monthEndStr   = $monthEnd->format('Y-m-d');

        // Filter tambahan — match dengan filter di view index supaya
        // hasil export = data yang user lihat di layar.
        $search       = trim((string) $request->get('q', ''));
        $categoryMap  = \App\Models\PettyCashTransaction::categoryMap();
        $categories   = $this->parseCategories($request, $categoryMap);
        $fundSourceId = (int) $request->get('fund_source_id', 0);
        if ($fundSourceId > 0 && ! FundSource::whereKey($fundSourceId)->exists()) {
            $fundSourceId = 0;
        }
        $walletName = $fundSourceId > 0
            ? (string) FundSource::whereKey($fundSourceId)->value('name')
            : '';

        // Tarik HANYA top up & pengeluaran (skip transfer) — catatan penuh cabang.
        $transactions = $trxClass::active()
            ->with('fundSource')
            ->forPeriod($period->id)
            ->where(function ($q) {
                $q->where('is_topup', 1)
                  ->orWhere(function ($qq) {
                      $qq->where('is_topup', 0)->where('is_transfer', 0);
                  });
            })
            // Rentang tanggal DIKECUALIKAN untuk baris nyasar (trx_date di luar
            // bulan periodenya) — sama seperti di tabel layar. Tanpa ini,
            // nominalnya ikut saldo periode tapi hilang dari file export,
            // sehingga total di kartu ringkasan tidak pernah cocok dengan
            // jumlah baris di Excel.
            ->where(function ($q) use ($startDate, $endDate, $monthStartStr, $monthEndStr) {
                $q->where(function ($qq) use ($startDate, $endDate) {
                    $qq->whereDate('trx_date', '>=', $startDate)
                       ->whereDate('trx_date', '<=', $endDate);
                })->orWhereDate('trx_date', '<', $monthStartStr)
                  ->orWhereDate('trx_date', '>', $monthEndStr);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%");
            })
            ->when(! empty($categories), function ($q) use ($categories) {
                $q->whereIn('category', $categories);
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
        if (! empty($categories))    $filterParts[] = 'Kategori: ' . implode(', ', array_map(fn($c) => $categoryMap[$c] ?? $c, $categories));
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
        $categoryMap = \App\Models\PettyCashTransaction::categoryMap();

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
            // Baris cash-in non-topup (Setor Cash legacy — fiturnya sudah
            // dihapus) dulu ikut dilabeli "Pengeluaran" padahal uang MASUK.
            $sheet->setCellValue("B{$row}", $t->is_topup
                ? 'Top up'
                : ($cashIn > 0 ? 'Setor cash (legacy)' : 'Pengeluaran'));
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
        // Transaksi baru hanya boleh pakai kategori AKTIF.
        $categoryKeys = \App\Models\PettyCashTransaction::activeCategoryKeys();
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
        $this->abortIfDateOutsidePeriod($period, $data['trx_date']);

        // Guard: source dana harus punya saldo > 0 di periode ini.
        // Filter dropdown sudah hide source kosong, tapi validasi server-side
        // mencegah bypass via inspect element.
        $sourceBalance = $this->computeSourceBalance($period, (int) $data['fund_source_id'], $trxClass);
        if ($sourceBalance <= 0) {
            return redirect()
                ->route('pettycash', $this->preserveParams($kind))
                ->with('error', 'Sumber dana yang dipilih belum punya saldo. Silakan top up dulu.');
        }

        // ── Pagar kiriman ganda (sisi server; pengaman tombol bukan pagar) ──
        // Kembar PERSIS dalam 10 detik = klik ganda. Dibalas flash RAMAH, bukan
        // galat: dari sudut pandang orangnya, pengeluarannya memang sudah
        // tersimpan — yang ia butuhkan adalah kepastian, bukan teguran.
        if ($trxClass::kembarBaruSaja(session('id'), $data['amount'], $data['description'], $data['trx_date'])) {
            return redirect()
                ->route('pettycash', $this->preserveParams($kind))
                ->with('success', 'Pengeluaran yang sama baru saja tersimpan — tidak dicatat dua kali.');
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
        // Cabang efektif rolling-aware — kalau user sedang rolling, pengajuan
        // top-up ikut ke cabang rolling, bukan cabang home.
        $lockedBranch = $this->lockedBranchId();

        if ($lockedBranch) {
            $branchId = $lockedBranch;
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
     *
     * PENTING: method ini TIDAK menyentuh petty_cash_transactions sama sekali —
     * nol insert, nol update, nol delete. Semua pengeluaran / top up / pindah
     * saldo yang sudah tercatat tetap utuh; yang berubah hanya titik awal saldo.
     */
    public function setOpening(Request $request)
    {
        $this->requireWriteAccess();
        $kind        = $this->kind($request);
        $periodClass = $this->periodClass($kind);
        $trxClass    = $this->trxClass($kind);

        $tableName = (new $periodClass)->getTable();
        $request->validate([
            'period_id' => 'required|integer|exists:' . $tableName . ',id',
            'balances'  => 'required|array',
        ]);

        $period = $periodClass::findOrFail($request->input('period_id'));
        $this->abortIfReadOnly($period);
        $this->abortIfBranchMismatch($request, $period);

        // Simpan ini SEBELUM snapshot ditimpa — dipakai untuk cek "masih perawan"
        // dan sebagai nilai lama di audit log.
        $openingBefore = $period->openingPerSourceResolved();
        $totalBefore   = array_sum($openingBefore);

        // Kunci: form ini menimpa SELURUH saldo awal, jadi tidak boleh bisa
        // dibuka sembarang waktu. Dua kondisi yang diizinkan:
        //   1. Periode masih perawan (belum ada saldo awal & belum ada transaksi)
        //      → cabang baru pertama kali pakai sistem, seperti perilaku lama.
        //   2. Super Admin sudah membuka jendela isi ulang (resetOpening()).
        $isVirgin   = empty($openingBefore)
            && ! $trxClass::active()->forPeriod($period->id)->exists();
        $windowOpen = $period->handover_at !== null && $period->opening_set_by === null;
        if (! $isVirgin && ! $windowOpen) {
            return redirect()
                ->route('pettycash', $this->preserveParams($kind))
                ->with('error', 'Saldo awal periode ini sudah terkunci. Minta Super Admin menjalankan "Migrasi Saldo" dulu.');
        }

        // Parse balances: strip non-digit char (handle format "1.500.000"),
        // validate fund_source_id exists, skip yang 0 / kosong.
        // Hanya wallet AKTIF & belum dipensiunkan di cabang ini — begitu
        // wallet lama dimatikan (global) atau dipensiunkan lewat Migrasi
        // Saldo, ia tak bisa lagi diisi walau lewat POST karangan.
        $validSources = FundSource::activeForBranch($period->raw_branch_id)
            ->whereIn('category', [FundSource::CAT_CASH, FundSource::CAT_BANK, FundSource::CAT_EWALLET]);
        $sourceCategoryMap = $validSources->pluck('category', 'id')->all();
        $snapshot = [];
        $diketik  = [];
        foreach ((array) $request->input('balances', []) as $sid => $raw) {
            $sid = (int) $sid;
            if (! isset($sourceCategoryMap[$sid])) continue;
            $clean = preg_replace('/\D/', '', (string) $raw);
            $amt = $clean === '' ? 0.0 : (float) $clean;
            if ($amt > 0 && $amt <= \App\Models\PettyCashTransaction::MAX_AMOUNT) {
                $diketik[$sid] = $amt;
            }
        }

        // Angka yang diketik operator = saldo wallet SEKARANG (hasil hitung
        // fisik), bukan saldo tanggal 1. Karena kartu wallet menampilkan
        // saldo_awal + mutasi bulan ini, saldo awal disimpan sebagai
        // (diketik - mutasi) supaya kartunya berbunyi persis seperti yang
        // diketik. Kalau periode belum punya transaksi, mutasi = 0 dan
        // keduanya identik.
        $net = $this->netPerSource($period, $trxClass);
        foreach ($diketik as $sid => $amt) {
            $snapshot[$sid] = $amt - (float) ($net[$sid] ?? 0.0);
        }

        // Wallet yang punya transaksi di periode ini tapi TIDAK ikut diisi
        // (mis. wallet lama yang sedang dipensiunkan) dinolkan juga — kalau
        // dibiarkan, saldonya jadi sebesar mutasinya dan kartunya tetap
        // nongol. Transaksinya tetap ada di tabel, cuma tidak lagi
        // menyisakan saldo.
        foreach ($net as $sid => $n) {
            if (! array_key_exists($sid, $diketik) && abs($n) > 0.005) {
                $snapshot[$sid] = -$n;
            }
        }

        $period->source_opening_balances = $snapshot;
        $period->opening_balance         = array_sum($snapshot);
        // Stempel siapa yang mengisi → sekaligus menutup jendela isi ulang.
        $period->opening_set_by = (string) session('id');
        if ($period->handover_at === null) {
            $period->handover_at = now();
        }
        $period->save();

        // Rincian saldo lama diarsipkan di audit log — satu-satunya tempat
        // angka pra-cut-over bisa dibaca lagi setelah snapshot ditimpa.
        \App\Models\AuditLog::record(
            'update',
            sprintf(
                'Isi ulang Saldo Awal petty cash — %s %d/%d: Rp %s → Rp %s (selisih Rp %s)',
                $this->branchNameFor($period),
                $period->period_month,
                $period->period_year,
                number_format($totalBefore, 0, ',', '.'),
                number_format(array_sum($snapshot), 0, ',', '.'),
                number_format(array_sum($snapshot) - $totalBefore, 0, ',', '.'),
            ),
            $period,
            ['source_opening_balances' => $openingBefore, 'opening_balance' => $totalBefore],
            ['source_opening_balances', 'opening_balance', 'opening_set_by', 'handover_at'],
        );

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

        // Periode sesudahnya yang terlanjur dibuat ikut dikoreksi, supaya
        // bulan depan tidak membawa saldo & wallet lama.
        $terkoreksi = $this->rambatkanSaldoAwal($period, $periodClass, $trxClass);

        return redirect()
            ->route('pettycash', $this->preserveParams($kind))
            ->with('success', 'Saldo awal berhasil disimpan.'
                . ($terkoreksi ? " Saldo awal {$terkoreksi} periode sesudahnya ikut dihitung ulang." : ''));
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
            'to_account_number' => 'nullable|string|max:50',
        ], [
            'to_source_id.different' => 'Sumber tujuan tidak boleh sama dengan sumber asal.',
            'amount.max'             => 'Nominal maksimal Rp 50.000.000.',
        ]);

        $period = $periodClass::findOrFail($data['period_id']);
        $this->abortIfReadOnly($period);
        $this->abortIfBranchMismatch($request, $period);
        $this->abortIfDateOutsidePeriod($period, $data['trx_date']);

        // Cek saldo asal cukup
        $fromBalance = $this->computeSourceBalance($period, (int) $data['from_source_id'], $trxClass);
        if ($fromBalance < (float) $data['amount']) {
            return redirect()
                ->route('pettycash', $this->preserveParams($kind))
                ->with('error', 'Saldo sumber asal tidak cukup untuk ditransfer (saldo: Rp ' . number_format($fromBalance, 0, ',', '.') . ').');
        }

        $fromSource = FundSource::findOrFail($data['from_source_id']);
        $toSource   = FundSource::findOrFail($data['to_source_id']);
        $ref        = 'TRF-' . $period->id . '-' . now()->format('YmdHis') . '-' . \Illuminate\Support\Str::random(8);

        // Satu verb untuk semua arah — lihat self::MOVE_VERB.
        $verb = self::MOVE_VERB;

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

        // Tanpa catatan: wallet asal & tujuan sudah menjelaskan semuanya.
        // Baris LAMA bisa punya sufiks " — catatan"; collapseTransferPairs()
        // masih mengekstraknya supaya riwayat lama tetap tampil utuh.
        $descOut = $verb . ' ke ' . $toSource->name;
        $descIn  = $verb . ' dari ' . $fromSource->name;

        // Pagar kiriman ganda untuk transfer: baris KELUAR identik (pembuat +
        // nominal + keterangan + tanggal) dalam 10 detik terakhir. Keterangan
        // transfer dibangun dari sumber & tujuan, jadi kesamaannya sudah memuat
        // arah transfernya.
        if ($trxClass::kembarBaruSaja(session('id'), $data['amount'], $descOut, $data['trx_date'])) {
            return redirect()
                ->route('pettycash', $this->preserveParams($kind))
                ->with('success', 'Transfer yang sama baru saja tersimpan — tidak dicatat dua kali.');
        }

        // Pindah saldo tidak punya bukti: ini pergerakan internal antar wallet
        // milik cabang sendiri, tak ada pihak luar yang perlu dibuktikan.
        // Baris LAMA yang terlanjur punya attachment_path tetap bisa dibuka
        // lewat ikon klip di tabel.
        DB::transaction(function () use ($trxClass, $period, $data, $fromSource, $toSource, $descOut, $descIn, $ref) {
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
                'attachment_path' => null,
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
                'attachment_path' => null,
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
     * Kalau transaksi adalah bagian dari Pindah Saldo (is_transfer=1), hapus
     * juga pasangannya + expense bundle yang berbagi ref_no — supaya saldo
     * tidak pincang. Expense leg (is_transfer=0, ref_no=TRF-…) legacy: dibuat
     * shortcut "sekalian pengeluaran" yang sudah tidak ada lagi.
     */
    public function destroy(Request $request, $id)
    {
        $this->requireWriteAccess();
        $kind     = $this->kind($request);
        $trxClass = $this->trxClass($kind);

        $trx = $trxClass::active()->findOrFail($id);
        $period = $trx->period;
        $this->abortIfReadOnly($period);
        $this->abortIfBranchMismatch($request, $period);
        $this->abortIfBeyondEditWindow($trx, 'dihapus');

        // Jejak siapa yang menghapus — tampil di tab "Riwayat".
        $actorId = session('id');
        $now     = now();
        if ($trx->is_transfer && $trx->ref_no) {
            // Hapus seluruh bundle yang share ref_no: 2 leg transfer +
            // (opsional) expense bundle. ref_no transfer pakai prefix "TRF-"
            // jadi tidak collide dgn ref_no manual user.
            $affected = $trxClass::active()
                ->where('ref_no', $trx->ref_no)
                ->update(['deleted_at' => $now, 'deleted_by' => $actorId]);
            $msg = $affected > 2
                ? 'Transfer + pengeluaran bundel dihapus (' . $affected . ' baris).'
                : 'Transfer dihapus (kedua sisi).';
        } else {
            $trx->deleted_at = $now;
            $trx->deleted_by = $actorId;
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
        $this->abortIfBeyondEditWindow($trx, 'diedit');

        // Saat edit, izinkan SEMUA kategori yang pernah ada (termasuk yang kini
        // nonaktif) supaya transaksi lama tetap bisa disimpan ulang.
        $categoryKeys = array_keys(\App\Models\PettyCashTransaction::categoryMap());
        $data = $request->validate([
            'amount'            => 'required|numeric|min:1',
            'description'       => 'required|string|max:255',
            'category'          => 'required|in:' . implode(',', $categoryKeys),
            // Bukti opsional — sama seperti saat input (gambar / PDF, maks 5MB).
            'attachment'        => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'remove_attachment' => 'nullable|in:1',
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

        // Bukti: ganti file baru, atau hapus bukti lama bila diminta.
        $attachmentPath = $trx->attachment_path;
        if ($request->hasFile('attachment')) {
            $newPath = $this->storeAttachment($request, $trx->trx_date->format('Y-m-d'));
            if ($newPath) {
                $this->deleteAttachmentFile($attachmentPath);
                $attachmentPath = $newPath;
            }
        } elseif ($request->boolean('remove_attachment')) {
            $this->deleteAttachmentFile($attachmentPath);
            $attachmentPath = null;
        }

        $trx->update([
            'amount'          => $data['amount'],
            'description'     => $data['description'],
            'category'        => $data['category'],
            'attachment_path' => $attachmentPath,
            'updated_by'      => session('id'),
            'edited_at'       => now(),
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

        // ── Siapa yang menghapus, dan apa saja yang ikut terbawa ─────────────
        //
        // ⚠️ Penghapusan dompet menyapu transaksi ORANG LAIN di periode yang
        // sama, bukan cuma milik yang mengklik. Sebelum ini efeknya SENYAP:
        // `deleted_by` tak diisi (beda dari destroy() biasa yang mengisinya),
        // dan pesan suksesnya cuma bilang "berhasil dihapus" tanpa angka.
        //
        // Kejadian nyata 15 Agu 2026 19.50.36: pengeluaran Verini Rp21.000 di
        // periode Sunter lenyap sebagai efek samping pembersihan dompet Bank
        // BCA. Bukan keputusan siapa pun atas transaksi itu — dia cuma memakai
        // dompet yang sedang dibereskan. Karena `deleted_by` kosong, tak ada
        // yang bisa ditunjuk, dan baru ketahuan lima hari kemudian saat
        // Verini bertanya ke mana catatannya.
        //
        // Sekarang: dihitung dulu, dicap namanya, dicatat di audit log, dan
        // jumlahnya disebut terang-terangan di pesan hasil.
        $actorId = (string) session('id');

        $terdampak = $trxClass::active()
            ->forPeriod($period->id)
            ->where(function ($q) use ($sourceId, $refNos) {
                $q->where('fund_source_id', $sourceId);
                if (! empty($refNos)) {
                    $q->orWhere(fn ($q2) => $q2->whereIn('ref_no', $refNos)->where('is_transfer', 1));
                }
            })
            ->get(['id', 'created_by', 'amount']);

        $jumlah = $terdampak->count();
        $nilai  = (float) $terdampak->sum('amount');

        $idOrangLain = $terdampak->pluck('created_by')->filter()
            ->reject(fn ($id) => (string) $id === $actorId)->unique()->values();
        $namaOrangLain = $idOrangLain->isEmpty() ? collect() : \App\Models\Users::whereIn('id', $idOrangLain)
            ->pluck('nama');
        $jumlahOrangLain = $terdampak->filter(
            fn ($t) => $t->created_by && (string) $t->created_by !== $actorId
        )->count();

        // Step 2: soft-delete semua transaksi yang langsung pakai source ini.
        $trxClass::active()
            ->forPeriod($period->id)
            ->where('fund_source_id', $sourceId)
            ->update(['deleted_at' => $now, 'deleted_by' => $actorId]);

        // Step 3: soft-delete sibling kaki transfer (source LAIN dgn ref_no sama).
        if (! empty($refNos)) {
            $trxClass::active()
                ->forPeriod($period->id)
                ->whereIn('ref_no', $refNos)
                ->where('is_transfer', 1)
                ->update(['deleted_at' => $now, 'deleted_by' => $actorId]);
        }

        // Step 4: buang entry source dari snapshot opening, re-sync scalar.
        $snapshot = $period->source_opening_balances ?? [];
        if (! is_array($snapshot)) $snapshot = [];
        unset($snapshot[$sourceId]);
        $period->source_opening_balances = $snapshot;
        $period->opening_balance         = array_sum($snapshot);
        $period->save();

        // Jejak permanen. Modul petty cash tak punya audit log sendiri, padahal
        // ini aksi paling merusak di layar ini — sekali klik, transaksi sebulan
        // beberapa orang bisa hilang sekaligus.
        $namaDompet = optional(FundSource::find($sourceId))->name ?? ('#' . $sourceId);
        $namaCabang = optional(RawBranch::find($period->raw_branch_id))->name ?? '?';
        \App\Models\AuditLog::record('delete', sprintf(
            'Petty cash: dompet "%s" dihapus dari periode %s %d-%02d — %d transaksi (Rp%s) ikut terhapus%s.',
            $namaDompet, $namaCabang, $period->period_year, $period->period_month,
            $jumlah, number_format($nilai, 0, ',', '.'),
            $jumlahOrangLain > 0
                ? sprintf(', %d di antaranya milik %s', $jumlahOrangLain, $namaOrangLain->implode(', '))
                : ''
        ), $period);

        // Pesan hasil menyebut angkanya. Kalau ada milik orang lain, sebut
        // namanya — supaya yang menghapus tahu harus mengabari siapa, bukan
        // menemukannya berhari-hari kemudian.
        $pesan = $jumlah === 0
            ? sprintf('Dompet "%s" dihapus dari periode ini. Tidak ada transaksi yang terpengaruh.', $namaDompet)
            : sprintf('Dompet "%s" dihapus — %d transaksi (Rp%s) ikut terhapus.',
                $namaDompet, $jumlah, number_format($nilai, 0, ',', '.'));

        if ($jumlahOrangLain > 0) {
            $pesan .= sprintf(' PERHATIAN: %d di antaranya dicatat oleh %s — tolong kabari mereka.',
                $jumlahOrangLain, $namaOrangLain->implode(', '));
        }

        return redirect()
            ->route('pettycash', $this->preserveParams($kind))
            ->with($jumlahOrangLain > 0 ? 'error' : 'success', $pesan);
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

        // Jangan pernah menutup periode yang migrasi saldonya masih
        // menggantung. Di jendela itu saldo awal sengaja diisi lawan-mutasi
        // supaya tiap kartu berbunyi Rp 0 — jadi closing_balance juga akan
        // jatuh tepat Rp 0, bukan karena kasnya kosong tapi karena memang
        // dirancang begitu. Kalau dikunci, nol palsu itu terbawa jadi saldo
        // awal bulan depan dan is_closed tidak punya jalan balik lewat UI.
        // Kondisi yang sama dipakai $openingWindowOpen (index) & $windowOpen
        // (setOpening).
        if ($period->handover_at !== null && $period->opening_set_by === null) {
            return redirect()
                ->route('pettycash', $this->preserveParams($kind))
                ->with('error', 'Periode belum bisa ditutup — migrasi saldo masih menunggu operator cabang mengisi saldo dari hitung fisik. Isi Setoran Awal dulu, baru periode bisa dikunci.');
        }

        if (! $period->is_closed) {
            // Tutup periode = kunci total fisik kas cabang.
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

    /**
     * POST /pettycash/period/{id}/reset-opening
     *
     * Buka "jendela isi ulang" saldo awal untuk 1 cabang + 1 periode. Dipakai
     * saat cut-over ke daftar wallet yang lebih ringkas: Super Admin membuka
     * jendelanya, lalu operator cabang yang mengisi angka Cash & Bank dari
     * hitung fisik lewat modal Setoran Awal.
     *
     * Saldo awal langsung DINOLKAN saat jendela dibuka, supaya operator mulai
     * dari papan kosong. Rincian saldo lama diarsipkan ke audit log sebelum
     * dibuang — itu satu-satunya tempat angka pra-cut-over bisa dibaca lagi.
     *
     * Transaksi TIDAK disentuh sama sekali: tidak ada insert, update, maupun
     * delete ke petty_cash_transactions. Seluruh pengeluaran & pemasukan yang
     * sudah tercatat tetap utuh; yang berubah cuma titik awal saldonya.
     */
    public function resetOpening(Request $request, $periodId)
    {
        // Sengaja lebih ketat dari requireWriteAccess(): membuka kunci saldo
        // awal bukan aksi operasional harian.
        if (! isSuperAdmin()) {
            abort(403, 'Hanya Super Admin yang boleh menjalankan migrasi saldo.');
        }

        $kind        = $this->kind($request);
        $periodClass = $this->periodClass($kind);
        $period      = $periodClass::findOrFail($periodId);

        $this->abortIfReadOnly($period);
        $this->abortIfBranchMismatch($request, $period);

        // Bulan lampau tidak boleh diutak-atik — riwayatnya sengaja dibiarkan
        // apa adanya, lengkap dengan wallet-wallet lamanya.
        if (! $period->isCurrentMonth()) {
            return redirect()
                ->route('pettycash', $this->preserveParams($kind))
                ->with('error', 'Migrasi saldo hanya untuk bulan berjalan — riwayat bulan lampau dibiarkan apa adanya.');
        }

        $mode = $request->input('mode') === 'cancel' ? 'cancel' : 'open';

        if ($mode === 'cancel') {
            $period->handover_at = null;
            $period->save();

            return redirect()
                ->route('pettycash', $this->preserveParams($kind))
                ->with('success', 'Migrasi saldo dihentikan.');
        }

        if ($period->handover_at !== null && $period->opening_set_by === null) {
            return redirect()
                ->route('pettycash', $this->preserveParams($kind))
                ->with('info', 'Migrasi saldo sudah berjalan — tinggal diisi oleh operator cabang.');
        }

        // ── Wallet mana yang dipertahankan setelah migrasi ──────────────────
        // Modal mengirim keep[] = id wallet yang tetap dipakai cabang ini;
        // sisanya dipensiunkan UNTUK CABANG INI SAJA (cabang lain tak
        // tersentuh — itu inti kenapa peralihan bisa bertahap).
        //
        // Kalau keep[] tidak dikirim sama sekali (mis. POST lama / skrip),
        // tak ada yang dipensiunkan — migrasi tetap jalan sebagai
        // pengosongan saldo biasa. Diam lebih aman daripada menebak.
        $walletSekarang = FundSource::activeForBranch($period->raw_branch_id);
        $pensiunkan     = [];
        if ($period->raw_branch_id && $request->has('keep')) {
            $keep = array_map('intval', (array) $request->input('keep', []));
            $pensiunkan = $walletSekarang
                ->reject(fn ($fs) => in_array((int) $fs->id, $keep, true))
                ->values();

            // Harus menyisakan minimal satu wallet — cabang tanpa wallet
            // tidak bisa mencatat apa pun, dan tak ada jalan balik lewat UI.
            if ($pensiunkan->count() >= $walletSekarang->count()) {
                return redirect()
                    ->route('pettycash', $this->preserveParams($kind))
                    ->with('error', 'Pilih minimal satu wallet yang dipertahankan — cabang tidak boleh kehilangan semua wallet.');
            }
        }

        // Arsipkan rincian saldo lama SEBELUM dibuang.
        $openingBefore = $period->openingPerSourceResolved();
        $totalBefore   = array_sum($openingBefore);

        // Angka yang HILANG DARI LAYAR bukan $totalBefore. Kartu wallet
        // menampilkan saldo_awal + mutasi bulan berjalan, jadi yang benar-benar
        // dikosongkan dari pandangan operator adalah jumlah keduanya. Keduanya
        // dipisah karena dipakai untuk hal berbeda: $totalBefore = potret kolom
        // untuk old_values audit log (harus apa adanya), $totalTampil = angka
        // yang disebut ke manusia.
        $trxClass    = $this->trxClass($kind);
        $netBefore   = $this->netPerSource($period, $trxClass);
        $totalTampil = $totalBefore + array_sum($netBefore);

        // Nolkan saldo yang TAMPIL, bukan cuma kolom saldo awal. Kartu wallet
        // menampilkan saldo_awal + mutasi bulan ini, dan mutasinya sengaja
        // dipertahankan (transaksi tidak dihapus) — jadi supaya tiap kartu
        // benar-benar berbunyi Rp 0, saldo awal diisi lawan dari mutasinya.
        // Wallet tanpa transaksi cukup tidak masuk snapshot.
        $nol = [];
        foreach ($netBefore as $sid => $net) {
            if (abs($net) > 0.005) {
                $nol[$sid] = -$net;
            }
        }

        $period->handover_at             = now();
        $period->opening_set_by          = null;
        $period->source_opening_balances = $nol;
        $period->opening_balance         = array_sum($nol);
        $period->save();

        // Pensiunkan wallet yang tidak dipertahankan. Dilakukan SESUDAH saldo
        // dikosongkan supaya tidak ada wallet yang pensiun sambil masih
        // memegang angka — kartunya langsung hilang dari layar.
        $namaPensiun = [];
        if (! empty($pensiunkan) && $pensiunkan->isNotEmpty()) {
            FundSourceBranchAccount::retireFor(
                $period->raw_branch_id,
                $pensiunkan->pluck('id')->all(),
            );
            $namaPensiun = $pensiunkan->pluck('name')->all();
        }

        \App\Models\AuditLog::record(
            'update',
            sprintf(
                'Migrasi Saldo petty cash — %s %d/%d: Rp %s dikosongkan dari layar (saldo awal Rp %s + mutasi bulan berjalan), menunggu diisi operator cabang dari hitung fisik%s',
                $this->branchNameFor($period),
                $period->period_month,
                $period->period_year,
                number_format($totalTampil, 0, ',', '.'),
                number_format($totalBefore, 0, ',', '.'),
                $namaPensiun ? '. Wallet dipensiunkan di cabang ini: ' . implode(', ', $namaPensiun) : '',
            ),
            $period,
            ['source_opening_balances' => $openingBefore, 'opening_balance' => $totalBefore],
            ['source_opening_balances', 'opening_balance', 'handover_at', 'opening_set_by'],
        );

        return redirect()
            ->route('pettycash', $this->preserveParams($kind))
            ->with('success', sprintf(
                'Migrasi saldo dimulai — saldo Rp %s dikosongkan (saldo awal Rp %s + mutasi bulan berjalan).%s Operator cabang sekarang bisa mengisi saldo dari hitung fisik. Transaksi tidak ada yang terhapus.',
                number_format($totalTampil, 0, ',', '.'),
                number_format($totalBefore, 0, ',', '.'),
                $namaPensiun
                    ? ' Wallet ' . implode(' & ', $namaPensiun) . ' dipensiunkan untuk cabang ini (cabang lain tidak terpengaruh).'
                    : '',
            ));
    }

    /** Nama cabang pemilik periode — untuk pesan & audit log. */
    private function branchNameFor($period): string
    {
        if (! $period->raw_branch_id) return '-';

        return (string) (RawBranch::whereKey($period->raw_branch_id)->value('name') ?? $period->raw_branch_id);
    }

    /**
     * POST /pettycash/period/{id}/move
     *
     * Pindahkan (atau TUKAR) periode kas kecil ke cabang lain — untuk
     * membereskan salah cabang akibat rolling (mis. Della home Cinere yang
     * rolling ke Sunter tapi periode + pengeluarannya masih tercatat Cinere).
     *
     * Karena semua transaksi menempel ke periode via period_id, cukup ubah
     * raw_branch_id periode → seluruh ledger (pengeluaran + rincian saldo)
     * ikut pindah, tanpa copy/hapus baris.
     *
     *   - Cabang tujuan BELUM punya periode bulan ini → re-point 1 arah.
     *   - Cabang tujuan SUDAH punya periode (belum ditutup) → TUKAR cabang
     *     kedua periode (swap). Karena ada unique key
     *     (period_year, period_month, raw_branch_id), parkir sementara
     *     period_year salah satu periode ke sentinel supaya update di tengah
     *     tidak tabrakan (raw_branch_id = FK → tak bisa pakai nilai palsu).
     *
     * Guard: hanya Super Admin, periode asal (dan tujuan bila ada) belum
     * ditutup, cabang tujuan valid & ≠ cabang asal.
     */
    public function movePeriod(Request $request, $periodId)
    {
        $this->requireWriteAccess();
        if (! isSuperAdmin()) {
            abort(403, 'Hanya Super Admin yang bisa memindahkan periode antar cabang.');
        }

        $kind   = $this->kind($request);
        $period = PettyCashPeriod::findOrFail($periodId);

        if ($period->is_closed) {
            abort(403, 'Periode sudah ditutup — tidak bisa dipindah.');
        }

        $data = $request->validate([
            'target_branch_id' => 'required|string|exists:raw_branch,id',
        ]);
        $target = (string) $data['target_branch_id'];
        $source = (string) $period->raw_branch_id;

        if ($target === $source) {
            return redirect()
                ->route('pettycash', $this->preserveParams($kind))
                ->with('error', 'Cabang tujuan sama dengan cabang asal.');
        }

        // Periode cabang tujuan di bulan yang sama (kalau sudah ada).
        $targetPeriod = PettyCashPeriod::where('period_year', $period->period_year)
            ->where('period_month', $period->period_month)
            ->where('raw_branch_id', $target)
            ->first();

        if ($targetPeriod && $targetPeriod->is_closed) {
            abort(403, 'Periode cabang tujuan sudah ditutup — tidak bisa ditukar.');
        }

        DB::transaction(function () use ($period, $targetPeriod, $source, $target) {
            if (! $targetPeriod) {
                // Re-point 1 arah: seluruh transaksi ikut via period_id.
                $period->update(['raw_branch_id' => $target]);
                return;
            }
            // SWAP dua periode. Parkir period_year periode asal ke sentinel
            // dulu supaya update di tengah tidak melanggar unique
            // (period_year, period_month, raw_branch_id). Semua dalam 1
            // transaksi → state antara tidak terlihat dari luar.
            $origYear = $period->period_year;
            $period->update(['period_year' => 9999]);
            $targetPeriod->update(['raw_branch_id' => $source]);
            $period->update(['raw_branch_id' => $target, 'period_year' => $origYear]);
        });

        // Redirect ke cabang tujuan supaya Super Admin langsung melihat data
        // yang sudah pindah (branch dibawa via query — admin tak ter-lock).
        $params           = $this->preserveParams($kind);
        $params['branch'] = $target;

        $branchName = optional(RawBranch::find($target))->name ?? 'cabang tujuan';

        return redirect()
            ->route('pettycash', $params)
            ->with('success', "Periode berhasil dipindahkan ke {$branchName}.");
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

    /**
     * Hapus file bukti dari disk. Hanya menyentuh file di dalam folder
     * uploads/expense-receipts/ (guard supaya tak menghapus file lain).
     */
    private function deleteAttachmentFile(?string $path): void
    {
        if (! $path || strpos($path, 'uploads/expense-receipts/') !== 0) {
            return;
        }
        $abs = public_path($path);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    private function buildRows($period, $transactions, $fundSources = null, ?string $startDate = null, ?string $endDate = null, array $branchAccountMap = [], array $categories = [], string $search = '', int $fundSourceId = 0): array
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

        // Batas bulan yang DIMILIKI periode ini — dipakai mendeteksi baris
        // nyasar (trx_date di luar bulan periodenya). Sengaja diturunkan dari
        // $period, bukan dari $startDate/$endDate: dua yang terakhir adalah
        // filter pilihan user yang bisa dipersempit sampai satu hari.
        $periodMonthStart = sprintf('%04d-%02d-01', (int) $period->period_year, (int) $period->period_month);
        $periodMonthEnd   = date('Y-m-t', strtotime($periodMonthStart));
        $outsideCount = 0;
        $outsideTotal = 0.0;

        // Total & jumlah transaksi untuk kategori yang sedang difilter —
        // dipakai strip "filter aktif" biar nominal asli kategori jelas.
        $filterCatTotal = 0.0;
        $filterCatCount = 0;

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

        // Jaring pengaman wallet pensiunan.
        //
        // $fundSources hanya berisi wallet AKTIF. Kalau sebuah wallet
        // dinonaktifkan padahal masih memegang saldo (atau punya transaksi di
        // periode ini), saldonya jatuh ke bucket "Belum dikategorikan" — kartu
        // yang TIDAK bisa diklik, jadi uangnya mustahil dipindahkan lewat UI
        // dan terbawa carry-forward selamanya.
        //
        // Karena itu wallet nonaktif yang masih ada jejaknya di periode ini
        // tetap dibangunkan kartunya supaya bisa diklik & disapu bersih.
        // Dropdown tujuan tetap wallet aktif saja (FundSource::active() di
        // index()), jadi uang cuma bisa mengalir KE wallet yang masih dipakai.
        $extraIds = array_diff(
            array_merge(
                array_keys($openingPerSource ?: []),
                $transactions->pluck('fund_source_id')->filter()->unique()->all(),
            ),
            array_keys($sourceMeta),
        );
        $retiredIds = [];
        if (! empty($extraIds)) {
            foreach (FundSource::whereIn('id', $extraIds)->get() as $fs) {
                $retiredIds[$fs->id] = true;
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
            $rowCategory = $t->category ?? null;

            // Total kategori terfilter dari SELURUH transaksi periode (sebelum
            // baris disaring untuk tampilan) — buat strip "filter aktif". Hanya
            // baris pengeluaran (cashOut) yang dihitung.
            if (! empty($categories) && in_array($rowCategory, $categories, true) && $cashOut > 0) {
                $filterCatTotal += $cashOut;
                $filterCatCount++;
            }

            // Transfer = pergerakan INTERNAL antar sumber dana, bukan cash
            // flow eksternal. Jadi:
            //   - Saldo total TIDAK berubah (net 0: keluar dari A, masuk ke B)
            //   - Total Cash In / Cash Out di summary atas EXCLUDE transfer
            //   - Saldo running di tabel tetap konstan untuk row transfer
            //
            // Total Cash In = SEMUA uang masuk non-transfer, baik top-up maupun
            // setoran non-topup (pengembalian kasbon, setor cash, koreksi).
            // Dulu hanya is_topup=1 yang dihitung padahal $saldo menghitung
            // keduanya — akibatnya saldo akhir tak pernah sama dengan
            // opening + in − out. Keduanya kini digabung supaya cocok.
            if (! $isTransfer) {
                $saldo    += $cashIn - $cashOut;
                $totalIn  += $cashIn;
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

            // Baris "nyasar": tanggalnya di luar bulan periode yang menaunginya.
            // Terjadi karena period_id diambil dari halaman yang sedang dibuka,
            // sedangkan trx_date dulu bebas diketik — orang yang mau mencatat
            // pengeluaran bulan lalu (periodenya read-only) terpaksa mencatat
            // di bulan berjalan sambil memundurkan tanggal.
            //
            // Baris begini WAJIB tetap tampil: uangnya sudah ikut mengurangi
            // saldo & summary cards di atas, jadi kalau barisnya disembunyikan
            // filter tanggal, uangnya berkurang tanpa wujud di layar mana pun
            // (di periode tetangga juga tak muncul — beda period_id).
            // Karena itu ia dikecualikan dari filter tanggal, lalu ditandai
            // di tabel lewat flag 'outside_period'.
            $outsidePeriod = ($trxDateStr < $periodMonthStart || $trxDateStr > $periodMonthEnd);
            // Kaki IN sebuah Pindah Saldo tidak dihitung: sepasang OUT+IN
            // tampil sebagai SATU baris gabungan di tabel, jadi menghitung
            // keduanya bikin angka peringatan dobel.
            if ($outsidePeriod && ! ($isTransfer && $cashIn > 0)) {
                $outsideCount++;
                $outsideTotal += ($cashOut - $cashIn);
            }

            if (! $outsidePeriod) {
                if ($startDate !== null && $trxDateStr < $startDate) continue;
                if ($endDate   !== null && $trxDateStr > $endDate)   continue;
            }

            // Filter kategori/search/wallet — sama seperti tanggal: cuma
            // menyaring baris tampilan, saldo & summary sudah terlanjur
            // terhitung dari seluruh transaksi di atas.
            if (! empty($categories) && ! in_array($rowCategory, $categories, true)) continue;
            if ($search !== '' && stripos((string) $t->description, $search) === false) continue;
            if ($fundSourceId > 0 && (int) $t->fund_source_id !== $fundSourceId) continue;

            $fs = $t->fundSource;
            $rows[] = (object) [
                'id'              => $t->id,
                'trx_date'        => $t->trx_date,
                'description'     => $isTransfer
                    ? self::normalizeMoveVerb($t->description)
                    : $t->description,
                'category'        => $t->category ?? null,
                'is_topup'        => (bool) $t->is_topup,
                'is_transfer'     => $isTransfer,
                'is_composite'    => false,
                'outside_period'  => $outsidePeriod,
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
                'created_by'      => $t->created_by,
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
            // Ringkasan kategori yang sedang difilter (label di-inject controller).
            'filter_category_total' => $filterCatTotal,
            'filter_category_count' => $filterCatCount,
            // Baris nyasar (trx_date di luar bulan periode). Dipakai untuk
            // strip peringatan di atas tabel — angkanya ikut saldo periode
            // ini, jadi orang perlu tahu kalau ada.
            'outside_period_count'  => $outsideCount,
            'outside_period_total'  => $outsideTotal,
        ];

        // Drop source yang tidak punya aktivitas & balance 0 (selain Cash agar
        // selalu tampil sebagai anchor saldo awal).
        //
        // Wallet PENSIUNAN (nonaktif, ikut lewat jaring pengaman di atas)
        // dipakai aturan lebih ketat: hanya tampil selama masih MENYIMPAN
        // saldo. Begitu saldonya nol — mis. sudah disapu ke wallet aktif —
        // kartunya hilang; riwayat mutasinya tetap ada di tabel.
        $sourceBalances = [];
        foreach ($sourceMeta as $sid => $m) {
            if (isset($retiredIds[$sid])) {
                if (round((float) $m['balance'], 2) != 0.0) {
                    $sourceBalances[] = (object) $m;
                }
                continue;
            }
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
     * Samakan istilah baris pindah saldo LAMA saat render.
     *
     * Deskripsi transfer ditulis permanen ke DB saat dibuat, jadi baris lama
     * masih berbunyi "Tarik tunai ke …" / "Deposit dari …" / "Transfer ke …".
     * Daripada meng-UPDATE riwayat (yang berarti mengubah jejak audit), verb-nya
     * cukup dinormalkan saat dibaca. Hanya prefix di awal string yang diganti —
     * catatan user di belakang " — " tidak tersentuh.
     *
     * HANYA untuk baris is_transfer; deskripsi pengeluaran / top up jangan
     * ikut disentuh (bisa saja ada pengeluaran yang keterangannya diawali
     * kata "Transfer").
     */
    private static function normalizeMoveVerb(?string $desc): string
    {
        return preg_replace(
            '/^(Tarik tunai|Deposit|Transfer)\b/u',
            self::MOVE_VERB,
            (string) $desc,
        );
    }

    /**
     * Gabungkan baris pasangan transfer (ref_no sama) jadi 1 composite
     * row. Properti penting:
     *   - source_from / source_to : badge gabungan (mis. "BSI → Cash")
     *   - description gabungan: "Pindah saldo: BSI → Cash"
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

            // Deskripsi composite dihitung ulang tiap render (bukan dibaca dari
            // DB), jadi baris transfer LAMA — yang deskripsinya masih berbunyi
            // "Tarik tunai"/"Deposit" — otomatis ikut istilah baru tanpa perlu
            // mengubah satu pun data historis.
            $descriptionA = self::MOVE_VERB . ': ' . $outRow->source_name . ' → ' . $inRow->source_name;

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
                // Kaki OUT & IN selalu setanggal, jadi cukup ikut kaki OUT.
                'outside_period'  => $outRow->outside_period ?? false,
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
                // created_at (dari OUT leg) dipakai _table_rows untuk window
                // 24 jam tombol hapus transfer.
                'created_at'    => $outRow->created_at ?? null,
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

    /**
     * Pagar tanggal: transaksi harus bertanggal di dalam bulan periodenya.
     *
     * Sebelum ini `trx_date` cuma divalidasi `required|date` sementara
     * `period_id` diambil dari halaman yang sedang dibuka, sehingga
     * pengeluaran bisa tersimpan bertanggal Agustus di periode September.
     * Uangnya ikut mengurangi saldo September, tapi barisnya tak pernah
     * muncul di kedua bulan (Agustus beda period_id, September tersaring
     * filter tanggal). Pagar ini menutup pintu masuknya.
     *
     * Sengaja abort 403, bukan flash halus: ini form tampering atau input
     * di luar jalur — jalur normalnya sudah dibatasi min/max di input date.
     */
    private function abortIfDateOutsidePeriod($period, ?string $trxDate): void
    {
        if (! $trxDate) {
            return;
        }
        $date  = substr((string) $trxDate, 0, 10);
        $start = sprintf('%04d-%02d-01', (int) $period->period_year, (int) $period->period_month);
        $end   = date('Y-m-t', strtotime($start));

        if ($date < $start || $date > $end) {
            abort(403, 'Tanggal transaksi di luar bulan periode yang sedang dibuka. '
                . 'Buka periode yang sesuai, atau catat di bulan berjalan dengan tanggal bulan berjalan.');
        }
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
        // Pakai cabang efektif rolling-aware yang SAMA dengan resolveBranchId,
        // supaya user yang sedang rolling boleh mengubah data cabang rolling-nya
        // (bukan cuma cabang home dari session).
        $eff = $this->lockedBranchId();
        if ($eff && (string) $period->raw_branch_id !== (string) $eff) {
            abort(403, 'Anda tidak boleh mengubah data cabang lain.');
        }
    }

    /**
     * Hitung saldo current satu fund_source di periode: saldo awal per-source
     * (snapshot; fallback scalar ke Cash) + net semua transaksi aktif periode
     * untuk source itu.
     */
    /**
     * Rambatkan perubahan saldo awal ke periode-periode SESUDAHNYA yang
     * terlanjur dibuat.
     *
     * source_opening_balances itu SNAPSHOT yang diambil sekali saat periode
     * lahir (ensurePeriod), bukan turunan hidup. Jadi kalau periode Oktober
     * sudah pernah tercipta sebelum saldo awal September diisi ulang, Oktober
     * akan membawa angka lama — lengkap dengan wallet-wallet yang sudah
     * dipensiunkan — diam-diam sampai ada yang sadar.
     *
     * Periode yang sudah DITUTUP dilewati DAN menghentikan rantai: angkanya
     * sudah di-sign-off. Pola ini sama dengan
     * FinanceDashboardController::hitungUlangSaldoBerantai().
     *
     * @return int jumlah periode yang ikut diperbarui
     */
    private function rambatkanSaldoAwal($dari, string $periodClass, string $trxClass): int
    {
        $berikut = $periodClass::where('raw_branch_id', $dari->raw_branch_id)
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
            if ($p->is_closed) break;

            $opening = $sebelumnya->closing_balance !== null
                ? (float) $sebelumnya->closing_balance
                : (float) $sebelumnya->opening_balance + (float) $trxClass::netForPeriod($sebelumnya->id);

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
     * Mutasi bersih (masuk - keluar) per wallet untuk transaksi AKTIF di
     * periode ini: [fund_source_id => net].
     *
     * Dipakai untuk "mengunci" saldo tampil ke angka hasil hitung fisik.
     * Kartu wallet menampilkan saldo_awal + mutasi, jadi supaya kartunya
     * berbunyi persis seperti yang diketik operator, saldo awal disimpan
     * sebagai (angka_yang_diketik - mutasi). Transaksinya sendiri tidak
     * disentuh — cuma titik awalnya yang digeser.
     *
     * Transaksi tanpa wallet (fund_source_id NULL) di-skip, sama seperti
     * PettyCashPeriod::computeClosingPerSource().
     *
     * @return array<int,float>
     */
    private function netPerSource($period, string $trxClass): array
    {
        $net = [];
        $rows = $trxClass::active()
            ->forPeriod($period->id)
            ->whereNotNull('fund_source_id')
            ->get(['fund_source_id', 'direction', 'amount']);

        foreach ($rows as $r) {
            $sid = (int) $r->fund_source_id;
            $amt = (float) $r->amount;
            $net[$sid] = ($net[$sid] ?? 0.0) + ($r->direction === $trxClass::DIR_IN ? $amt : -$amt);
        }

        return $net;
    }

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
            $balance += $r->direction === $trxClass::DIR_IN ? (float) $r->amount : -(float) $r->amount;
        }

        return $balance;
    }

    /**
     * Ambil daftar kategori terpilih dari request (multi-select). Menerima
     * param `categories[]` (baru) maupun `category` tunggal (backward compat).
     * Hanya key yang valid di $categoryOptions yang dikembalikan, tanpa
     * duplikat. Array kosong = semua kategori.
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
}
