<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawDoctorSchedule extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_doctor_schedule';

    protected $fillable = [
        'id',
        'raw_doctor_id',
        'raw_branch_id',
        'day',
        'start_hour',
        'end_hour',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function doctor()
    {
        return $this->belongsTo(RawDoctor::class, 'raw_doctor_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }
}
