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

    /**
     * Resolve sub-layanan dari input bebas vendor (kode code_service / nama
     * persis / nama LIKE) — opsional dibatasi ke service induk tertentu.
     * Dipakai endpoint Open API (mis. Cekat jadwal_dokter.sub_service).
     * Hanya cari yang is_active=1. Return null bila tak ditemukan.
     */
    public static function resolveByTreatment(?string $treatment, ?string $parentServiceId = null): ?self
    {
        $t = trim((string) $treatment);
        if ($t === '') {
            return null;
        }

        $base = self::query()->where('is_active', 1);
        if ($parentServiceId) {
            $base->where('raw_doc_service_id', $parentServiceId);
        }

        $byCode = (clone $base)->whereRaw('UPPER(code_service) = ?', [strtoupper($t)])->first();
        if ($byCode) {
            return $byCode;
        }

        $byName = (clone $base)->whereRaw('LOWER(name) = ?', [mb_strtolower($t)])->first();
        if ($byName) {
            return $byName;
        }

        return $base->where('name', 'like', '%' . $t . '%')->first();
    }
}