<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah day_of_week (1=Senin … 7=Minggu, ISO) di task. Hanya dipakai untuk
 * task di bawah kategori cadence=weekly — task harian biarkan NULL.
 *
 * Saat bootstrap weekly submission, task di-expand jadi item per day_of_week
 * (1 item per task — bukan per occurrence dalam bulan).
 */
class AddDayOfWeekToSysChecklistTaskTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_task', function (Blueprint $table) {
            $table->tinyInteger('day_of_week')->nullable()->after('description')->index();
        });
    }

    public function down()
    {
        Schema::table('sys_checklist_task', function (Blueprint $table) {
            $table->dropIndex(['day_of_week']);
            $table->dropColumn('day_of_week');
        });
    }
}
