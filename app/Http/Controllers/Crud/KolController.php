<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawAffiliate;
use App\Models\RawBranch;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * KolController
 *
 * Mengelola halaman "KOL Bulan Ini" dan "KOL Existing" yang dipindahkan
 * dari Notion ke Data Center. Data sumber tetap dari tabel `raw_affiliate`
 * (filter: type='kol').
 *
 * Logic pemisahan Bulan Ini vs Existing (murni berbasis tanggal):
 *   - Bulan Ini  : tanggal_approach DI bulan berjalan ATAU NULL (KOL baru tambah,
 *                  belum ada jadwal approach)
 *   - Existing   : tanggal_approach SEBELUM bulan berjalan (apapun progress-nya)
 *
 * Catatan: progress (waiting_confirmation, akan_di_approach, dll) TIDAK lagi
 * dipakai sebagai kriteria pemisahan — kalau bulan-nya sudah lewat, otomatis
 * pindah ke Existing meskipun statusnya masih menggantung.
 */
class KolController extends Controller
{
    /**
     * Halaman KOL Bulan Ini (default index).
     * URL: /kol
     */
    public function index()
    {
        // ─── Filter dari request ─────────────────────────────────────────
        $filterBranch   = request('branch', 'all');
        $filterProgress = request('progress', 'all');
        $filterKategori = request('kategori', 'all');
        $search         = trim((string) request('q', ''));
        $view           = request('view', 'table');   // 'table' | 'calendar'
        $weekOffset     = (int) request('week', 0);   // 0 = minggu ini, -1 = lalu, +1 = depan

        // ─── Base query: KOL aktif bulan ini ─────────────────────────────
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();

        $baseQuery = RawAffiliate::where('type', 'kol')
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                // Tanggal approach di bulan ini
                $q->whereBetween('tanggal_approach', [$startOfMonth, $endOfMonth])
                  // Atau belum ada tanggal approach (KOL baru, belum dijadwalkan)
                  ->orWhereNull('tanggal_approach');
            });

        // ─── Hitung jumlah KOL per cabang (untuk summary cards) ──────────
        // Catatan: dihitung dari baseQuery (sebelum filter cabang) supaya
        // angka di kartu cabang selalu konsisten.
        $branchCounts = (clone $baseQuery)
            ->selectRaw('raw_branch_id, COUNT(*) as total')
            ->groupBy('raw_branch_id')
            ->pluck('total', 'raw_branch_id')
            ->toArray();

        $branches = RawBranch::orderBy('name')->get()->map(function ($b) use ($branchCounts) {
            $b->kol_count = $branchCounts[$b->id] ?? 0;
            return $b;
        });

        $totalCount = array_sum($branchCounts);

        // ─── Apply filter user ke query yang akan ditampilkan ────────────
        $query = (clone $baseQuery)->with(['branch', 'docService']);

        if ($filterBranch !== 'all') {
            $query->where('raw_branch_id', $filterBranch);
        }
        if ($filterProgress !== 'all') {
            $query->where('kol_progress', $filterProgress);
        }
        if ($filterKategori !== 'all') {
            $query->where('kol_kategori', $filterKategori);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('social_username', 'like', "%{$search}%")
                  ->orWhere('social_instagram', 'like', "%{$search}%")
                  ->orWhere('social_tiktok', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $kols = $query
            ->orderByRaw("FIELD(kol_progress, 'confirm', 'waiting_confirmation', 'akan_di_approach', 'no_respon', 'done')")
            ->orderBy('name')
            ->paginate($this->perPage)
            ->appends(request()->query());

        // ─── Active branch object (kalau filter branch dipilih) ──────────
        $activeBranch = $filterBranch !== 'all'
            ? $branches->firstWhere('id', $filterBranch)
            : null;

        // ─── Monthly card view data (Tampilan Bulanan) ──────────────────
        // Pakai partial `_existing_monthly` yang sama dengan halaman Existing.
        // Bedanya: di sini hanya KOL bulan berjalan (tanggal_approach di bulan
        // ini). KOL null tanggal_approach tidak masuk card view supaya group
        // bulan tidak punya bucket "—".
        $monthlyKols = (clone $query)
            ->whereNotNull('tanggal_approach')
            ->whereBetween('tanggal_approach', [$startOfMonth, $endOfMonth])
            ->orderBy('tanggal_approach')
            ->orderBy('name')
            ->limit(500)
            ->get();

        $kolsByMonth = $monthlyKols
            ->groupBy(fn ($k) => $k->tanggal_approach?->format('Y-m'))
            ->sortKeysDesc();

        $monthlyTotal  = $monthlyKols->count();
        $monthlyCapped = $monthlyTotal >= 500;

        // ─── Calendar view data (Mon-Sat current week, dengan offset opsional) ──
        $weekDays   = [];
        $kolsByDay  = collect();
        $weekLabel  = '';
        if ($view === 'calendar') {
            $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeeks($weekOffset);
            $endOfWeek   = $startOfWeek->copy()->addDays(5); // Saturday

            $weekKolsQuery = RawAffiliate::where('type', 'kol')
                ->whereBetween('tanggal_approach', [$startOfWeek, $endOfWeek])
                ->with('branch');

            // Apply same filters supaya konsisten dengan table view
            if ($filterBranch !== 'all')   $weekKolsQuery->where('raw_branch_id', $filterBranch);
            if ($filterProgress !== 'all') $weekKolsQuery->where('kol_progress', $filterProgress);
            if ($filterKategori !== 'all') $weekKolsQuery->where('kol_kategori', $filterKategori);
            if ($search !== '') {
                $weekKolsQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('social_username', 'like', "%{$search}%")
                      ->orWhere('social_instagram', 'like', "%{$search}%")
                      ->orWhere('social_tiktok', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $weekKols = $weekKolsQuery->orderBy('tanggal_approach')->get();
            $kolsByDay = $weekKols->groupBy(fn($k) => $k->tanggal_approach->format('Y-m-d'));

            for ($i = 0; $i < 6; $i++) {
                $d = $startOfWeek->copy()->addDays($i);
                $weekDays[] = [
                    'date'       => $d->format('Y-m-d'),
                    'day_name'   => $d->translatedFormat('l'),
                    'short_name' => $d->translatedFormat('D'),
                    'day_num'    => $d->format('j'),
                    'is_today'   => $d->isToday(),
                ];
            }

            $weekLabel = $startOfWeek->translatedFormat('d M') . ' – ' . $endOfWeek->translatedFormat('d M Y');
        }

        return view('crud.kol.list', compact(
            'kols',
            'branches',
            'totalCount',
            'filterBranch',
            'filterProgress',
            'filterKategori',
            'search',
            'activeBranch',
            'view',
            'weekDays',
            'kolsByDay',
            'weekLabel',
            'weekOffset',
            'kolsByMonth',
            'monthlyTotal',
            'monthlyCapped'
        ));
    }

    /**
     * Halaman KOL Existing — arsip dari bulan-bulan sebelumnya.
     * URL: /kol/existing
     */
    public function existing()
    {
        // ─── Filter dari request ─────────────────────────────────────────
        $filterBranch   = request('branch', 'all');
        $filterTahun    = request('tahun', 'all');
        $filterBulan    = request('bulan', 'all');     // 1-12 atau 'all'
        $filterPlatform = request('platform', 'all');
        $search         = trim((string) request('q', ''));

        $startOfMonth = Carbon::now()->startOfMonth();

        // ─── Base query: KOL dari bulan sebelumnya ───────────────────────
        // Semua KOL dengan tanggal_approach < bulan berjalan masuk sini,
        // apapun status progress-nya. Filter progress diserahkan ke user
        // lewat dropdown filter di halaman.
        $baseQuery = RawAffiliate::where('type', 'kol')
            ->whereNotNull('tanggal_approach')
            ->where('tanggal_approach', '<', $startOfMonth);

        // ─── Branch counts ───────────────────────────────────────────────
        $branchCounts = (clone $baseQuery)
            ->selectRaw('raw_branch_id, COUNT(*) as total')
            ->groupBy('raw_branch_id')
            ->pluck('total', 'raw_branch_id')
            ->toArray();

        $branches = RawBranch::orderBy('name')->get()->map(function ($b) use ($branchCounts) {
            $b->kol_count = $branchCounts[$b->id] ?? 0;
            return $b;
        });

        $totalCount = array_sum($branchCounts);

        // ─── Daftar tahun yang tersedia (untuk filter dropdown) ──────────
        $availableYears = (clone $baseQuery)
            ->selectRaw('YEAR(tanggal_approach) as tahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->filter() // buang null
            ->values();

        // ─── Apply filter ────────────────────────────────────────────────
        $query = (clone $baseQuery)->with('branch');

        if ($filterBranch !== 'all') {
            $query->where('raw_branch_id', $filterBranch);
        }
        if ($filterTahun !== 'all') {
            $query->whereYear('tanggal_approach', $filterTahun);
        }
        if ($filterBulan !== 'all') {
            $query->whereMonth('tanggal_approach', $filterBulan);
        }
        if ($filterPlatform !== 'all') {
            // Platform bisa stored di kolom 'social_platform' (single) atau di
            // kolom social_<platform> spesifik. Cek keduanya supaya filter robust.
            $query->where(function ($q) use ($filterPlatform) {
                $q->where('social_platform', $filterPlatform);
                if ($filterPlatform === 'tiktok')    $q->orWhereNotNull('social_tiktok');
                if ($filterPlatform === 'instagram') $q->orWhereNotNull('social_instagram');
                if ($filterPlatform === 'youtube')   $q->orWhereNotNull('social_youtube');
            });
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('social_username', 'like', "%{$search}%")
                  ->orWhere('social_instagram', 'like', "%{$search}%")
                  ->orWhere('social_tiktok', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        $kols = $query
            ->orderByDesc('tanggal_approach')
            ->orderBy('name')
            ->paginate($this->perPage)
            ->appends(request()->query());

        // ─── Monthly view data (untuk accordion bulanan di bawah tabel) ─
        // Re-query dengan filter yang sama (tanpa pagination), limit 500 supaya
        // page tidak balon kalau filter sangat lebar.
        $monthlyKols = (clone $query)
            ->orderByDesc('tanggal_approach')
            ->orderBy('name')
            ->limit(500)
            ->get();

        $kolsByMonth = $monthlyKols
            ->groupBy(fn ($k) => $k->tanggal_approach?->format('Y-m'))
            ->sortKeysDesc();

        $monthlyTotal = $monthlyKols->count();
        $monthlyCapped = $monthlyTotal >= 500;

        $activeBranch = $filterBranch !== 'all'
            ? $branches->firstWhere('id', $filterBranch)
            : null;

        return view('crud.kol.existing', compact(
            'kols',
            'branches',
            'totalCount',
            'availableYears',
            'filterBranch',
            'filterTahun',
            'filterBulan',
            'filterPlatform',
            'search',
            'activeBranch',
            'kolsByMonth',
            'monthlyTotal',
            'monthlyCapped'
        ));
    }

    /**
     * Update kol_progress dari dropdown inline di tabel KOL Bulan Ini.
     * URL: PATCH /kol/{id}/progress
     * Returns JSON: { progress, label, color }
     */
    public function updateProgress(Request $request, $id)
    {
        $allowed = array_keys(RawAffiliate::kolProgressOptions());
        $validated = $request->validate([
            'progress' => 'required|in:' . implode(',', $allowed),
        ]);

        $kol = RawAffiliate::where('type', 'kol')->findOrFail($id);
        $kol->kol_progress = $validated['progress'];
        $kol->save();

        $opt = RawAffiliate::kolProgressOptions()[$kol->kol_progress];

        return response()->json([
            'progress' => $kol->kol_progress,
            'label'    => $opt['label'],
            'color'    => $opt['color'],
        ]);
    }

    /**
     * Update rencana_perawatan dari textarea inline di tabel KOL Bulan Ini.
     * URL: PATCH /kol/{id}/rencana-perawatan
     * Returns JSON: { rencana_perawatan }
     */
    public function updateRencanaPerawatan(Request $request, $id)
    {
        $validated = $request->validate([
            'rencana_perawatan' => 'nullable|string|max:2000',
        ]);

        $kol = RawAffiliate::where('type', 'kol')->findOrFail($id);
        $kol->rencana_perawatan = trim((string) ($validated['rencana_perawatan'] ?? '')) ?: null;
        $kol->save();

        return response()->json([
            'rencana_perawatan' => $kol->rencana_perawatan,
        ]);
    }

    /**
     * Update kol_status_relasi dari dropdown inline di tabel KOL Bulan Ini.
     * URL: PATCH /kol/{id}/status-relasi
     * Returns JSON: { kol_status_relasi }
     */
    public function updateStatusRelasi(Request $request, $id)
    {
        $allowed = array_keys(RawAffiliate::kolStatusRelasiOptions());
        $validated = $request->validate([
            'kol_status_relasi' => 'nullable|in:' . implode(',', $allowed),
        ]);

        $kol = RawAffiliate::where('type', 'kol')->findOrFail($id);
        $kol->kol_status_relasi = $validated['kol_status_relasi'] ?: null;
        $kol->save();

        return response()->json([
            'kol_status_relasi' => $kol->kol_status_relasi,
        ]);
    }

    /**
     * Update feedback dari textarea inline di tabel KOL Bulan Ini.
     * URL: PATCH /kol/{id}/feedback
     * Returns JSON: { feedback }
     */
    public function updateFeedback(Request $request, $id)
    {
        $validated = $request->validate([
            'feedback' => 'nullable|string|max:5000',
        ]);

        $kol = RawAffiliate::where('type', 'kol')->findOrFail($id);
        $kol->feedback = trim((string) ($validated['feedback'] ?? '')) ?: null;
        $kol->save();

        return response()->json([
            'feedback' => $kol->feedback,
        ]);
    }

    /**
     * Download template CSV untuk import KOL.
     * URL: GET /kol/csv-template
     */
    public function downloadCsvTemplate()
    {
        $headers = [
            'name', 'social_username', 'social_platform', 'followers_count',
            'kol_kategori', 'kol_progress', 'kol_status_relasi',
            'tanggal_approach', 'rate_card', 'contact_person', 'phone',
            'branch_name', 'feedback',
        ];

        $sample = [
            'Risma Aulia', 'rismaaulia', 'instagram', '50000',
            'moms_creator', 'done', 'rekomendasi',
            '2025-12-15', 'Rp 5.000.000', 'Manager Risma 0812xxx', '081234567890',
            'Pamulang', 'Aktif posting, konversi bagus',
        ];

        $callback = function () use ($headers, $sample) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, $sample);
            fclose($out);
        };

        return response()->streamDownload($callback, 'template_kol_import.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Import KOL dari file CSV.
     * URL: POST /kol/import-csv
     *
     * Header CSV (case-insensitive, spasi → underscore):
     *   name, social_username, social_platform, followers_count,
     *   kol_kategori, kol_progress, kol_status_relasi,
     *   tanggal_approach, rate_card, contact_person, phone,
     *   branch_name, feedback, email
     *
     * Validasi: name wajib, enum di-validate (invalid → null). Branch match by name.
     */
    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $handle = fopen($request->file('csv_file')->getPathname(), 'r');
        if (!$handle) {
            return redirect()->route('kol.existing')->with('error', 'Gagal membuka file CSV.');
        }

        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            fclose($handle);
            return redirect()->route('kol.existing')->with('error', 'CSV kosong atau tidak valid.');
        }

        // Normalize header: lowercase + spaces → underscore + trim BOM
        $headers = array_map(function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // strip UTF-8 BOM
            return strtolower(trim(str_replace([' ', '-'], '_', $h)));
        }, $rawHeaders);

        // Pre-build lookup maps (sekali, supaya tidak hit DB per-row)
        $branches = RawBranch::all()
            ->mapWithKeys(fn($b) => [strtolower(trim($b->name)) => $b->id])
            ->toArray();
        $kategoriKeys = array_keys(RawAffiliate::kolKategoriOptions());
        $progressKeys = array_keys(RawAffiliate::kolProgressOptions());
        $statusKeys   = array_keys(RawAffiliate::kolStatusRelasiOptions());
        $platformKeys = array_keys(RawAffiliate::socialPlatforms());

        $created = 0;
        $skipped = 0;
        $errors  = [];
        $rowNum  = 1; // header di row 1

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            // Skip baris kosong
            if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            // Pad row supaya cocok dengan jumlah header
            $row  = array_pad($row, count($headers), '');
            $data = array_combine($headers, array_slice($row, 0, count($headers)));

            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                $skipped++;
                $errors[] = "Baris {$rowNum}: nama kosong, dilewati.";
                continue;
            }

            // Validate enum — empty atau invalid value → null (silent).
            // Empty string PENTING di-coerce ke null karena kolom-kolom ini ENUM di MySQL:
            // insert '' akan trigger "Data truncated for column ..." dan seluruh baris gagal.
            $kategori     = trim((string) ($data['kol_kategori']      ?? '')) ?: null;
            $progress     = trim((string) ($data['kol_progress']      ?? '')) ?: null;
            $statusRelasi = trim((string) ($data['kol_status_relasi'] ?? '')) ?: null;
            $platform     = trim((string) ($data['social_platform']   ?? '')) ?: null;

            if ($kategori     && !in_array($kategori, $kategoriKeys, true))    $kategori = null;
            if ($progress     && !in_array($progress, $progressKeys, true))    $progress = null;
            if ($statusRelasi && !in_array($statusRelasi, $statusKeys, true))  $statusRelasi = null;
            if ($platform     && !in_array($platform, $platformKeys, true))    $platform = null;

            // Resolve branch by name (case-insensitive)
            $branchName = trim((string) ($data['branch_name'] ?? $data['branch'] ?? ''));
            $branchId   = $branchName ? ($branches[strtolower($branchName)] ?? null) : null;

            // Parse tanggal_approach (terima format apa saja yang Carbon bisa parse)
            $tglApproach = null;
            $tglStr = trim((string) ($data['tanggal_approach'] ?? ''));
            if ($tglStr !== '') {
                try {
                    $tglApproach = Carbon::parse($tglStr)->toDateString();
                } catch (\Exception $e) {
                    // skip date, biarkan null
                }
            }

            $followers = trim((string) ($data['followers_count'] ?? ''));
            $followers = $followers !== '' ? (int) preg_replace('/[^\d]/', '', $followers) : null;

            try {
                RawAffiliate::create([
                    'id'                  => \Illuminate\Support\Str::uuid()->toString(),
                    'name'                => $name,
                    'type'                => 'kol',
                    'role'                => 'influencer',
                    'raw_branch_id'       => $branchId,
                    'phone'               => trim((string) ($data['phone']            ?? '')) ?: null,
                    'email'               => trim((string) ($data['email']            ?? '')) ?: null,
                    'social_platform'     => $platform ?: null,
                    'social_username'     => trim((string) ($data['social_username']  ?? '')) ?: null,
                    'social_instagram'    => trim((string) ($data['social_instagram'] ?? '')) ?: null,
                    'social_tiktok'       => trim((string) ($data['social_tiktok']    ?? '')) ?: null,
                    'social_youtube'      => trim((string) ($data['social_youtube']   ?? '')) ?: null,
                    'followers_count'     => $followers,
                    'kol_kategori'        => $kategori,
                    'kol_progress'        => $progress,
                    'kol_status_relasi'   => $statusRelasi,
                    'tanggal_approach'    => $tglApproach,
                    'rate_card'           => trim((string) ($data['rate_card']        ?? '')) ?: null,
                    'contact_person'      => trim((string) ($data['contact_person']   ?? '')) ?: null,
                    'feedback'            => trim((string) ($data['feedback']         ?? '')) ?: null,
                    'commission_rate'     => 0,
                    'is_active'           => 1,
                    'registration_status' => RawAffiliate::REG_STATUS_APPROVED,
                    'created_at'          => now(),
                ]);
                $created++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Baris {$rowNum}: " . $e->getMessage();
            }
        }

        fclose($handle);

        $msg = "Import selesai: {$created} KOL ditambahkan, {$skipped} dilewati.";
        if (!empty($errors)) {
            $msg .= ' Catatan: ' . implode(' | ', array_slice($errors, 0, 3));
            if (count($errors) > 3) $msg .= ' (+' . (count($errors) - 3) . ' error lain)';
        }

        return redirect()->route('kol.existing')->with($created > 0 ? 'success' : 'error', $msg);
    }
}
