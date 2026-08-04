<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hapus baris divisi "Supervisor" (id=SPV) yang nyangkut di sys_checklist_division.
 *
 * SPV bukan divisi operasional — ia sisa eksperimen HIERARKI DIVISI yang sudah
 * dibatalkan (kolom parent_id/level/kind sempat ditambah lalu dibuang lagi,
 * lihat migration ..._100000_add_hierarchy... & ..._100040_drop_hierarchy...).
 * Saat itu "Supervisor" masuk sebagai node jabatan; kolomnya dibuang tapi
 * BARISNYA tertinggal, sehingga muncul di kartu "Progres per Divisi".
 *
 * Supervisor kini murni jabatan/role di group_path — bukan divisi checklist.
 * Divisi operasional yang sah hanya FO / GS / ASDENT (lihat ChecklistDivisionSeeder).
 */
class RemoveStraySupervisorDivision extends Migration
{
    public function up(): void
    {
        // Hanya hapus bila benar-benar tak terpakai → aman & idempotent.
        $used = DB::table('sys_user_position')->where('division_id', 'SPV')->exists()
             || DB::table('sys_checklist_category')->where('division_id', 'SPV')->exists();

        if (! $used) {
            DB::table('sys_checklist_division')->where('id', 'SPV')->delete();
        }
    }

    public function down(): void
    {
        // No-op: data sampah, sengaja tidak dikembalikan.
    }
}
