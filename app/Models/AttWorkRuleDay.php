<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jam baku per hari-dalam-minggu. day: 0=Minggu .. 6=Sabtu (Carbon dayOfWeek).
 * Skema: database/sql/2026_07_18_absensi_work_rule.sql.
 */
class AttWorkRuleDay extends Model
{
    protected $table = 'att_work_rule_day';

    public $timestamps = false;

    protected $fillable = [
        'rule_id', 'day', 'libur',
        'jam_masuk_awal', 'jam_masuk_akhir', 'jam_pulang', 'jam_lembur_mulai',
    ];
}
