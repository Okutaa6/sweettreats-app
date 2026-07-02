<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Idempotent seeder untuk akses role Finance.
 *
 * Asumsi: row group_path dengan nama 'Finance' sudah ada (dibuat manual di
 * Kelola Role atau via UI). Seeder ini cuma grant akses menu — tidak bikin
 * role baru, supaya safe dijalankan berulang dan tidak mengganggu user yang
 * sudah punya assignment.
 *
 * Yang di-grant:
 *   1. Menu 'pettycash' (Operational Expense / branch page) — view-only di
 *      level controller + view (lihat helper canEditPettyCash() di Helpers.php
 *      dan guard di PettyCashController).
 *   2. Menu 'finance' (Monitoring Petty Cash dashboard) — full akses untuk
 *      approve/reject pengajuan top-up.
 */
class FinanceRoleSeeder extends Seeder
{
    public function run(): void
    {
        $groupId = DB::table('group_path')->where('nama', 'Finance')->value('id');
        if (! $groupId) {
            $this->command->warn('Role "Finance" tidak ditemukan di group_path — skip seeder.');
            return;
        }

        // Menu yang harus di-grant ke role Finance (by access_path.link)
        $menus = ['pettycash', 'finance'];
        foreach ($menus as $link) {
            $accessId = DB::table('access_path')->where('link', $link)->value('id');
            if (! $accessId) {
                $this->command->warn("Menu '{$link}' tidak ditemukan di access_path — skip.");
                continue;
            }
            $this->grantAccess($groupId, $accessId);
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
