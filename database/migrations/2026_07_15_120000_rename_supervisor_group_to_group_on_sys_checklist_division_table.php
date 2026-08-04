<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename sys_checklist_division.supervisor_group_id → group_id.
 *
 * Makna kolom berubah: model "role staf = divisi" — kolom ini kini menyimpan
 * ROLE STAF pemilik divisi (anggota role tsb mengisi checklist divisi ini),
 * bukan role supervisor. Supervisor dihitung dari hierarki (atasan role tsb).
 *
 * doctrine/dbal tidak terpasang → pakai raw SQL MySQL CHANGE. Index lama tetap
 * valid pada kolom yang di-rename.
 */
class RenameSupervisorGroupToGroupOnSysChecklistDivisionTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sys_checklist_division', 'supervisor_group_id')
            && ! Schema::hasColumn('sys_checklist_division', 'group_id')) {
            DB::statement('ALTER TABLE sys_checklist_division CHANGE supervisor_group_id group_id VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sys_checklist_division', 'group_id')
            && ! Schema::hasColumn('sys_checklist_division', 'supervisor_group_id')) {
            DB::statement('ALTER TABLE sys_checklist_division CHANGE group_id supervisor_group_id VARCHAR(255) NULL');
        }
    }
}
