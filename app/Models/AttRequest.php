<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Form pengajuan absen: cuti | izin | libur | lembur | wfh.
 * Deklarasi manusia — kebenaran tertinggi di atas scan (§4).
 * Lihat docs/rencana/rencana-rekonsiliasi-absensi.md §4, §5.2.
 */
class AttRequest extends Model
{
    protected $table = 'att_request';

    protected $fillable = [
        'user_id', 'raw_branch_id', 'tipe', 'sub_tipe',
        'tanggal_from', 'tanggal_to', 'jam_from', 'jam_to',
        'alasan', 'foto', 'claimed_menit', 'scan_evidence', 'verifikasi', 'status',
    ];

    protected $casts = [
        'tanggal_from' => 'date',
        'tanggal_to' => 'date',
        'scan_evidence' => 'array',
        'claimed_menit' => 'integer',
    ];

    public const ABSENCE_TYPES = ['cuti', 'izin', 'sakit', 'libur', 'wfh'];

    /**
     * Sakit ≥ sekian hari berturut → surat dokter WAJIB (keputusan Ekky
     * 2026-08-11). 1–2 hari cukup kabar ke atasan: menuntut surat untuk demam
     * semalam justru mendorong orang masuk kerja sambil sakit.
     */
    public const SAKIT_HARI_WAJIB_SURAT = 3;

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function approvals()
    {
        return $this->hasMany(AttRequestApproval::class, 'request_id', 'id');
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }
}
