<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ChecklistWeeklySubmissionItem extends Model
{
    use HasUuids;

    protected $table = 'sys_checklist_weekly_submission_item';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'submission_id', 'task_id',
        'category_title_snapshot', 'task_title_snapshot',
        'category_sort_order', 'task_sort_order',
        'day_of_week', 'is_done', 'note', 'requires_photo', 'photo_before_after', 'photo_path',
        'photo_before_path', 'photo_after_path', 'done_at', 'created_at',
        'is_excepted', 'exception_reason', 'excepted_at',
    ];

    protected $casts = [
        'is_done'             => 'boolean',
        'is_excepted'         => 'boolean',
        'requires_photo'      => 'boolean',
        'photo_before_after'  => 'boolean',
        'day_of_week'         => 'integer',
        'category_sort_order' => 'integer',
        'task_sort_order'     => 'integer',
        'done_at'             => 'datetime',
        'excepted_at'         => 'datetime',
        'created_at'          => 'datetime',
    ];

    /** Item "beres": sudah dicentang ATAU dikecualikan (tidak berlaku minggu ini). */
    public function isSatisfied(): bool
    {
        return (bool) ($this->is_done || $this->is_excepted);
    }

    public function submission()
    {
        return $this->belongsTo(ChecklistWeeklySubmission::class, 'submission_id', 'id');
    }

    public function task()
    {
        return $this->belongsTo(ChecklistTask::class, 'task_id', 'id');
    }
}
