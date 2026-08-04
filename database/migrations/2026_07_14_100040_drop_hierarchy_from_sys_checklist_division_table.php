<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarki organisasi dipindah ke Role (group_path). Kolom hierarki di
 * sys_checklist_division tidak dipakai lagi → dibuang; divisi kembali murni
 * untuk domain checklist (FO/GS/ASDENT).
 */
class DropHierarchyFromSysChecklistDivisionTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_division', function (Blueprint $table) {
            if (Schema::hasColumn('sys_checklist_division', 'parent_id')) {
                $table->dropColumn('parent_id');
            }
            if (Schema::hasColumn('sys_checklist_division', 'level')) {
                $table->dropColumn('level');
            }
            if (Schema::hasColumn('sys_checklist_division', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }

    public function down()
    {
        Schema::table('sys_checklist_division', function (Blueprint $table) {
            $table->string('parent_id', 32)->nullable()->after('id')->index();
            $table->tinyInteger('level')->default(0)->after('parent_id')->index();
            $table->string('kind', 16)->default('operational')->after('level');
        });
    }
}
