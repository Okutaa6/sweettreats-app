<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\PettyCashCategory;
use App\Models\PettyCashTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * PettyCashCategoryController
 *
 * CRUD master kategori pengeluaran petty cash. Diakses Super Admin dari
 * halaman "Kelola Wallet" (/fund-sources) — list-nya dirender oleh
 * FundSourceController@index, aksi CRUD-nya di sini.
 *
 * Aturan:
 *   - `key` di-generate otomatis dari label (slug underscore) & immutable.
 *   - Kategori sistem (is_system) tak boleh dinonaktifkan
 *     / dihapus.
 *   - Kategori yang sudah dipakai transaksi tak boleh dihapus permanen
 *     (cukup dinonaktifkan) supaya badge riwayat lama tetap benar.
 */
class PettyCashCategoryController extends Controller
{
    private function guard(): void
    {
        if (! isSuperAdmin()) {
            abort(403, 'Halaman ini hanya untuk Super Admin.');
        }
    }

    /**
     * POST /petty-cash-categories → tambah kategori baru.
     */
    public function store(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'label'      => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $key = $this->makeUniqueKey($data['label']);

        PettyCashCategory::create([
            'key'        => $key,
            'label'      => $data['label'],
            'is_system'  => 0,
            'is_active'  => 1,
            'sort_order' => $data['sort_order'] ?? $this->nextSortOrder(),
        ]);

        return redirect()->route('fundsource')
            ->with('success', 'Kategori "' . $data['label'] . '" berhasil ditambahkan.');
    }

    /**
     * POST /petty-cash-categories/{id} → ubah label / urutan. Key immutable.
     */
    public function update(Request $request, $id)
    {
        $this->guard();

        $cat = PettyCashCategory::findOrFail($id);

        $data = $request->validate([
            'label'      => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $cat->label      = $data['label'];
        $cat->sort_order = $data['sort_order'] ?? $cat->sort_order;
        $cat->save();

        return redirect()->route('fundsource')
            ->with('success', 'Kategori "' . $cat->label . '" berhasil diperbarui.');
    }

    /**
     * POST /petty-cash-categories/{id}/toggle → aktif/nonaktifkan.
     */
    public function toggle(Request $request, $id)
    {
        $this->guard();

        $cat = PettyCashCategory::findOrFail($id);
        if ($cat->is_system) {
            return redirect()->route('fundsource')
                ->with('error', 'Kategori sistem "' . $cat->label . '" tidak bisa dinonaktifkan.');
        }

        $cat->is_active = $cat->is_active ? 0 : 1;
        $cat->save();

        $status = $cat->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->route('fundsource')
            ->with('success', 'Kategori "' . $cat->label . '" berhasil ' . $status . '.');
    }

    /**
     * POST /petty-cash-categories/{id}/delete → hapus permanen.
     * Ditolak untuk kategori sistem / yang sudah dipakai transaksi.
     */
    public function destroy(Request $request, $id)
    {
        $this->guard();

        $cat = PettyCashCategory::findOrFail($id);
        if ($cat->is_system) {
            return redirect()->route('fundsource')
                ->with('error', 'Kategori sistem "' . $cat->label . '" tidak bisa dihapus.');
        }

        if ($cat->transactionCount() > 0) {
            return redirect()->route('fundsource')
                ->with('error', 'Kategori "' . $cat->label . '" sudah dipakai transaksi — tidak bisa dihapus. Nonaktifkan saja.');
        }

        $label = $cat->label;
        $cat->delete();

        return redirect()->route('fundsource')
            ->with('success', 'Kategori "' . $label . '" berhasil dihapus.');
    }

    /* ───────── Helpers ───────── */

    /** Slug underscore unik dari label (mis. "ATK Kantor" → "atk_kantor"). */
    private function makeUniqueKey(string $label): string
    {
        $base = Str::slug($label, '_');
        if ($base === '') {
            $base = 'kategori';
        }
        $base = substr($base, 0, 32);

        $key = $base;
        $i   = 2;
        while (PettyCashCategory::where('key', $key)->exists()) {
            $suffix = '_' . $i;
            $key = substr($base, 0, 32 - strlen($suffix)) . $suffix;
            $i++;
        }
        return $key;
    }

    private function nextSortOrder(): int
    {
        return (int) (PettyCashCategory::max('sort_order') ?? 0) + 10;
    }
}
