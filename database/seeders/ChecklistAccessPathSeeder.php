<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Register menu Checklist Akreditasi di tabel access_path supaya:
 *   - Sidebar render menu (via getSidebarChild())
 *   - AuthGuard::hasMenuAccess() membolehkan akses URL
 *
 * Idempotent — pakai updateOrInsert dengan UUID stabil.
 *
 * Struktur: 1 parent group "Akreditasi" + 4 children (Dashboard, Setup,
 * Review, Audit). Plus 1 menu "Checklist Saya" untuk karyawan biasa.
 */
class ChecklistAccessPathSeeder extends Seeder
{
    public function run(): void
    {
        $parentId       = 'cl-parent-akreditasi-0000';
        $dashId          = 'cl-dash-akreditasi-0000';
        $positionId      = 'cl-positions-akreditasi';
        $setupId         = 'cl-setup-akreditasi-0000';
        $reviewId        = 'cl-review-akreditasi-000';
        $auditId         = 'cl-audit-akreditasi-0000';
        $myChecklistId   = 'cl-my-checklist-00000000';

        $items = [
            // Parent grup (supervisor view)
            [
                'id'          => $parentId,
                'pid'         => '',
                'nama'        => 'Checklist Akreditasi',
                'icon'        => 'icon-fa fas fa-clipboard-check',
                'urutan'      => 90,
                'urutan_path' => null,
                'link'        => '',
                'pid_path'    => null,
            ],
            // Children
            [
                'id'          => $dashId,
                'pid'         => $parentId,
                'nama'        => 'Dashboard',
                'icon'        => 'dripicons-meter',
                'urutan'      => 1,
                'urutan_path' => null,
                'link'        => 'checklist',
                'pid_path'    => $parentId . ',' . $dashId,
            ],
            [
                'id'          => $positionId,
                'pid'         => $parentId,
                'nama'        => 'Posisi Karyawan',
                'icon'        => 'dripicons-user-id',
                'urutan'      => 2,
                'urutan_path' => null,
                'link'        => 'checklist/positions',
                'pid_path'    => $parentId . ',' . $positionId,
            ],
            [
                'id'          => $setupId,
                'pid'         => $parentId,
                'nama'        => 'Setup Tugas',
                'icon'        => 'dripicons-document-edit',
                'urutan'      => 3,
                'urutan_path' => null,
                'link'        => 'checklist/setup',
                'pid_path'    => $parentId . ',' . $setupId,
            ],
            [
                'id'          => $reviewId,
                'pid'         => $parentId,
                'nama'        => 'Review & Approval',
                'icon'        => 'dripicons-checklist',
                'urutan'      => 4,
                'urutan_path' => null,
                'link'        => 'checklist/review',
                'pid_path'    => $parentId . ',' . $reviewId,
            ],
            [
                'id'          => $auditId,
                'pid'         => $parentId,
                'nama'        => 'Audit Trail',
                'icon'        => 'dripicons-time-reverse',
                'urutan'      => 5,
                'urutan_path' => null,
                'link'        => 'checklist/audit',
                'pid_path'    => $parentId . ',' . $auditId,
            ],
            // Top-level menu untuk karyawan
            [
                'id'          => $myChecklistId,
                'pid'         => '',
                'nama'        => 'Checklist Saya',
                'icon'        => 'icon-fa fas fa-tasks',
                'urutan'      => 91,
                'urutan_path' => null,
                'link'        => 'my-checklist',
                'pid_path'    => $myChecklistId,
            ],
        ];

        foreach ($items as $row) {
            DB::table('access_path')->updateOrInsert(
                ['id' => $row['id']],
                $row
            );
        }

        // Auto-grant ke role admin & karyawan agar AuthGuard membolehkan.
        // AuthGuard cek `access_path.link == segment(1)`, jadi cukup grant
        // entry yang link-nya = segment URL (checklist & my-checklist).
        //
        // ADMIN     → semua menu checklist (dashboard, setup, review, audit)
        // Super Admin → idem
        // KARYAWAN  → my-checklist saja
        $adminGroupNames = ['ADMIN', 'Super Admin', 'Admin General', 'Admin Backup'];
        $employeeGroupNames = [
            'KARYAWAN', 'SALES CABANG', 'Sales', 'Marketing', 'Operasional',
            'ADMIN', 'Super Admin', 'Admin General', 'Admin Backup',
        ];

        $supervisorAccessIds = [$dashId, $positionId, $setupId, $reviewId, $auditId];
        $employeeAccessIds   = [$myChecklistId];

        $this->grantAccess($supervisorAccessIds, $adminGroupNames);
        $this->grantAccess($employeeAccessIds, $employeeGroupNames);
    }

    /**
     * Idempotent grant: hanya insert kalau pair (group_id, access_id)
     * belum ada. UUID baru digenerate per row.
     */
    private function grantAccess(array $accessIds, array $groupNames): void
    {
        $groups = DB::table('group_path')->whereIn('nama', $groupNames)->get();
        foreach ($groups as $g) {
            foreach ($accessIds as $aid) {
                $exists = DB::table('group_access')
                    ->where('group_id', $g->id)
                    ->where('access_id', $aid)
                    ->exists();
                if (! $exists) {
                    DB::table('group_access')->insert([
                        'id'        => (string) \Illuminate\Support\Str::uuid(),
                        'group_id'  => $g->id,
                        'access_id' => $aid,
                    ]);
                }
            }
        }
    }
}
