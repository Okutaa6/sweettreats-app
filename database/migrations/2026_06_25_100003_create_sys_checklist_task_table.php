<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task dalam kategori. Contoh: "Membuka ruang tindakan", "Sterilisasi alat
 * sesuai SOP". Saat karyawan membuka /my-checklist hari ini, task aktif
 * di-iterate dan di-snapshot ke sys_checklist_submission_item.
 *
 * requires_note: kalau true, karyawan wajib isi catatan saat mencentang
 * (Fase 2 — UI MVP belum enforce).
 */
class CreateSysChecklistTaskTable extends Migration
{
    public function up()
    {
        Schema::create('sys_checklist_task', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('category_id', 255)->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->tinyInteger('requires_note')->default(0);
            $table->integer('sort_order')->default(0)->index();
            $table->tinyInteger('is_active')->default(1)->index();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_checklist_task');
    }
}
