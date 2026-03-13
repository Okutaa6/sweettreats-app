<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawPromo extends Model
{
    protected $table = 'raw_promo';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'raw_affiliate_id',
        'promo_code',
        'discount_type',
        'discount_value',
        'valid_until',
        'is_active',
        'created_at',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'is_active'      => 'boolean',
        'valid_until'    => 'date',
        'created_at'     => 'datetime',
    ];

    public function affiliate()
    {
        return $this->belongsTo(RawAffiliate::class, 'raw_affiliate_id', 'id');
    }

    public function reservations()
    {
        return $this->hasMany(RawCusReservation::class, 'raw_promo_id', 'id');
    }

    // Cek apakah promo masih valid
    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->valid_until && $this->valid_until->isPast()) return false;
        return true;
    }

    // Hitung nilai diskon berdasarkan total
    public function calculateDiscount(float $total): float
    {
        if ($this->discount_type === 'percent') {
            return round($total * ($this->discount_value / 100), 2);
        }
        return min((float) $this->discount_value, $total);
    }
}