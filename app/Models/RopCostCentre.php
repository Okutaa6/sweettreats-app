<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RopCostCentre
 *
 * Master cost centre pengajuan: 5 cabang (BIN/SUN/KAL/PAM/CIN) + departemen
 * kantor (IT/MKT/FIN/HQ). Kode-nya jadi potongan {CC} di nomor ROP, jadi
 * sekali dipakai JANGAN diubah — nonaktifkan (aktif=0) kalau pensiun.
 *
 * Cabang menyimpan raw_branch_id supaya laporan per cabang bisa join;
 * departemen kantor raw_branch_id NULL.
 */
class RopCostCentre extends Model
{
    protected $table = 'rop_cost_centre';
    protected $primaryKey = 'kode';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode', 'nama', 'tipe', 'raw_branch_id', 'urutan', 'aktif',
    ];

    protected $casts = [
        'aktif'  => 'boolean',
        'urutan' => 'integer',
    ];

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function scopeAktif($q)
    {
        return $q->where('aktif', 1)->orderBy('urutan');
    }

    /**
     * Cost centre milik sebuah cabang — dipakai untuk menebak pilihan default
     * pengaju yang punya raw_branch_id (mis. Supervisor cabang).
     */
    public static function untukBranch(?string $branchId): ?self
    {
        if (! $branchId) return null;
        return self::where('raw_branch_id', $branchId)->where('aktif', 1)->first();
    }
}
