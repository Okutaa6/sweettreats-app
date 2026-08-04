<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Vendor
 *
 * Master penerima pembayaran. Diseed dari Accurate lewat
 * `php artisan rop:tarik-vendor`; boleh juga ditambah manual.
 *
 * Daftar ini BANTUAN, bukan gerbang: pengaju tetap boleh mengetik nama
 * penerima bebas (rop_requests.pay_to_text) kalau vendornya belum terdaftar.
 */
class Vendor extends Model
{
    protected $table = 'vendors';

    public const SUMBER_ACCURATE = 'accurate';
    public const SUMBER_MANUAL   = 'manual';

    protected $fillable = [
        'nama', 'accurate_id', 'accurate_no', 'slug_sumber',
        'bank_nama', 'bank_rekening', 'bank_atas_nama',
        'sumber', 'aktif', 'last_synced_at',
    ];

    protected $casts = [
        'aktif'          => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function scopeAktif($q)
    {
        return $q->where('aktif', 1)->orderBy('nama');
    }

    /**
     * Pencarian untuk combobox di form pengajuan.
     */
    public function scopeCari($q, ?string $kata)
    {
        if (! $kata) return $q;
        return $q->where('nama', 'like', '%' . $kata . '%');
    }
}
