<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawBranch extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_branch';

    protected $fillable = [
        'id',
        'name',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    public function doctorSchedules()
    {
        return $this->hasMany(RawDoctorSchedule::class, 'raw_branch_id', 'id');
    }

    public function reservations()
    {
        return $this->hasMany(RawCusReservation::class, 'raw_branch_id', 'id');
    }
}
