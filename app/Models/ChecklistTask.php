<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Task individual dalam kategori. Saat karyawan submit checklist, judulnya
 * di-snapshot ke ChecklistSubmissionItem.task_title_snapshot — supaya edit
 * master tidak mengubah history.
 */
class ChecklistTask extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'sys_checklist_task';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    const DELETED_AT = 'deleted_at';

    protected $fillable = [
        'id', 'category_id', 'title', 'description', 'day_of_week',
        'requires_photo', 'photo_before_after', 'sort_order', 'is_active', 'created_at', 'updated_at', 'deleted_at',
    ];

    protected $casts = [
        'requires_photo'    => 'boolean',
        'photo_before_after'=> 'boolean',
        'day_of_week'   => 'integer',
        'sort_order'    => 'integer',
        'is_active'     => 'boolean',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    /**
     * Day-of-week (ISO 2=Selasa … 7=Minggu) → label Indonesia.
     * Senin (1) tidak dipakai karena klinik tutup hari Senin.
     */
    public static function dayLabels(): array
    {
        return [
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
    }

    public static function dayShortLabels(): array
    {
        return [2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
    }

    public function dayLabel(): ?string
    {
        return $this->day_of_week ? (self::dayLabels()[$this->day_of_week] ?? null) : null;
    }

    public function category()
    {
        return $this->belongsTo(ChecklistCategory::class, 'category_id', 'id');
    }
}
