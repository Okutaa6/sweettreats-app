<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sama seperti versi harian: kolom `excuse_reason` untuk status 'excused'
 * pada submission mingguan.
 */
class AddExcuseToSysChecklistWeeklySubmissionTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_weekly_submission', function (Blueprint $table) {
            $table->text('excuse_reason')->nullable()->after('review_notes');
        });
    }

    public function down()
    {
        Schema::table('sys_checklist_weekly_submission', function (Blueprint $table) {
            $table->dropColumn('excuse_reason');
        });
    }
}
