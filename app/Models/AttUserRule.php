<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Penugasan aturan per ORANG, history-aware (effective_from/to = jejak gaji).
 * Ganti aturan = TUTUP baris lama + baris BARU — JANGAN pernah update rule_id
 * baris lama (docs/rencana/rencana-aturan-kerja.md Tab 2).
 * Skema: database/sql/2026_07_18_absensi_work_rule.sql.
 */
class AttUserRule extends Model
{
    protected $table = 'att_user_rule';

    protected $fillable = ['user_id', 'rule_id', 'effective_from', 'effective_to'];

    public function rule()
    {
        return $this->belongsTo(AttWorkRule::class, 'rule_id');
    }

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }
}
