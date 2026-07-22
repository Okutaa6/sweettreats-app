<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Struktur role/jabatan berjenjang (group_path) — melengkapi struktur dev.
 *
 * Mengadopsi NAMA role dev (konsisten dgn Reza & checklist) lalu MELENGKAPI:
 *   - apex Executive (dev sudah punya, di sini jadi puncak eksplisit),
 *   - isi induk role yatim: Coordinator Dentist → Executive, Sales → Supervisor,
 *   - tambah role yang dev belum punya: Finance, IT.
 *
 * Approval absensi memakai hierarki ini (att_approval_rule + resolver). Checklist
 * Reza juga memakai pohon ini (§6.1) — perubahan sudah dikonfirmasi ke Reza.
 *
 * Role admin (Admin/Admin General/Admin Backup/Super Admin) TIDAK diubah (akses-only).
 * Idempotent: updateOrInsert by nama; parent di-set pass kedua.
 */
class OrgRoleStructureSeeder extends Seeder
{
    public function run(): void
    {
        // nama => [induk (nama) atau null, level, landing_page, theme]
        // landing 'dashboard' dipakai di LOCAL (checklist belum ada); di deploy
        // selaraskan ke landing dev (my-checklist/report/dst).
        $roles = [
            'Executive'           => [null,                  0, 'dashboard',          'theme-default'],
            'Supervisor'          => ['Executive',           1, 'dashboard',          'theme-default'],
            'Coordinator Dentist' => ['Executive',           1, 'dashboard',          'theme-default'],
            'Head Marketing'      => ['Executive',           1, 'dashboard',          'theme-marketing'],
            'Finance'             => ['Executive',           1, 'dashboard',          'theme-default'],  // BARU
            'IT'                  => ['Executive',           1, 'dashboard',          'theme-default'],  // BARU
            'General Services'    => ['Supervisor',          2, 'dashboard',          'theme-default'],
            'Front Office'        => ['Supervisor',          2, 'dashboard',          'theme-default'],
            'Sales'               => ['Supervisor',          2, 'dashboard',          'theme-default'],  // isi induk (dev yatim)
            'Assistant Dentist'   => ['Coordinator Dentist', 2, 'dashboard',          'theme-default'],
            'Creative'            => ['Head Marketing',      2, 'dashboard',          'theme-default'],
        ];

        // Pass 1: pastikan semua role ada (parent belum di-set)
        foreach ($roles as $nama => [$parent, $level, $landing, $theme]) {
            $existing = DB::table('group_path')->where('nama', $nama)->first();
            if ($existing) {
                DB::table('group_path')->where('id', $existing->id)->update([
                    'level' => $level, 'landing_page' => $landing, 'theme' => $theme,
                ]);
            } else {
                DB::table('group_path')->insert([
                    'id' => (string) Str::uuid(), 'nama' => $nama,
                    'parent_id' => null, 'level' => $level,
                    'deskripsi' => $nama, 'landing_page' => $landing, 'theme' => $theme,
                ]);
            }
        }

        // Pass 2: set parent_id (by nama)
        $idByName = DB::table('group_path')->pluck('id', 'nama');
        foreach ($roles as $nama => [$parent, $level, $landing, $theme]) {
            $pid = $parent ? ($idByName[$parent] ?? null) : null;
            DB::table('group_path')->where('nama', $nama)->update(['parent_id' => $pid]);
        }

        $this->command?->info('Struktur role selesai. Total role: ' . DB::table('group_path')->count());
    }
}
