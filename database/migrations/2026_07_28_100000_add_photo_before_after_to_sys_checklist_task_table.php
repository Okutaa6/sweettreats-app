<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `photo_before_after` pada task. Bersama `requires_photo` menandai
 * mode foto per-task (mendekopel mode before/after dari division_id):
 *
 *   requires_photo=0                     → tanpa foto
 *   requires_photo=1, photo_before_after=0 → perlu foto tunggal
 *   requires_photo=1, photo_before_after=1 → perlu foto before/after (khusus GS)
 *
 * Backfill: task GS lama yang `requires_photo=1` di-set before/after=1 supaya
 * perilaku otomatis GS (yang dulu ditentukan division_id) tetap sama.
 */
class AddPhotoBeforeAfterToSysChecklistTaskTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_task', function (Blueprint $table) {
            $table->tinyInteger('photo_before_after')->default(0)->after('requires_photo');
        });

        DB::statement(
            "UPDATE sys_checklist_task t
             JOIN sys_checklist_category c ON c.id = t.category_id
             SET t.photo_before_after = 1
             WHERE c.division_id = 'GS' AND t.requires_photo = 1"
        );
    }

    public function down()
    {
        Schema::table('sys_checklist_task', function (Blueprint $table) {
            $table->dropColumn('photo_before_after');
        });
    }
}
