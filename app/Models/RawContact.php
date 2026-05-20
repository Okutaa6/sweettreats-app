<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawContact extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_contact';

    protected $fillable = [
        'source_app',
        'name',
        'phone',
        'channel',
    ];

    public $incrementing = false;
    public $timestamps   = false;
    protected $keyType   = 'string';

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function journeys()
    {
        return $this->hasMany(RawCusJourney::class, 'raw_contact_id', 'id');
    }

    /**
     * Semua pasien (raw_customer) yang menempel ke nomor ini.
     * 1 contact = 1 nomor; banyak pasien boleh share.
     */
    public function patients()
    {
        return $this->hasMany(RawCustomer::class, 'raw_contact_id', 'id');
    }
}