<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * IP internet terdaftar per cabang — penanda lokasi absen HP.
 * Satu cabang boleh banyak IP (multi-jalur ISP); satu IP hanya satu cabang
 * (UNIQUE). Diisi via halaman admin IT + tombol "daftarkan IP" dari flag SPV.
 * Lihat docs/rencana-absen-hp.md §5.
 */
class AttBranchIp extends Model
{
    protected $table = 'att_branch_ip';

    protected $fillable = [
        'raw_branch_id', 'ip', 'label', 'status', 'last_seen_at', 'created_by',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    /** Cari cabang dari IP request. NULL = IP tak dikenal (bukan error — flag). */
    public static function matchBranch(string $ip): ?self
    {
        return static::where('ip', $ip)->where('status', 'aktif')->first();
    }
}
