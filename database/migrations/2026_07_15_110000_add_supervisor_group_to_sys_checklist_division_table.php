<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemetaan divisi checklist → ROLE penanggung jawab (supervisor). Membuat
 * supervisi checklist bisa DIATUR lewat UI, bukan hardcode di isChecklistSupervisor().
 *
 *   supervisor_group_id → id role di group_path yang mensupervisi divisi ini.
 *
 * Satu divisi = satu role penanggung jawab; satu role boleh memegang banyak
 * divisi. Nullable & default null → belum ditugaskan (admin menyetel via halaman
 * Ubah Role). Visibility supervisor dihitung dari role + bawahannya (subtree),
 * jadi atasan otomatis ikut melihat divisi bawahannya.
 */
class AddSupervisorGroupToSysChecklistDivisionTable extends Migration
{
    public function up(): void
    {
        Schema::table('sys_checklist_division', function (Blueprint $table) {
            $table->string('supervisor_group_id', 255)->nullable()->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sys_checklist_division', function (Blueprint $table) {
            $table->dropColumn('supervisor_group_id');
        });
    }
}
