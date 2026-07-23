<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Hasil rekonsiliasi harian — 1 baris per (user, hari kerja). TURUNAN, bisa
 * dihitung ulang dari att_scan + aturan. Diisi App\Services\AttendanceReconciler.
 * Lihat docs/rencana-rekonsiliasi-absensi.md §5.2.
 */
class AttDay extends Model
{
    protected $table = 'att_day';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'raw_branch_id', 'work_date', 'rule_id',
        'scan_first', 'scan_last', 'scan_count', 'single_scan', 'cabang_terlibat',
        'status', 'telat_menit', 'wajib_pulang', 'lembur_menit_disetujui', 'computed_at',
    ];

    protected $casts = [
        'work_date' => 'date',
        'scan_first' => 'datetime',
        'scan_last' => 'datetime',
        'cabang_terlibat' => 'array',
        'single_scan' => 'boolean',
        'computed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }
}
