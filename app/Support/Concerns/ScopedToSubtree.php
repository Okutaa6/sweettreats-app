<?php

namespace App\Support\Concerns;

use App\Models\GroupPath;
use App\Models\Users;

/**
 * Batasi baris ke data milik user di dalam subtree ROLE manajer yang sedang
 * login (role-nya + semua role bawahan, company-wide karena hierarki GLOBAL).
 *
 * Dipakai opt-in di controller list untuk fitur apa pun yang butuh
 * "manajer lihat data bawahannya", mis:
 *   Project::query()->visibleToManager('owner_id')->get();
 *
 * Bukan middleware — konsisten dgn konvensi app yang memfilter manual per
 * controller. Admin lintas-cabang (canSeeAllBranches) di-bypass.
 */
trait ScopedToSubtree
{
    /**
     * @param string      $userColumn    kolom pada model yang menyimpan users.id
     * @param string|null $managerUserId default: user login (session id)
     */
    public function scopeVisibleToManager($q, string $userColumn = 'user_id', ?string $managerUserId = null)
    {
        // Admin lintas-cabang lihat semua (konvensi lama).
        if (function_exists('canSeeAllBranches') && canSeeAllBranches()) {
            return $q;
        }

        $managerUserId = $managerUserId ?: session('id');

        $manager = Users::find($managerUserId);
        $group   = $manager ? GroupPath::find($manager->group_id) : null;
        if (!$group) {
            return $q->whereRaw('1 = 0'); // tak punya role → tak lihat apa-apa
        }

        $subtreeGroupIds = $group->subtreeIds();               // role diri + bawahan
        $subUserIds = Users::whereIn('group_id', $subtreeGroupIds)->pluck('id');

        return $q->whereIn($userColumn, $subUserIds);
    }
}
