<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kategori pengelompok task di tiap divisi (mis. "Persiapan Ruang & Alat").
 * Soft-deleted supaya snapshot di item lama tetap valid.
 */
class ChecklistCategory extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'sys_checklist_category';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    const DELETED_AT = 'deleted_at';

    protected $fillable = [
        'id', 'division_id', 'raw_branch_id', 'name', 'description', 'cadence',
        'sort_order', 'is_active', 'created_at', 'updated_at', 'deleted_at',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const CADENCE_DAILY  = 'daily';
    const CADENCE_WEEKLY = 'weekly';

    public static function cadenceOptions(): array
    {
        return [
            self::CADENCE_DAILY  => ['label' => 'Harian',   'desc' => 'Task muncul setiap hari operasional'],
            self::CADENCE_WEEKLY => ['label' => 'Mingguan', 'desc' => 'Task hanya muncul pada hari tertentu dalam minggu (Form Mingguan)'],
        ];
    }

    public function isWeekly(): bool
    {
        return $this->cadence === self::CADENCE_WEEKLY;
    }

    public function division()
    {
        return $this->belongsTo(ChecklistDivision::class, 'division_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function tasks()
    {
        return $this->hasMany(ChecklistTask::class, 'category_id', 'id');
    }

    public function activeTasks()
    {
        return $this->tasks()
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->orderBy('sort_order');
    }
}
