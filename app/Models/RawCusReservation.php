<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawCusReservation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_cus_reservation';

    protected $fillable = [
        'id',
        'raw_branch_id',
        'raw_customer_id',
        'raw_doctor_id',
        'date',
        'start_hour',
        'end_hour',
        'status',
        'is_waiting',
        'follow_up_h_min_1',      // tambah
        'follow_up_h',             // tambah
        'follow_up_h_min_1_jam',   // tambah
        'keterangan_kehadiran',    // tambah
        'created_at',
        'updated_at',
        'updated_by',
        'raw_promo_id',             //tambah
        'total_reservasi',          // tambah
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    protected $casts = [
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(RawCustomer::class, 'raw_customer_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(RawDoctor::class, 'raw_doctor_id', 'id');
    }

    public function services()
    {
        return $this->hasMany(RawCusResService::class, 'raw_cus_reservation_id', 'id');
    }

    //new
    public function promo()
    {
        return $this->belongsTo(RawPromo::class, 'raw_promo_id', 'id');
    }
    //new   
    public function commission()
    {
        return $this->hasOne(RawCommission::class, 'raw_cus_reservation_id', 'id');
    }

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_WAITLIST = 'waitlist';
    const STATUS_ONGOING = 'ongoing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public static function statusOptions()
    {
        return [
            self::STATUS_PENDING => ['label' => 'Menunggu Konfirmasi', 'icon' => 'fa-hourglass-half', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
            self::STATUS_CONFIRMED => ['label' => 'Dikonfirmasi', 'icon' => 'fa-calendar-check', 'color' => '#1565c0', 'bg' => '#e3f2fd'],
            self::STATUS_WAITLIST => ['label' => 'Waiting List', 'icon' => 'fa-list-ol', 'color' => '#7c3aed', 'bg' => '#ede9fe'],
            self::STATUS_ONGOING => ['label' => 'Berlangsung', 'icon' => 'fa-spinner', 'color' => '#2e7d32', 'bg' => '#e8f5e9'],
            self::STATUS_COMPLETED => ['label' => 'Selesai', 'icon' => 'fa-check', 'color' => '#8b7a6b', 'bg' => '#f5f0eb'],
            self::STATUS_CANCELLED => ['label' => 'Dibatalkan', 'icon' => 'fa-times', 'color' => '#dc2626', 'bg' => '#fef2f2'],
        ];
    }

    public function getStatusInfoAttribute()
    {
        return self::statusOptions()[$this->status] ?? self::statusOptions()[self::STATUS_PENDING];
    }
}
