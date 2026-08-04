<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Unit AC per cabang. Status jatuh tempo dihitung dari servis 'cuci'
 * terakhir + wash_interval_days. Accessor next_due_date / due_status
 * mengandalkan kolom last_wash_date hasil scopeWithServiceSummary() —
 * selalu load list unit lewat scope itu sebelum memakai accessor.
 */
class AcUnit extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'sys_ac_unit';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    const DELETED_AT = 'deleted_at';

    const STATUS_AMAN      = 'aman';
    const STATUS_SEGERA    = 'segera';
    const STATUS_TERLAMBAT = 'terlambat';
    const STATUS_BELUM     = 'belum';   // belum pernah dicuci (belum ada baseline)

    /** Batas "Segera": jatuh tempo dalam N hari ke depan. */
    const DUE_SOON_DAYS = 14;

    protected $fillable = [
        'id', 'raw_branch_id', 'room_name', 'capacity_pk', 'wash_interval_days',
        'notes', 'sort_order', 'is_active', 'created_at', 'updated_at', 'deleted_at',
    ];

    protected $casts = [
        'capacity_pk'        => 'float',
        'wash_interval_days' => 'integer',
        'sort_order'         => 'integer',
        'is_active'          => 'boolean',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
        'deleted_at'         => 'datetime',
    ];

    public static function statusMeta(): array
    {
        return [
            self::STATUS_AMAN => [
                'label' => 'Aman',
                'class' => 'ac-pill--aman',
                'icon'  => 'fa-check-circle',
            ],
            self::STATUS_SEGERA => [
                'label' => 'Segera Dicuci',
                'class' => 'ac-pill--segera',
                'icon'  => 'fa-clock',
            ],
            self::STATUS_TERLAMBAT => [
                'label' => 'Terlambat',
                'class' => 'ac-pill--terlambat',
                'icon'  => 'fa-exclamation-circle',
            ],
            self::STATUS_BELUM => [
                'label' => 'Belum Pernah Dicuci',
                'class' => 'ac-pill--belum',
                'icon'  => 'fa-hourglass-start',
            ],
        ];
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function services()
    {
        return $this->hasMany(AcServiceRecord::class, 'ac_unit_id', 'id')
            ->orderByDesc('service_date')->orderByDesc('created_at');
    }

    /**
     * Tambahkan last_wash_date (tanggal CUCI terakhir — hanya catatan servis
     * yang mencentang "Cuci AC", is_wash=1; visit isi-freon/sparepart saja
     * tidak dihitung) via subquery agar list unit tidak N+1. Prasyarat
     * accessor next_due_date / due_status.
     */
    public function scopeWithServiceSummary($query)
    {
        return $query->addSelect([
            // Hanya servis 'approved' yang me-reset jadwal cuci — record pending
            // belum resmi masuk riwayat sampai supervisor menyetujui.
            'last_wash_date' => DB::table('sys_ac_service_record')
                ->selectRaw('MAX(service_date)')
                ->whereColumn('sys_ac_service_record.ac_unit_id', 'sys_ac_unit.id')
                ->where('is_wash', 1)
                ->where('status', 'approved')
                ->whereNull('deleted_at'),
            // Isi freon terakhir = tanggal servis (approved) terakhir yang punya
            // baris "Isi Freon". Tidak memengaruhi jadwal cuci (info saja).
            'last_freon_date' => DB::table('sys_ac_service_record')
                ->selectRaw('MAX(service_date)')
                ->whereColumn('sys_ac_service_record.ac_unit_id', 'sys_ac_unit.id')
                ->where('sys_ac_service_record.status', 'approved')
                ->whereNull('sys_ac_service_record.deleted_at')
                ->whereExists(function ($q) {
                    $q->selectRaw('1')
                        ->from('sys_ac_service_item')
                        ->whereColumn('sys_ac_service_item.service_record_id', 'sys_ac_service_record.id')
                        ->where('sys_ac_service_item.description', 'Isi Freon');
                }),
            // Jumlah catatan servis yang masih menunggu approval (untuk badge).
            'pending_count' => DB::table('sys_ac_service_record')
                ->selectRaw('COUNT(*)')
                ->whereColumn('sys_ac_service_record.ac_unit_id', 'sys_ac_unit.id')
                ->where('status', 'pending')
                ->whereNull('deleted_at'),
        ]);
    }

    public function getNextDueDateAttribute(): ?Carbon
    {
        if (empty($this->attributes['last_wash_date'])) {
            return null;
        }

        return Carbon::parse($this->attributes['last_wash_date'])
            ->addDays((int) $this->wash_interval_days);
    }

    public function getDueStatusAttribute(): string
    {
        $due = $this->next_due_date;

        if ($due === null) {
            // Belum pernah dicuci — status tersendiri (bukan terlambat), tapi
            // tetap dihitung "butuh aksi" di overdueCountForBranch().
            return self::STATUS_BELUM;
        }

        if ($due->lt(Carbon::today())) {
            return self::STATUS_TERLAMBAT;
        }

        if ($due->lte(Carbon::today()->addDays(self::DUE_SOON_DAYS))) {
            return self::STATUS_SEGERA;
        }

        return self::STATUS_AMAN;
    }

    public function getDueStatusLabelAttribute(): string
    {
        return self::statusMeta()[$this->due_status]['label'] ?? '—';
    }

    /** Jumlah unit yang butuh aksi: terlambat ATAU belum pernah dicuci. */
    public static function overdueCountForBranch(string $branchId): int
    {
        return self::query()
            ->where('raw_branch_id', $branchId)
            ->where('is_active', 1)
            ->withServiceSummary()
            ->get()
            ->filter(fn ($unit) => in_array($unit->due_status, [self::STATUS_TERLAMBAT, self::STATUS_BELUM], true))
            ->count();
    }
}
