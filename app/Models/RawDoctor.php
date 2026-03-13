<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawDoctor extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_doctor';

    protected $fillable = [
        'id',
        'name',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    /**
     * Jadwal dokter (per cabang & hari)
     */
    public function schedules()
    {
        return $this->hasMany(RawDoctorSchedule::class, 'raw_doctor_id', 'id');
    }

    /**
     * Reservasi yang ditangani dokter ini
     */
    public function reservations()
    {
        return $this->hasMany(RawCusReservation::class, 'raw_doctor_id', 'id');
    }

    /**
     * Service/perawatan yang dikerjakan di reservasi (historical)
     */
    public function reservationServices()
    {
        return $this->hasMany(RawCusResService::class, 'raw_doctor_id', 'id');
    }

    /**
     * Service/perawatan yang BISA dilakukan dokter ini (master data)
     */
    public function doctorServices()
    {
        return $this->hasMany(RawDoctorService::class, 'raw_doctor_id', 'id');
    }

    /**
     * get list RawDocService yang bisa dilakukan dokter ini
     * via raw_doctor_service pivot
     */
    public function capableServices()
    {
        return $this->belongsToMany(
            RawDocService::class,
            'raw_doctor_service',
            'raw_doctor_id',
            'raw_doc_service_id'
        );
    }
}
