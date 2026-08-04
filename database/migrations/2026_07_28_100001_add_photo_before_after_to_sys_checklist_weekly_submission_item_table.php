<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot flag `photo_before_after` di item Form Mingguan (sejajar `requires_photo`).
 * Render form/review sekarang memakai flag per-item ini, bukan lagi division_id.
 *
 * Backfill: item dari submission divisi GS yang `requires_photo=1` di-set 1, supaya
 * form berjalan/lama tetap menampilkan foto before/after setelah render berpindah
 * ke flag per-item.
 */
class AddPhotoBeforeAfterToSysChecklistWeeklySubmissionItemTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_weekly_submission_item', function (Blueprint $table) {
            $table->tinyInteger('photo_before_after')->default(0)->after('requires_photo');
        });

        DB::statement(
            "UPDATE sys_checklist_weekly_submission_item i
             JOIN sys_checklist_weekly_submission s ON s.id = i.submission_id
             SET i.photo_before_after = 1
             WHERE s.division_id = 'GS' AND i.requires_photo = 1"
        );
    }

    public function down()
    {
        Schema::table('sys_checklist_weekly_submission_item', function (Blueprint $table) {
            $table->dropColumn('photo_before_after');
        });
    }
}
