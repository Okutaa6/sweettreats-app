<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Periode penggajian absensi. Finance TUTUP periode → att_day di rentang itu BEKU
 * (reconcile tak menimpanya lagi, §5.3/D2). Kontrol per-periode, bukan per-item (§5.4).
 * Lihat docs/rencana-rekonsiliasi-absensi.md §5.2, §7 Fase D.
 */
class AttPeriod extends Model
{
    protected $table = 'att_period';

    protected $fillable = [
        'raw_branch_id', 'nama', 'periode_from', 'periode_to',
        'status', 'closed_by', 'closed_at', 'catatan', 'created_by',
    ];

    protected $casts = [
        'periode_from' => 'date',
        'periode_to'   => 'date',
        'closed_at'    => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function closer()
    {
        return $this->belongsTo(Users::class, 'closed_by', 'id');
    }

    public function scopeClosed($q)
    {
        return $q->where('status', 'closed');
    }

    public function scopeOpen($q)
    {
        return $q->where('status', 'open');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
