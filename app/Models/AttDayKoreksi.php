<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Koreksi status harian oleh admin — buku besar, bukan lembar coret.
 *
 * Baris di sini adalah DEKLARASI MANUSIA atas satu (orang, tanggal).
 * `AttendanceReconciler` membacanya sebagai kata terakhir setiap kali
 * menghitung, sebab `att_day` itu turunan dan akan menimpa dirinya sendiri
 * pada reconcile berikutnya — lihat komentar di
 * database/sql/2026_09_05_att_day_koreksi.sql.
 *
 * ⚠️ Jangan pernah UPDATE atau DELETE baris di sini. Membatalkan koreksi =
 * mengisi `dicabut_at` (lihat KoreksiAbsen::cabut). Jejaknya menentukan uang.
 */
class AttDayKoreksi extends Model
{
    protected $table = 'att_day_koreksi';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'work_date', 'status', 'status_lama', 'alasan',
        'dibuat_oleh', 'dibuat_nama', 'created_at',
        'dicabut_at', 'dicabut_oleh', 'dicabut_nama', 'dicabut_alasan',
    ];

    protected $casts = [
        'work_date'  => 'date',
        'created_at' => 'datetime',
        'dicabut_at' => 'datetime',
    ];

    /** Koreksi yang masih berlaku (belum dicabut). */
    public function scopeAktif($q)
    {
        return $q->whereNull('dicabut_at');
    }

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }
}
