<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mapping (cabang, No.ID mesin) -> user DataCenter.
 * Kunci unik (raw_branch_id, device_user_id) — 26 No.ID bentrok antar cabang.
 * Lihat docs/rencana/rencana-rekonsiliasi-absensi.md §5.2.
 */
class AttDeviceUser extends Model
{
    protected $table = 'att_device_user';

    protected $fillable = [
        'raw_branch_id', 'device_user_id', 'user_id', 'nama_di_mesin', 'status', 'catatan',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }
}
