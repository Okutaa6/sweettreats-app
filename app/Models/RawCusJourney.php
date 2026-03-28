<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawCusJourney extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_cus_journey';

    protected $fillable = [
        'id',
        'raw_customer_id',
        'raw_contact_id',      //BARU
        'journey_label',
        'created_at',
    ];

    public $incrementing = false;
    public $timestamps   = false;
    protected $keyType   = 'string';

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(RawCustomer::class, 'raw_customer_id', 'id');
    }

    //BARU
    public function contact()
    {
        return $this->belongsTo(RawContact::class, 'raw_contact_id', 'id');
    }
}