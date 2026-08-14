<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PettyCashCategory
 *
 * Master kategori pengeluaran petty cash. Sebelumnya hardcoded di
 * PettyCashTransaction::CATEGORIES; sekarang dikelola Super Admin dari
 * halaman "Kelola Wallet" (/fund-sources).
 *
 *   - key       : kode teknis (huruf kecil/underscore) yang disimpan di
 *                 petty_cash_transactions.category. Immutable.
 *   - label     : nama tampilan (boleh diubah).
 *   - is_system : 1 = terkunci (tak boleh hapus/nonaktif), mis. 'pinjaman'.
 *   - is_active : 0 = disembunyikan dari pilihan input baru; data lama aman.
 */
class PettyCashCategory extends Model
{
    protected $table = 'petty_cash_categories';

    protected $fillable = [
        'key',
        'label',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_system'  => 'boolean',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', 1);
    }

    /** Jumlah transaksi (termasuk yang sudah dihapus) yang memakai kategori ini. */
    public function transactionCount(): int
    {
        return PettyCashTransaction::where('category', $this->key)->count();
    }
}
