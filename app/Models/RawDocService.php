<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawDocService extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_doc_service';

    protected $fillable = [
        'id',
        'name',
      
        'category',     
        'sort_order',  
        'duration_minutes',
        'price',
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
        'price'            => 'double',
        'sort_order'       => 'integer', // BARU
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    // new: daftar kategori layanan
    public static function categories(): array
    {
        return [
            'estetika'   => 'Estetika',
            'standar'    => 'Perawatan Standar',
            'pembedahan' => 'Pembedahan',
            'lainnya'    => 'Lainnya',
        ];
    }

    public function reservationServices()
    {
        return $this->hasMany(RawCusResService::class, 'raw_doc_service_id', 'id');
    }

    public function details()
    {
        return $this->hasMany(RawDocServiceDetail::class, 'raw_doc_service_id', 'id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function allDetails()
    {
        return $this->hasMany(RawDocServiceDetail::class, 'raw_doc_service_id', 'id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}