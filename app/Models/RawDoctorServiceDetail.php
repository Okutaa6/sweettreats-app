<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawDoctorServiceDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_doctor_service_detail';

    protected $fillable = [
        'id',
        'raw_doctor_id',
        'raw_doc_service_detail_id',
        'duration_minutes',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    protected $casts = [
        'duration_minutes' => 'double',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public function doctor()
    {
        return $this->belongsTo(RawDoctor::class, 'raw_doctor_id', 'id');
    }

    public function serviceDetail()
    {
        return $this->belongsTo(RawDocServiceDetail::class, 'raw_doc_service_detail_id', 'id');
    }
}
