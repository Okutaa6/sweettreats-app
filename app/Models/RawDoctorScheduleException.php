<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawDoctorScheduleException extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_doctor_schedule_exception';

    protected $fillable = [
        'id',
        'raw_doctor_id',
        'raw_branch_id',
        'date',
        'start_hour',
        'end_hour',
        'type',
        'reason',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    protected $casts = [
        'date'       => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const TYPE_REPLACE = 'replace';
    public const TYPE_ADD     = 'add';

    public function doctor()
    {
        return $this->belongsTo(RawDoctor::class, 'raw_doctor_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }
}
