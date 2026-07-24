<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengecualian per-hari pada 1 weekly submission — "hari ini tidak perlu diisi".
 * 1 row per (submission, day_of_week). Menggantikan skip level-minggu lama.
 */
class ChecklistWeeklyDayExcuse extends Model
{
    use HasUuids;

    protected $table = 'sys_checklist_weekly_day_excuse';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'submission_id', 'day_of_week', 'reason',
        'created_at', 'updated_at',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(ChecklistWeeklySubmission::class, 'submission_id', 'id');
    }
}
