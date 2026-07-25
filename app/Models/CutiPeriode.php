<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Periode kontrak cuti — satu baris per kontrak per karyawan.
 * `berjalan` = 1 (aktif) atau NULL (ditutup); UNIQUE (user_id, berjalan)
 * menjamin cuma 1 periode aktif per orang. docs/rencana-jatah-cuti.md.
 */
class CutiPeriode extends Model
{
    protected $table = 'att_cuti_periode';

    protected $fillable = [
        'user_id', 'saldo_awal', 'kontrak_mulai', 'kontrak_akhir', 'berjalan',
    ];

    protected $casts = [
        'saldo_awal'    => 'float',
        'kontrak_mulai' => 'date',
        'kontrak_akhir' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    public function mutasi()
    {
        return $this->hasMany(CutiMutasi::class, 'periode_id', 'id');
    }

    public function scopeBerjalan($q)
    {
        return $q->where('berjalan', 1);
    }

    /** Sisa cuti periode ini = saldo_awal - total pemakaian. Boleh minus. */
    public function sisa(): float
    {
        return (float) $this->saldo_awal - (float) $this->mutasi()->sum('hari');
    }
}
