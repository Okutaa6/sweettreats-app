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
        $recapId  = 'abs-recap-000000000000';
        $periodId = 'abs-period-00000000000';
        $akunGrpId = 'akun-parent-0000000000';
        $akunAbsId = 'akun-absensi-000000000';
        $persGrpId = 'pers-parent-0000000000';
        $persInbId = 'pers-inbox-00000000000';

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
                'urutan_path' => null, 'link' => 'absensi',
                'pid_path' => $parentId . ',' . $uploadId,
            ],
            [
                // link = full-path (buat href sidebar). AuthGuard tetap lolos via
                // node 'absensi' (segment(1) URL 'absensi/recap' = 'absensi').
                'id' => $recapId, 'pid' => $parentId, 'nama' => 'Rekap Persetujuan',
                'icon' => 'dripicons-checklist', 'urutan' => 2,
                'urutan_path' => null, 'link' => 'absensi/recap',
                'pid_path' => $parentId . ',' . $recapId,
            ],
            [
                'id' => $periodId, 'pid' => $parentId, 'nama' => 'Periode Gaji',
                'icon' => 'dripicons-lock', 'urutan' => 3,
                'urutan_path' => null, 'link' => 'absensi/periode',
                'pid_path' => $parentId . ',' . $periodId,
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
                'urutan_path' => null, 'link' => 'akun',
                'pid_path' => $akunGrpId . ',' . $akunAbsId,
            ],
            // ── Persetujuan (inbox approver) — grant semua role, inbox kosong utk non-approver ──
            [
                'id' => $persGrpId, 'pid' => '', 'nama' => 'Persetujuan',
                'icon' => 'icon-fa fas fa-check-circle', 'urutan' => 94,
                'urutan_path' => null, 'link' => '', 'pid_path' => null,
            ],
            [
                'id' => $persInbId, 'pid' => $persGrpId, 'nama' => 'Inbox Persetujuan',
                'icon' => 'dripicons-inbox', 'urutan' => 1,
                'urutan_path' => null, 'link' => 'persetujuan',
                'pid_path' => $persGrpId . ',' . $persInbId,
            ],
        ];
        foreach ($items as $row) {
            DB::table('access_path')->updateOrInsert(['id' => $row['id']], $row);
        }

        // Absensi admin → finance & admin. Akun Saya → SEMUA role (self-service).
        // WAJIB grant PARENT juga: buildTree (access:rebuild) cuma masukin node yg di-grant,
        // dan parent (pid kosong) harus ada di set biar child-nya tampil di sidebar.
        // Parent link='' → tak ganggu AuthGuard (tak match segment apa pun).
        $this->grant([$parentId, $uploadId, $recapId, $periodId], ['Finance', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin']);
        $allRoles = DB::table('group_path')->pluck('nama')->all();
        $this->grant([$akunGrpId, $akunAbsId], $allRoles);
        // Persetujuan → semua role (visibilitas per-approver ditunda; controller scope by session id).
        $this->grant([$persGrpId, $persInbId], $allRoles);
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
