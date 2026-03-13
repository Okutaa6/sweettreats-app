<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawDoctorService extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_doctor_service';

    protected $fillable = [
        'id',
        'raw_doctor_id',
        'raw_doc_service_id',
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

    /**
     * Relasi ke dokter
     */
    public function doctor()
    {
        return $this->belongsTo(RawDoctor::class, 'raw_doctor_id', 'id');
    }

    /**
     * Relasi ke service/perawatan
     */
    public function service()
    {
        return $this->belongsTo(RawDocService::class, 'raw_doc_service_id', 'id');
    }
}
