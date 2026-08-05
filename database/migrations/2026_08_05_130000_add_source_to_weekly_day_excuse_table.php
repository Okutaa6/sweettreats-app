<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber pengecualian hari pada weekly submission:
 *
 *   source = 'manual' → dibuat karyawan sendiri ("hari ini tidak perlu diisi").
 *   source = 'system' → OTOMATIS dari cuti/izin yang disetujui. Karyawan TIDAK
 *                        boleh membatalkannya; ref_id = att_request.id (idempoten).
 *
 * Data lama = manual. Unique (submission_id, day_of_week) yang ada tetap dipakai
 * agar updateOrCreate cuti/izin idempoten.
 */
class AddSourceToWeeklyDayExcuseTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_weekly_day_excuse', function (Blueprint $table) {
            $table->string('source', 16)->default('manual')->after('reason')->index();
            $table->string('ref_id', 255)->nullable()->after('source')->index();
        });

        DB::table('sys_checklist_weekly_day_excuse')
            ->whereNull('source')
            ->update(['source' => 'manual']);
    }

    public function down()
    {
        Schema::table('sys_checklist_weekly_day_excuse', function (Blueprint $table) {
            $table->dropColumn(['source', 'ref_id']);
        });
    }
}
