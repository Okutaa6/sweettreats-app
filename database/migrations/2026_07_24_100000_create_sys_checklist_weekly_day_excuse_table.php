<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengecualian ("tidak perlu diisi") per-hari untuk 1 weekly submission.
 * Menggantikan skip level-minggu lama (submission.status = excused).
 *
 * 1 row = 1 hari yang dikecualikan pada minggu tsb. Task di hari itu dihitung
 * "satisfied" tanpa perlu dicentang, sehingga form mingguan tetap bisa 100%.
 */
class CreateSysChecklistWeeklyDayExcuseTable extends Migration
{
    public function up()
    {
        Schema::create('sys_checklist_weekly_day_excuse', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('submission_id', 255)->index();
            $table->tinyInteger('day_of_week'); // ISO 2=Selasa … 7=Minggu
            $table->string('reason', 500);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['submission_id', 'day_of_week'], 'weekly_day_excuse_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_checklist_weekly_day_excuse');
    }
}
