<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Posisi/jabatan karyawan saat ini (atau histori). 1 user bisa punya
 * banyak row historis tapi hanya 1 yg aktif (is_primary=1 dan tanggal
 * masih berlaku).
 */
class UserPosition extends Model
{
    use HasUuids;

    protected $table = 'sys_user_position';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'division_id', 'raw_branch_id', 'is_primary',
        'effective_from', 'effective_to', 'confirmed_at', 'confirmed_by',
        'created_at', 'updated_at',
    ];

    protected $casts = [
        'is_primary'     => 'boolean',
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'confirmed_at'   => 'datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    public function division()
    {
        return $this->belongsTo(ChecklistDivision::class, 'division_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    /**
     * Scope: posisi AKTIF per tanggal tertentu (default hari ini). Aktif =
     * sudah dikonfirmasi supervisor (confirmed_at terisi), is_primary, dan
     * tanggal masih berlaku. Usulan pending (confirmed_at NULL) TIDAK aktif.
     */
    public function scopeActiveOn($q, ?Carbon $date = null)
    {
        $d = ($date ?: Carbon::today())->toDateString();
        return $q->where('is_primary', 1)
            ->whereNotNull('confirmed_at')
            ->where(function ($q2) use ($d) {
                $q2->whereNull('effective_to')->orWhere('effective_to', '>=', $d);
            })
            ->where(function ($q2) use ($d) {
                $q2->whereNull('effective_from')->orWhere('effective_from', '<=', $d);
            });
    }

    /**
     * Scope: usulan posisi yang menunggu konfirmasi supervisor
     * (confirmed_at NULL, masih open-ended, is_primary).
     */
    public function scopePending($q)
    {
        return $q->where('is_primary', 1)
            ->whereNull('confirmed_at')
            ->whereNull('effective_to');
    }
}
