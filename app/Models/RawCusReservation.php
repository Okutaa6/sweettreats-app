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
        'keterangan',
        'follow_up_h_min_1',      // tambah
        'follow_up_h',             // tambah
        'keterangan_kehadiran',    // tambah
        'created_at',
        'updated_at',
        'updated_by',
        'raw_promo_id',             //tambah
        'marketing_promo_id',
        'total_reservasi',          // tambah
        'lainnya_text',
        'promo_klaim',
        'promo_klaim_by',
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

    public function marketingPromo()
    {
        return $this->belongsTo(MarketingPromo::class, 'marketing_promo_id', 'id');
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
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public static function statusOptions()
    {
        return [
            // Palette hangat (design handoff) — samakan dgn token --st-* di
            // reservasi-redesign.css supaya pill = chip kalender = design.
            self::STATUS_PENDING => ['label' => 'Menunggu Konfirmasi', 'icon' => 'fa-hourglass-half', 'color' => '#B5701A', 'bg' => '#FBE7CF'],
            self::STATUS_CONFIRMED => ['label' => 'Dikonfirmasi', 'icon' => 'fa-calendar-check', 'color' => '#2E7D74', 'bg' => '#DFEDE9'],
            self::STATUS_WAITLIST => ['label' => 'Waiting List', 'icon' => 'fa-list-ol', 'color' => '#8A6A52', 'bg' => '#F0E6DA'],
            self::STATUS_COMPLETED => ['label' => 'Selesai', 'icon' => 'fa-check', 'color' => '#2F8E55', 'bg' => '#E3F4E8'],
            self::STATUS_CANCELLED => ['label' => 'Dibatalkan', 'icon' => 'fa-times', 'color' => '#C0392B', 'bg' => '#FBE3DE'],
        ];
    }

    public function getStatusInfoAttribute()
    {
        return self::statusOptions()[$this->status] ?? self::statusOptions()[self::STATUS_PENDING];
    }

    /**
     * Jumlah pasien waiting list yang menunggu dokter+tanggal tertentu.
     * Dipakai saat sebuah reservasi DIBATALKAN → slotnya kosong → sistem
     * menawarkan untuk meng-assign pasien yang sudah antri di dokter &
     * tanggal yang sama.
     */
    public static function waitlistCandidatesFor($doctorId, $date, $branchId = null): int
    {
        if (! $doctorId || ! $date) {
            return 0;
        }

        return static::where('status', self::STATUS_WAITLIST)
            ->where('raw_doctor_id', $doctorId)
            ->whereDate('date', $date)
            ->when($branchId, fn ($q) => $q->where('raw_branch_id', $branchId))
            ->count();
    }
}
