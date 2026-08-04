<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah hierarki organisasi GLOBAL ke tabel divisi. Divisi operasional
 * (FO/GS/ASDENT) yang sudah ada diperluas menjadi pohon jabatan lengkap
 * dengan menambahkan node manajemen (HEAD_OPS/FINANCE/COORD_ASDENT) via
 * ChecklistDivisionSeeder.
 *
 *   parent_id → adjacency list, self-FK ke sys_checklist_division.id (null = root)
 *   level     → kedalaman terdenormalisasi (root=0), dipakai org chart & cek manajer
 *   kind      → 'management' | 'operational'; operasional = leaf pemilik checklist
 *
 * Backward compat penuh: baris FO/GS/ASDENT lama TIDAK diubah oleh migration
 * ini (hanya menambah kolom nullable/default). Parent/level/kind di-set lewat
 * seeder. Node manajemen tidak punya checklist category → activeCategories()
 * mengembalikan kosong, tanpa polusi domain checklist.
 *
 * Money-gate (Finance) SENGAJA tidak ditaruh di sini — itu properti jenis
 * pengajuan (registry ApprovalRequest::types()), bukan properti posisi.
 */
class AddHierarchyToSysChecklistDivisionTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_division', function (Blueprint $table) {
            $table->string('parent_id', 32)->nullable()->after('id')->index();
            $table->tinyInteger('level')->default(0)->after('parent_id')->index();
            $table->string('kind', 16)->default('operational')->after('level');
        });
    }

    public function down()
    {
        Schema::table('sys_checklist_division', function (Blueprint $table) {
            $table->dropColumn(['parent_id', 'level', 'kind']);
        });
    }
}
