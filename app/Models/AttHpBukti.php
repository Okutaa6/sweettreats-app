<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bukti tiap absen HP: foto selfie + semua sinyal saat kejadian (IP, cabang
 * cocok/manual, perangkat, jam server). att_scan tetap sumber kebenaran buat
 * engine; tabel ini buat mata manusia (strip foto SPV, panel flag) + audit.
 * Lihat docs/rencana/rencana-absen-hp.md §5.
 */
class AttHpBukti extends Model
{
    protected $table = 'att_hp_bukti';

    protected $fillable = [
        'user_id', 'att_scan_id', 'jenis', 'photo_path', 'ip',
        'branch_id_cocok', 'branch_id_manual', 'device_id',
        'device_label', 'is_desktop', 'taken_at',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    public function scan()
    {
        return $this->belongsTo(AttScan::class, 'att_scan_id', 'id');
    }

    public function branchCocok()
    {
        return $this->belongsTo(RawBranch::class, 'branch_id_cocok', 'id');
    }

    public function branchManual()
    {
        return $this->belongsTo(RawBranch::class, 'branch_id_manual', 'id');
    }

    /** Cabang efektif buat tampilan: hasil cocok IP, atau pilihan manual. */
    public function getBranchEfektifIdAttribute(): ?string
    {
        return $this->branch_id_cocok ?: $this->branch_id_manual;
    }
}
