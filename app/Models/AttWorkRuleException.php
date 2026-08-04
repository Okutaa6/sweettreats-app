<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Override per tanggal — libur nasional (Lebaran), tanggal khusus.
 * raw_branch_id NULL = semua cabang. Menang atas att_work_rule_day di
 * AttendanceReconciler::resolveDay(). Skema: 2026_07_18_absensi_work_rule.sql;
 * pola seed: 2026_07_21_absensi_exception_lebaran.sql.
 */
class AttWorkRuleException extends Model
{
    protected $table = 'att_work_rule_exception';

    protected $fillable = [
        'raw_branch_id', 'tgl_from', 'tgl_to', 'libur',
        'jam_masuk', 'jam_pulang', 'alasan', 'dibuat_oleh',
    ];

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }
}
