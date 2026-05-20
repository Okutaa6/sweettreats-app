<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawCustomer extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_customer';

    protected $fillable = [
        'source_app',
        'channel',
        'raw_contact_id',
        'relasi',
        'name',
        'phone',
        'email',
        'jenis_kelamin',
        'tanggal_lahir',
        'nik',
        'tempat_lahir',
        'alamat',
        'tanggal_daftar',
        'raw_branch_id',
        'raw_doc_service_id',
        'raw_doctor_id',
        'pasien_baru_lama',
        'raw_promo_id',
        'catatan',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    public function contact()
    {
        return $this->belongsTo(RawContact::class, 'raw_contact_id', 'id');
    }

    public function journeys()
    {
        return $this->hasMany(RawCusJourney::class, 'raw_customer_id', 'id');
    }

    public function reservations()
    {
        return $this->hasMany(RawCusReservation::class, 'raw_customer_id', 'id');
    }

    public function latestReservation()
    {
        return $this->hasOne(RawCusReservation::class, 'raw_customer_id', 'id')->latestOfMany('date');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function docService()
    {
        return $this->belongsTo(RawDocService::class, 'raw_doc_service_id', 'id');
    }

    public function promo()
    {
        return $this->belongsTo(RawPromo::class, 'raw_promo_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(RawDoctor::class, 'raw_doctor_id', 'id');
    }
}
