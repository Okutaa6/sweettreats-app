<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Submit per-hari untuk 1 weekly submission — "tugas hari ini sudah dikirim".
 *
 * 1 row = 1 hari yang sudah disubmit pada minggu tsb. Hari yang punya row di sini
 * terkunci (tak bisa diubah). Begitu SEMUA hari ber-tugas sudah submitted ATAU
 * dikecualikan, header submission otomatis pindah ke status 'submitted' dan masuk
 * antrian review supervisor (review tetap per-minggu).
 */
class CreateSysChecklistWeeklyDaySubmitTable extends Migration
{
    public function up()
    {
        Schema::create('sys_checklist_weekly_day_submit', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('submission_id', 255)->index();
            $table->tinyInteger('day_of_week'); // ISO 2=Selasa … 7=Minggu
            $table->string('note', 1000)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['submission_id', 'day_of_week'], 'weekly_day_submit_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_checklist_weekly_day_submit');
    }
}
