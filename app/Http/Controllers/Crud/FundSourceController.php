<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\FundSource;
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

        return view('crud.fundsource.index', compact('sources', 'categoryLabels'));
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
     */
    public function toggle(Request $request, $id)
    {
        $this->guard();

        $fs = FundSource::findOrFail($id);
        $fs->is_active = $fs->is_active ? 0 : 1;
        $fs->save();

        $status = $fs->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->route('fundsource')
            ->with('success', 'Sumber dana "' . $fs->name . '" berhasil ' . $status . '.');
    }

    /* ───────── Helpers ───────── */

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
