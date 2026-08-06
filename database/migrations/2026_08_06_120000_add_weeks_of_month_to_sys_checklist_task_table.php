<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah weeks_of_month (CSV minggu-ke, mis. "1,3") di task. NULL/kosong =
 * SETIAP minggu (perilaku lama). Hanya dipakai task di kategori cadence=weekly —
 * task harian biarkan NULL.
 *
 * Dipakai saat bootstrap/reconcile weekly submission untuk memfilter task
 * berdasarkan "minggu ke-berapa dalam bulan" (reset tiap bulan; mgg-5 → fallback
 * mgg-4). Prod di-apply lewat database/sql/2026_08_06_task_weeks_of_month.sql —
 * migration ini mirror utk konsistensi repo.
 */
class AddWeeksOfMonthToSysChecklistTaskTable extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('sys_checklist_task', 'weeks_of_month')) return;

        Schema::table('sys_checklist_task', function (Blueprint $table) {
            $table->string('weeks_of_month', 16)->nullable()->after('day_of_week');
        });
    }

    public function down()
    {
        if (! Schema::hasColumn('sys_checklist_task', 'weeks_of_month')) return;

        Schema::table('sys_checklist_task', function (Blueprint $table) {
            $table->dropColumn('weeks_of_month');
        });
    }
}
