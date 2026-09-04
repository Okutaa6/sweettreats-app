<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu entri Katalog Modul — "apa saja yang kami punya".
 *
 * ⛔ Tak ada kolom jumlah fitur / jumlah tiket di sini: fitur diturunkan dari
 * `access_path` (lihat KatalogModul::fitur) dan angka tiket dihitung dari
 * it_ticket. Angka tersimpan basi diam-diam.
 */
class ItModul extends Model
{
    protected $table = 'it_modul';

    protected $fillable = [
        'nama', 'departemen', 'jenis', 'status', 'sejak', 'sejak_terbukti',
        'access_id', 'catatan', 'urutan',
        // pemakaian: PEMETAAN diisi seeder; ANGKA-nya ditulis it:hitung-pemakaian
        'pakai_tabel', 'pakai_kolom', 'pakai_7hari', 'pakai_at',
    ];

    protected $casts = [
        'sejak'          => 'date',
        'sejak_terbukti' => 'bool',
        'urutan'         => 'int',
        'pakai_7hari'    => 'int',
        'pakai_at'       => 'datetime',
    ];

    public function tiket()
    {
        return $this->hasMany(ItTicket::class, 'modul_id');
    }

    /** Node menu akar modul ini — NULL berarti modul tanpa menu. */
    public function akses()
    {
        return $this->belongsTo(AccessPath::class, 'access_id');
    }
}
