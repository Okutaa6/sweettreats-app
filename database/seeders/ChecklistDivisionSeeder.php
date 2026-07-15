<?php

namespace Database\Seeders;

use App\Models\ChecklistDivision;
use Illuminate\Database\Seeder;

/**
 * Seed 3 divisi operasional default klinik (FO / GS / ASDENT). Idempotent.
 *
 * Hierarki organisasi (parent_id/level/kind) SENGAJA tidak diisi di sini —
 * struktur dibiarkan KOSONG secara default. Admin menyusun sendiri pohon
 * jabatan (mis. menambah Head Ops/Finance dan menempatkan divisi di bawahnya)
 * lewat halaman Struktur Organisasi (tab di /setgroup).
 */
class ChecklistDivisionSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            ['id' => 'FO',     'name' => 'Front Office',       'description' => 'Menerima dan mengarahkan pasien',  'sort_order' => 10],
            ['id' => 'GS',     'name' => 'General Services',   'description' => 'Kebersihan dan logistik klinik',   'sort_order' => 20],
            ['id' => 'ASDENT', 'name' => 'Assistant Dentist',  'description' => 'Mendampingi dokter saat tindakan', 'sort_order' => 30],
        ];

        foreach ($divisions as $d) {
            ChecklistDivision::updateOrCreate(
                ['id' => $d['id']],
                array_merge($d, ['is_active' => 1, 'updated_at' => now()])
            );
        }
    }
}
