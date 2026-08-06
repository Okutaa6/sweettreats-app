<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Submit per-hari pada 1 weekly submission — "tugas hari ini sudah dikirim".
 * 1 row per (submission, day_of_week). Hari yang tercatat di sini terkunci
 * (kecuali review_status=rejected → dibuka lagi untuk diperbaiki).
 *
 * review_status: pending (default) | approved | rejected — status review
 * supervisor per hari. Header mingguan diturunkan dari kumpulan status ini.
 */
class ChecklistWeeklyDaySubmit extends Model
{
    use HasUuids;

    protected $table = 'sys_checklist_weekly_day_submit';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    const REVIEW_PENDING  = 'pending';
    const REVIEW_APPROVED = 'approved';
    const REVIEW_REJECTED = 'rejected';

    protected $fillable = [
        'id', 'submission_id', 'day_of_week', 'note',
        'review_status', 'reviewer_user_id', 'review_notes', 'reviewed_at',
        'submitted_at', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'day_of_week'  => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(ChecklistWeeklySubmission::class, 'submission_id', 'id');
    }

    public function reviewer()
    {
        return $this->belongsTo(Users::class, 'reviewer_user_id', 'id');
    }
}
