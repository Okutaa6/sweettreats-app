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
        'photo',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? asset('uploads/doctors/' . $this->photo) : null;
    }

    /**
     * Scope: hanya dokter aktif.
     * Dipakai di dropdown form reservasi baru & endpoint API publik.
     * Dokter nonaktif tetap valid untuk reservasi yang sudah terjadwal.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

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

    /**
     * Resolve dokter dari input bebas vendor (ID / nama persis / nama LIKE).
     * Dipakai endpoint Open API (mis. Cekat customer_profile.dokter).
     * Return null bila input kosong / tak ada match.
     */
    public static function resolveByIdOrName(?string $dokter): ?self
    {
        $d = trim((string) $dokter);
        if ($d === '') {
            return null;
        }

        $byId = self::find($d);
        if ($byId) {
            return $byId;
        }

        $byName = self::whereRaw('LOWER(name) = ?', [mb_strtolower($d)])->first();
        if ($byName) {
            return $byName;
        }

        return self::where('name', 'like', '%' . $d . '%')->first();
    }
}
