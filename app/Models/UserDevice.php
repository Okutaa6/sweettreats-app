<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Perangkat "Ingat perangkat ini" (remember me). Nyimpen HASH sha256 token —
 * token asli cuma hidup di cookie (90 hari), DIPUTAR tiap dipakai.
 * Cabut: logout manual / ganti password / akun nonaktif → revoked_at terisi.
 * Lihat docs/rencana/rencana-absen-hp.md §5.
 */
class UserDevice extends Model
{
    protected $table = 'user_device';

    /** Umur cookie perangkat (hari). */
    public const COOKIE_DAYS = 90;
    public const COOKIE_NAME = 'st_device';

    protected $fillable = [
        'user_id', 'token_hash', 'label', 'last_used_at', 'revoked_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    /** Cari perangkat hidup dari token mentah (cookie). NULL = tak dikenal/dicabut. */
    public static function findLive(?string $rawToken): ?self
    {
        if (!$rawToken) return null;

        return static::where('token_hash', hash('sha256', $rawToken))
            ->whereNull('revoked_at')
            ->first();
    }
}
