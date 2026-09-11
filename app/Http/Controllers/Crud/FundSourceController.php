<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\FundSource;
use App\Models\PettyCashCategory;
use App\Models\PettyCashTransaction;
use Illuminate\Http\Request;

/**
 * FundSourceController
 *
 * UI untuk Super Admin mengelola master sumber dana:
 *   - Upload / ganti logo brand
 *   - Edit nama, warna brand, label pendek
 *   - Toggle aktif/nonaktif
 *   - Tambah source baru (mis. bank tambahan, e-wallet baru)
 *
 * Akses dibatasi via guard isSuperAdmin() — bukan Super Admin akan
 * di-redirect ke dashboard dengan pesan error.
 */
class FundSourceController extends Controller
{
    /** Batas ukuran file logo (KB). */
    private const MAX_LOGO_KB = 1024;

    /** Folder upload (relatif ke public/). */
    private const UPLOAD_DIR = 'assets/logo';

    private function guard(): void
    {
        if (! isSuperAdmin()) {
            abort(403, 'Halaman ini hanya untuk Super Admin.');
        }
    }

    /**
     * GET /fund-sources
     */
    public function index(Request $request)
    {
        $this->guard();

        $sources = FundSource::orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categoryLabels = [
            FundSource::CAT_CASH    => 'Cash / Tunai',
            FundSource::CAT_BANK    => 'Bank',
            FundSource::CAT_EWALLET => 'E-Wallet',
        ];

        // Kategori pengeluaran petty cash (dikelola di halaman ini juga).
        // + hitung berapa transaksi memakai tiap kategori → yang terpakai
        //   tak boleh dihapus (hanya dinonaktifkan).
        $expenseCategories = PettyCashCategory::orderBy('sort_order')->orderBy('label')->get();
        $categoryUsage = PettyCashTransaction::query()
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as cnt')
            ->groupBy('category')
            ->pluck('cnt', 'category')
            ->all();

        return view('crud.fundsource.index', compact(
            'sources', 'categoryLabels', 'expenseCategories', 'categoryUsage'
        ));
    }

    /**
     * POST /fund-sources → tambah source baru.
     */
    public function store(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'category'    => 'required|in:cash,bank,ewallet',
            'code'        => 'required|string|max:50|unique:fund_sources,code|regex:/^[a-z0-9_]+$/',
            'name'        => 'required|string|max:100',
            'brand_color' => 'nullable|string|max:7|regex:/^#[0-9a-fA-F]{6}$/',
            'short_label' => 'nullable|string|max:10',
            'sort_order'  => 'nullable|integer|min:0|max:9999',
            'logo'        => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:' . self::MAX_LOGO_KB,
        ], [
            'code.regex' => 'Code hanya boleh huruf kecil, angka, dan underscore.',
        ]);

        $fs = FundSource::create([
            'category'    => $data['category'],
            'code'        => $data['code'],
            'name'        => $data['name'],
            'brand_color' => $data['brand_color'] ?? null,
            'short_label' => $data['short_label'] ?? null,
            'sort_order'  => $data['sort_order'] ?? 0,
            'is_active'   => 1,
        ]);

        if ($request->hasFile('logo')) {
            $fs->logo_url = $this->saveLogo($request->file('logo'), $fs->code);
            $fs->save();
        }

        return redirect()->route('fundsource')
            ->with('success', 'Sumber dana "' . $fs->name . '" berhasil ditambahkan.');
    }

    /**
     * POST /fund-sources/{id} → update metadata + logo.
     */
    public function update(Request $request, $id)
    {
        $this->guard();

        $fs = FundSource::findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'brand_color' => 'nullable|string|max:7|regex:/^#[0-9a-fA-F]{6}$/',
            'short_label' => 'nullable|string|max:10',
            'sort_order'  => 'nullable|integer|min:0|max:9999',
            'logo'        => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:' . self::MAX_LOGO_KB,
            'remove_logo' => 'nullable|in:1',
        ]);

        $fs->name        = $data['name'];
        $fs->brand_color = $data['brand_color'] ?? null;
        $fs->short_label = $data['short_label'] ?? null;
        $fs->sort_order  = $data['sort_order'] ?? 0;

        if ($request->boolean('remove_logo')) {
            $this->deleteLogoFile($fs->logo_url);
            $fs->logo_url = null;
        }

        if ($request->hasFile('logo')) {
            $this->deleteLogoFile($fs->logo_url);
            $fs->logo_url = $this->saveLogo($request->file('logo'), $fs->code);
        }

        $fs->save();

        return redirect()->route('fundsource')
            ->with('success', 'Sumber dana "' . $fs->name . '" berhasil diperbarui.');
    }

    /**
     * POST /fund-sources/{id}/toggle → aktif/nonaktifkan source.
     *
     * Menonaktifkan wallet yang MASIH menyimpan saldo itu jebakan: wallet
     * nonaktif hilang dari daftar wallet aktif, sehingga saldonya jatuh ke
     * kartu "Belum dikategorikan" yang tak bisa diklik — uangnya jadi mustahil
     * dipindahkan lewat UI, dan carry-forward membawanya ke bulan-bulan
     * berikutnya selamanya. Jadi arah aktif → nonaktif dijaga: kosongkan dulu
     * saldonya lewat "Pindah Saldo" di halaman Petty Cash cabang.
     *
     * Arah sebaliknya (mengaktifkan kembali) tidak perlu dicek.
     */
    public function toggle(Request $request, $id)
    {
        $this->guard();

        $fs = FundSource::findOrFail($id);

        if ($fs->is_active) {
            $stuck = $this->saldoTersangkut($fs);
            if (! empty($stuck)) {
                return redirect()->route('fundsource')->with('error',
                    'Wallet "' . $fs->name . '" masih menyimpan saldo di bulan berjalan / bulan berikutnya — '
                    . implode(' · ', $stuck)
                    . '. Pindahkan dulu lewat "Pindah Saldo" di halaman Petty Cash cabang, baru nonaktifkan.');
            }
        }

        $fs->is_active = $fs->is_active ? 0 : 1;
        $fs->save();

        $status = $fs->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->route('fundsource')
            ->with('success', 'Sumber dana "' . $fs->name . '" berhasil ' . $status . '.');
    }

    /* ───────── Helpers ───────── */

    /**
     * Daftar "cabang bulan: Rp X" di mana wallet ini masih memegang saldo,
     * dibatasi ke periode BULAN BERJALAN & SESUDAHNYA yang belum ditutup.
     *
     * Sengaja pakai computeClosingPerSource() — bukan query mentah — karena
     * itu rumus yang sama persis dipakai buildRows() & carry-forward
     * (opening snapshot + net transaksi aktif). Jadi guard ini tidak akan
     * pernah berbeda pendapat dengan angka yang tampil di layar.
     *
     * Kenapa bulan lampau TIDAK ikut dicek, walau periodenya belum ditutup:
     *   1. Bulan lampau sudah read-only di UI (PettyCashController::index()
     *      menyetel $isReadOnly kalau bukan bulan berjalan), jadi saldo di sana
     *      tidak bisa bertambah maupun berkurang — itu angka historis, bukan
     *      risiko uang tersangkut.
     *   2. Satu-satunya jalur saldo lama merembes ke depan adalah carry-forward,
     *      dan rantai itu putus begitu saldo awal bulan berjalan diisi ulang.
     *   3. Kartu wallet pensiunan tetap dibangkitkan buildRows(), jadi laporan
     *      bulan lampau tetap terbaca benar walau wallet-nya sudah nonaktif.
     * Kalau bulan lampau ikut dicek, wallet lama tidak akan pernah bisa
     * dinonaktifkan — periode praktis tidak pernah ditutup di sistem ini.
     *
     * @return string[]
     */
    private function saldoTersangkut(FundSource $fs): array
    {
        $stuck = [];
        $now   = \Carbon\Carbon::today();

        $periods = \App\Models\PettyCashPeriod::where('is_closed', 0)
            ->where(function ($q) use ($now) {
                $q->where('period_year', '>', $now->year)
                  ->orWhere(function ($qq) use ($now) {
                      $qq->where('period_year', $now->year)
                         ->where('period_month', '>=', $now->month);
                  });
            })
            ->get();
        if ($periods->isEmpty()) {
            return $stuck;
        }

        $branchNames = \App\Models\RawBranch::whereIn(
            'id',
            $periods->pluck('raw_branch_id')->filter()->unique()->values(),
        )->pluck('name', 'id');

        foreach ($periods as $p) {
            $saldo = (float) ($p->computeClosingPerSource()[$fs->id] ?? 0.0);
            if (abs($saldo) <= 0.005) continue;

            $stuck[] = ($branchNames[$p->raw_branch_id] ?? $p->raw_branch_id)
                . ' ' . $p->period_month . '/' . $p->period_year
                . ': Rp ' . number_format($saldo, 0, ',', '.');
        }

        return $stuck;
    }

    private function saveLogo($file, string $code): string
    {
        $uploadDir = public_path(self::UPLOAD_DIR);
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $filename  = $code . '_' . time() . '.' . $extension;
        $file->move($uploadDir, $filename);

        return '/' . self::UPLOAD_DIR . '/' . $filename;
    }

    private function deleteLogoFile(?string $logoUrl): void
    {
        if (! $logoUrl) return;
        // Hanya hapus kalau path-nya di dalam folder upload kita,
        // supaya tidak menghapus file system lain (mis. logo lama yg
        // sudah di-set manual dari assets shared).
        $expectedPrefix = '/' . self::UPLOAD_DIR . '/';
        if (strpos($logoUrl, $expectedPrefix) !== 0) {
            return;
        }
        $abs = public_path(ltrim($logoUrl, '/'));
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
