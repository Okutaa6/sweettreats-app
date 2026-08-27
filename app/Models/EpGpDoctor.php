<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Daftar keanggotaan dokter Branding GP — dokter (dari master raw_doctor) yang
 * ikut konten video Branding GP. Jadi sumber isi dropdown "Dokter GP" di form
 * EP Konten & sub-filter kalender.
 *
 * Catatan: ep_konten.gp_doctor_id menyimpan raw_doctor.id (bukan id membership),
 * jadi menghapus baris di sini TIDAK merusak konten lama.
 */
class EpGpDoctor extends Model
{
    use HasUuids;

    protected $table = 'ep_gp_doctor';

    protected $fillable = [
        'id',
        'raw_doctor_id',
        'social_media',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'social_media' => 'array',
        'is_active'    => 'boolean',
        'sort_order'   => 'integer',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    const UPDATED_AT = 'updated_at';
    const CREATED_AT = 'created_at';

    public function doctor()
    {
        return $this->belongsTo(RawDoctor::class, 'raw_doctor_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
