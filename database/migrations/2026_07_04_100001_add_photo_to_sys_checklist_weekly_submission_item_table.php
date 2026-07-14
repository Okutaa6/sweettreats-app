<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dukungan foto bukti pada item Form Mingguan:
 *   - requires_photo : snapshot flag task saat item dibuat (biar aman kalau
 *     master task diubah setelah submission mulai).
 *   - photo_path     : path relatif dari public/ ke foto yang di-upload ops
 *     (nullable). Format sama dengan attachment petty cash.
 * Nullable / default 0 → tidak breaking untuk item lama.
 */
class AddPhotoToSysChecklistWeeklySubmissionItemTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_weekly_submission_item', function (Blueprint $table) {
            $table->tinyInteger('requires_photo')->default(0)->after('note');
            $table->string('photo_path', 255)->nullable()->after('requires_photo');
        });
    }

    public function down()
    {
        Schema::table('sys_checklist_weekly_submission_item', function (Blueprint $table) {
            $table->dropColumn(['requires_photo', 'photo_path']);
        });
    }
}
