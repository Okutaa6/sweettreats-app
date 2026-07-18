<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Register menu Absensi di access_path + grant ke role, supaya:
 *   - Sidebar render menu (getSidebarChild())
 *   - AuthGuard membolehkan akses URL (cek access_path.link == segment(1))
 *
 * Semua sub-halaman absensi di bawah segment(1) = 'absensi', jadi cukup grant
 * satu node 'absensi'. Gating sub-halaman (upload=finance, approval=approver)
 * dilakukan di controller via helper role.
 *
 * Idempotent. Setelah seed WAJIB `php artisan access:rebuild`.
 */
class AbsensiAccessPathSeeder extends Seeder
{
    public function run(): void
    {
        $parentId = 'abs-parent-000000000000';
        $uploadId = 'abs-upload-000000000000';
        $akunGrpId = 'akun-parent-0000000000';
        $akunAbsId = 'akun-absensi-000000000';

        $items = [
            // ── Absensi (admin/finance) ──
            [
                'id' => $parentId, 'pid' => '', 'nama' => 'Absensi',
                'icon' => 'icon-fa fas fa-fingerprint', 'urutan' => 92,
                'urutan_path' => null, 'link' => '', 'pid_path' => null,
            ],
            [
                'id' => $uploadId, 'pid' => $parentId, 'nama' => 'Upload Data',
                'icon' => 'dripicons-upload', 'urutan' => 1,
                'urutan_path' => null, 'link' => 'absensi/upload',
                'pid_path' => $parentId . ',' . $uploadId,
            ],
            // ── Akun Saya (self-service, semua orang) ──
            [
                'id' => $akunGrpId, 'pid' => '', 'nama' => 'Akun Saya',
                'icon' => 'icon-fa fas fa-user-circle', 'urutan' => 93,
                'urutan_path' => null, 'link' => '', 'pid_path' => null,
            ],
            [
                'id' => $akunAbsId, 'pid' => $akunGrpId, 'nama' => 'Absensi Saya',
                'icon' => 'dripicons-calendar', 'urutan' => 1,
                'urutan_path' => null, 'link' => 'akun/absensi',
                'pid_path' => $akunGrpId . ',' . $akunAbsId,
            ],
        ];
        foreach ($items as $row) {
            DB::table('access_path')->updateOrInsert(['id' => $row['id']], $row);
        }

        // Absensi admin → finance & admin. Akun Saya → SEMUA role (self-service).
        $this->grant([$uploadId], ['Finance', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin']);
        $allRoles = DB::table('group_path')->pluck('nama')->all();
        $this->grant([$akunAbsId], $allRoles);
    }

    private function grant(array $accessIds, array $groupNames): void
    {
        $groups = DB::table('group_path')->whereIn('nama', $groupNames)->get();
        foreach ($groups as $g) {
            foreach ($accessIds as $aid) {
                $exists = DB::table('group_access')
                    ->where('group_id', $g->id)->where('access_id', $aid)->exists();
                if (! $exists) {
                    DB::table('group_access')->insert([
                        'id' => (string) Str::uuid(),
                        'group_id' => $g->id,
                        'access_id' => $aid,
                    ]);
                }
            }
        }
    }
}
