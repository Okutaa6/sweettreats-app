<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan karyawan saat submit Form Mingguan (opsional) — konteks/keterangan
 * untuk supervisor, terpisah dari `review_notes` (catatan supervisor) dan
 * `excuse_reason` (alasan dikecualikan). Nullable → tidak breaking untuk row lama.
 */
class AddSubmitNoteToSysChecklistWeeklySubmissionTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_weekly_submission', function (Blueprint $table) {
            $table->text('submit_note')->nullable()->after('excuse_reason');
        });
    }

    public function down()
    {
        Schema::table('sys_checklist_weekly_submission', function (Blueprint $table) {
            $table->dropColumn('submit_note');
        });
    }
}
