<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `requires_photo` pada task. Kalau true, saat ops mengisi Form
 * Mingguan, task ini WAJIB upload foto (dari kamera) sebelum bisa dicentang
 * selesai. Sejajar dengan `requires_note`. Dipakai untuk kategori mingguan.
 * Default 0 → tidak breaking untuk task lama.
 */
class AddRequiresPhotoToSysChecklistTaskTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_task', function (Blueprint $table) {
            $table->tinyInteger('requires_photo')->default(0)->after('requires_note');
        });
    }

    public function down()
    {
        Schema::table('sys_checklist_task', function (Blueprint $table) {
            $table->dropColumn('requires_photo');
        });
    }
}
