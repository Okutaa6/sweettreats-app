<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jenis aturan kerja — baku (dinilai telat/alpa dari scan) / fleksibel (scan
 * cuma penanda, tak dinilai). Dibaca AttendanceReconciler saat compute.
 * Skema: database/sql/2026_07_18_absensi_work_rule.sql.
 * UI kelola: docs/rencana/rencana-aturan-kerja.md Tab 1.
 */
class AttWorkRule extends Model
{
    protected $table = 'att_work_rule';

    protected $fillable = ['nama', 'tipe', 'keterangan'];

    public function days()
    {
        return $this->hasMany(AttWorkRuleDay::class, 'rule_id')->orderBy('day');
    }

    /** Penugasan yang efektif HARI INI — dipakai hitung "berlaku untuk N karyawan". */
    public function penugasanAktif()
    {
        $today = now()->toDateString();

        return $this->hasMany(AttUserRule::class, 'rule_id')
            ->where('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today));
    }
}
