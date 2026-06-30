<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Idempotent seeder untuk menu "Monitoring Petty Cash".
 *
 * Tidak bikin role baru — siapa pun admin (Super Admin / Admin General /
 * Admin Backup) yang punya akses ke menu ini bisa pakai dashboard
 * (approve/reject pengajuan top-up dari Sales).
 *
 * Yang dibuat:
 *   1. access_path → row link='finance' (menu sidebar)
 *   2. group_access → grant Super Admin + Admin General + Admin Backup
 */
class FinanceMenuSeeder extends Seeder
{
    public function run()
    {
        // ─── 1. Menu access_path: link='finance' ───
        // Pakai segment(1) match — middleware AuthGuard cek where('link', $controller)
        // dengan $controller = request()->segment(1).
        $menuId = DB::table('access_path')->where('link', 'finance')->value('id');
        if (! $menuId) {
            $menuId = (string) Str::uuid();
            DB::table('access_path')->insert([
                'id'          => $menuId,
                'pid'         => '',
                'nama'        => 'Monitoring Petty Cash',
                'icon'        => 'icon-fa fas fa-coins',
                'urutan'      => 60,
                'urutan_path' => null,
                'link'        => 'finance',
                'pid_path'    => null,
            ]);
        }

        // ─── 2. Grant ke admin-level roles ───
        $rolesToGrant = ['Super Admin', 'Admin General', 'Admin Backup'];
        foreach ($rolesToGrant as $roleName) {
            $groupId = DB::table('group_path')->where('nama', $roleName)->value('id');
            if (! $groupId) continue;
            $this->grantAccess($groupId, $menuId);
        }
    }

    private function grantAccess(string $groupId, string $accessId): void
    {
        $exists = DB::table('group_access')
            ->where('group_id', $groupId)
            ->where('access_id', $accessId)
            ->exists();
        if ($exists) return;

        DB::table('group_access')->insert([
            'id'        => (string) Str::uuid(),
            'group_id'  => $groupId,
            'access_id' => $accessId,
        ]);
    }
}
