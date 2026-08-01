<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ledger cuti — satu baris per potongan/koreksi. Saldo tidak pernah disimpan
 * sebagai angka tunggal; selalu dihitung dari sini (jejak audit alami).
 */
class CutiMutasi extends Model
{
    protected $table = 'att_cuti_mutasi';
    public $timestamps = false; // hanya created_at, diisi DB

    protected $fillable = [
        'periode_id', 'user_id', 'tanggal', 'hari', 'jenis',
        'sumber', 'ref_id', 'keterangan', 'dibuat_oleh',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'hari'    => 'float',
    ];

    public function periode()
    {
        return $this->belongsTo(CutiPeriode::class, 'periode_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }
}
