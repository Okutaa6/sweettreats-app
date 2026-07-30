<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catatan servis AC = satu kunjungan servis (kejadian cuci) pada satu
 * tanggal, diinput GS cabang. Rincian biaya per baris ada di relasi
 * items() (AcServiceItem). Soft delete dipakai untuk koreksi salah input;
 * jejak audit di ChecklistAuditLog dengan entity_type 'ac_service'.
 */
class AcServiceRecord extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'sys_ac_service_record';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    const DELETED_AT = 'deleted_at';

    // Alur approval: GS catat → pending; supervisor approve/reject.
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'id', 'ac_unit_id', 'raw_branch_id', 'service_date', 'is_wash', 'status',
        'created_by_user_id', 'reviewed_by_user_id', 'reviewed_at', 'review_note',
        'created_at', 'updated_at', 'deleted_at',
    ];

    protected $casts = [
        'service_date' => 'date',
        'is_wash'      => 'boolean',
        'reviewed_at'  => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'deleted_at'   => 'datetime',
    ];

    /** Label + warna pill per status approval. */
    public static function statusMeta(): array
    {
        return [
            self::STATUS_PENDING  => ['label' => 'Menunggu Approval', 'color' => '#A66019', 'bg' => '#FAEEDA'],
            self::STATUS_APPROVED => ['label' => 'Disetujui',         'color' => '#1F6E5F', 'bg' => '#E1F5EE'],
            self::STATUS_REJECTED => ['label' => 'Ditolak',           'color' => '#A32D2D', 'bg' => '#FCEBEB'],
        ];
    }

    public function unit()
    {
        return $this->belongsTo(AcUnit::class, 'ac_unit_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(AcServiceItem::class, 'service_record_id', 'id')
            ->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(Users::class, 'created_by_user_id', 'id');
    }

    public function reviewer()
    {
        return $this->belongsTo(Users::class, 'reviewed_by_user_id', 'id');
    }

    /** Total biaya kunjungan = jumlah semua baris rincian. Butuh items ter-load. */
    public function getTotalAmountAttribute(): int
    {
        return (int) $this->items->sum('amount');
    }
}
