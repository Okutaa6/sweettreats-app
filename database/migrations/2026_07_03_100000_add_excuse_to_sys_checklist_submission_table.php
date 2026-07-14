<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `excuse_reason` untuk fitur "hari ini tidak perlu diisi"
 * (status submission = 'excused'). Karyawan menandai sendiri dengan alasan;
 * hari itu tidak dihitung sebagai "belum diisi". Nullable → tidak breaking.
 */
class AddExcuseToSysChecklistSubmissionTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_submission', function (Blueprint $table) {
            $table->text('excuse_reason')->nullable()->after('review_notes');
        });
    }

    public function down()
    {
        Schema::table('sys_checklist_submission', function (Blueprint $table) {
            $table->dropColumn('excuse_reason');
        });
    }
}
