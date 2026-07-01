<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item detail tiap weekly submission. 1 row per task aktif (yg punya
 * day_of_week) saat snapshot dibuat. day_of_week di-snapshot juga supaya
 * kalau task master diubah hari-nya, history aman.
 *
 * Title disnapshot ke *_snapshot fields, persis pola submission harian.
 */
class CreateSysChecklistWeeklySubmissionItemTable extends Migration
{
    public function up()
    {
        Schema::create('sys_checklist_weekly_submission_item', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('submission_id', 255)->index();
            $table->string('task_id', 255)->nullable()->index();
            $table->string('category_title_snapshot', 128);
            $table->string('task_title_snapshot', 255);
            $table->integer('category_sort_order')->default(0);
            $table->integer('task_sort_order')->default(0);
            $table->tinyInteger('day_of_week')->index(); // 1=Senin … 7=Minggu
            $table->tinyInteger('is_done')->default(0)->index();
            $table->text('note')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_checklist_weekly_submission_item');
    }
}
