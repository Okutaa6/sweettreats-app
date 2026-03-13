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
    'name',
    'phone',
    'email',           // tambah
    'jenis_kelamin',   // tambah
    'tanggal_lahir',   // tambah
    'nik',             // tambah
    'tempat_lahir',    // tambah
    'alamat',          // tambah
    'tanggal_daftar',  // tambah
];
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    public function journeys()
    {
        return $this->hasMany(RawCusJourney::class, 'raw_customer_id', 'id');
    }

    public function reservations()
    {
        return $this->hasMany(RawCusReservation::class, 'raw_customer_id', 'id');
    }
}
