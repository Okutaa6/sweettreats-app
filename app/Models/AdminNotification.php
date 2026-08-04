<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $table = 'admin_notification';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'raw_branch_id', 'target_role', 'type', 'title', 'body', 'link', 'is_read', 'created_at',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Insert satu row notifikasi.
     *
     * Routing:
     *   - $userId terisi → notif personal ke 1 user (override branch/role filter)
     *   - $branchId terisi → notif visible ke semua user di cabang itu + global
     *   - $userId & $branchId NULL → broadcast global
     *
     * Untuk notif yang ditujukan ke ROLE (bukan user/branch spesifik),
     * pakai notifyRole() — lebih ekspresif.
     */
    public static function notify(
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
        ?string $branchId = null,
        ?string $userId = null
    ): self {
        return self::create([
            'type'          => $type,
            'title'         => $title,
            'body'          => $body,
            'link'          => $link,
            'raw_branch_id' => $branchId,
            'user_id'       => $userId,
            'created_at'    => now(),
        ]);
    }

    /**
     * Broadcast 1 row ke seluruh user dengan group_name = $role.
     * Tidak fan-out per user — query unreadForUser() filter via target_role.
     *
     * Pakai untuk mis. "ada request top-up baru" → semua Finance lihat.
     */
    public static function notifyRole(
        string $role,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
        ?string $branchId = null
    ): self {
        return self::create([
            'type'          => $type,
            'title'         => $title,
            'body'          => $body,
            'link'          => $link,
            'raw_branch_id' => $branchId,
            'target_role'   => $role,
            'created_at'    => now(),
        ]);
    }

    /**
     * Query notifikasi unread untuk user yang sedang login.
     *
     * Match rule (semuanya OR):
     *   1. user_id == session id (personal)
     *   2. user_id NULL & branch match
     *        - Sales (session branch terisi)   : raw_branch_id NULL OR == session branch
     *        - Admin (canSeeAllBranches=true)  : drop branch filter (lihat semua cabang)
     *        - Other (no branch)               : raw_branch_id NULL only (broadcast global)
     *   3. target_role == session group_name (role broadcast)
     */
    public static function unreadForUser(): \Illuminate\Database\Eloquent\Builder
    {
        $userId   = session('id');
        $branchId = session('raw_branch_id');
        $role     = session('group_name');
        $isAdmin  = function_exists('canSeeAllBranches') ? canSeeAllBranches() : false;

        return self::where('is_read', false)
            ->where(function ($q) use ($userId, $branchId, $role, $isAdmin) {
                // 1. Personal — ditujukan ke user ini langsung
                $q->where('user_id', $userId);

                // 2. Branch / global broadcast (user_id NULL)
                $q->orWhere(function ($q2) use ($branchId, $isAdmin) {
                    $q2->whereNull('user_id');
                    if ($isAdmin) {
                        // Admin lintas-cabang: tidak filter branch sama sekali
                        // — semua notif (per-branch & global) tampil.
                    } elseif ($branchId) {
                        $q2->where(function ($q3) use ($branchId) {
                            $q3->whereNull('raw_branch_id')->orWhere('raw_branch_id', $branchId);
                        });
                    } else {
                        $q2->whereNull('raw_branch_id');
                    }
                    $q2->whereNull('target_role');

                    // Gate per akses fitur: sembunyikan notif branch-broadcast
                    // yang fiturnya tak bisa dibuka user (mis. user Checklist-only
                    // tak lihat notif reservasi/komisi/top-up). Personal (jalur #1)
                    // & role broadcast (jalur #3) tidak di-gate.
                    $blocked = function_exists('notificationBlockedTypesForUser')
                        ? notificationBlockedTypesForUser() : [];
                    if (! empty($blocked)) {
                        $q2->whereNotIn('type', $blocked);
                    }
                });

                // 3. Role broadcast — kalau ada notif yang explicitly target role.
                //    Kalau baris notif diberi raw_branch_id (mis. notif checklist
                //    yang ditarget ke role atasan divisi), scope ke cabang yang
                //    cocok — kecuali admin lintas-cabang. Notif role tanpa branch
                //    (raw_branch_id NULL) tetap global → backward compatible.
                if ($role) {
                    $q->orWhere(function ($q4) use ($role, $branchId, $isAdmin) {
                        $q4->where('target_role', $role);
                        if (! $isAdmin && $branchId) {
                            $q4->where(function ($q5) use ($branchId) {
                                $q5->whereNull('raw_branch_id')
                                   ->orWhere('raw_branch_id', $branchId);
                            });
                        }
                    });
                }
            })
            ->orderByDesc('created_at');
    }
}
