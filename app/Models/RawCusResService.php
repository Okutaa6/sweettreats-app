<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawCusResService extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_cus_res_service';

    protected $fillable = [
        'id',
        'raw_doctor_id',
        'raw_cus_reservation_id',
        'raw_doc_service_id',
        'raw_doc_service_detail_id',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    public function reservation()
    {
        return $this->belongsTo(RawCusReservation::class, 'raw_cus_reservation_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(RawDoctor::class, 'raw_doctor_id', 'id');
    }

    public function docService()
    {
        return $this->belongsTo(RawDocService::class, 'raw_doc_service_id', 'id');
    }

    // relasi ke sub-detail yang dipilih admin
    public function serviceDetail()
    {
        return $this->belongsTo(RawDocServiceDetail::class, 'raw_doc_service_detail_id', 'id');
    }
}