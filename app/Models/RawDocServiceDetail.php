<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawDocServiceDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_doc_service_detail';

    protected $fillable = [
        'id',
        'raw_doc_service_id',
        'name',
        'duration_minutes',
        'price',
        'sort_order',
        'is_active',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    public $incrementing = false;
    public $timestamps   = false;
    protected $keyType   = 'string';

    protected $casts = [
        'duration_minutes' => 'double',
        'price'            => 'double',
        'is_active'        => 'boolean',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    // relasi ke parent 
    public function docService()
    {
        return $this->belongsTo(RawDocService::class, 'raw_doc_service_id', 'id');
    }
}