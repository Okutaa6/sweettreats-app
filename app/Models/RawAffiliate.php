<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawAffiliate extends Model
{
    protected $table = 'raw_affiliate';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'phone',
        'bank_account_info',
        'commission_rate',
        'is_active',
        'created_at',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'is_active'       => 'boolean',
        'created_at'      => 'datetime',
    ];

    public function promos()
    {
        return $this->hasMany(RawPromo::class, 'raw_affiliate_id', 'id');
    }

    public function commissions()
    {
        return $this->hasMany(RawCommission::class, 'raw_affiliate_id', 'id');
    }
}