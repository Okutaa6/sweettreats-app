<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawCommission extends Model
{
    protected $table = 'raw_commission';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'raw_affiliate_id',
        'raw_cus_reservation_id',
        'amount',
        'status',
        'created_at',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'created_at' => 'datetime',
    ];

    // Status options
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID     = 'paid';
    const STATUS_REJECTED = 'rejected';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING  => ['label' => 'Menunggu',   'color' => 'warning'],
            self::STATUS_APPROVED => ['label' => 'Disetujui',  'color' => 'primary'],
            self::STATUS_PAID     => ['label' => 'Dibayar',    'color' => 'success'],
            self::STATUS_REJECTED => ['label' => 'Ditolak',    'color' => 'danger'],
        ];
    }

    public function affiliate()
    {
        return $this->belongsTo(RawAffiliate::class, 'raw_affiliate_id', 'id');
    }

    public function reservation()
    {
        return $this->belongsTo(RawCusReservation::class, 'raw_cus_reservation_id', 'id');
    }
}