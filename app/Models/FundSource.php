<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * FundSource
 *
 * Master sumber dana / wallet untuk Petty Cash & Biaya Transport.
 *   category = cash | bank | ewallet (pengelompokan dropdown)
 *   code     = unik, dipakai sebagai key internal
 *   name     = label tampilan
 */
class FundSource extends Model
{
    protected $table = 'fund_sources';

    public const CAT_CASH    = 'cash';
    public const CAT_BANK    = 'bank';
    public const CAT_EWALLET = 'ewallet';

    public const UNCATEGORIZED_KEY   = '__uncategorized__';
    public const UNCATEGORIZED_LABEL = 'Belum dikategorikan';

    protected $fillable = [
        'category',
        'code',
        'name',
        'brand_color',
        'short_label',
        'logo_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', 1);
    }

    public static function categoryLabel(?string $category): string
    {
        return [
            self::CAT_CASH    => 'Cash',
            self::CAT_BANK    => 'Bank',
            self::CAT_EWALLET => 'E-Wallet',
        ][$category] ?? '-';
    }

    /**
     * Warna fallback per kategori — dipakai kalau brand_color belum diisi.
     */
    public static function defaultColor(?string $category): string
    {
        return [
            self::CAT_CASH    => '#16a34a',
            self::CAT_BANK    => '#2563eb',
            self::CAT_EWALLET => '#9333ea',
        ][$category] ?? '#64748b';
    }

    /**
     * Label pendek fallback — 2-3 huruf pertama dari name.
     */
    public function effectiveShortLabel(): string
    {
        if (! empty($this->short_label)) {
            return $this->short_label;
        }
        $clean = preg_replace('/[^A-Za-z0-9]/', '', (string) $this->name);
        return strtoupper(mb_substr($clean, 0, 3));
    }

    public function effectiveBrandColor(): string
    {
        return ! empty($this->brand_color)
            ? $this->brand_color
            : self::defaultColor($this->category);
    }

    /**
     * Ambil semua source aktif, dikelompokkan per category untuk dropdown.
     * Format: ['cash' => Collection, 'bank' => Collection, 'ewallet' => Collection]
     */
    public static function groupedForDropdown()
    {
        return static::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('category');
    }
}
