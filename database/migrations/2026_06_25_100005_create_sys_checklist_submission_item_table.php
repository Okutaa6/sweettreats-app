<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item detail tiap submission — 1 row per task aktif saat snapshot dibuat.
 *
 * task_id di-soft-ref (boleh NULL kalau task master di-hard-delete).
 * Title-nya di-snapshot ke category_title_snapshot & task_title_snapshot
 * supaya edit master tidak mengubah tampilan history (penting untuk audit
 * akreditasi).
 */
class CreateSysChecklistSubmissionItemTable extends Migration
{
    public function up()
    {
        Schema::create('sys_checklist_submission_item', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('submission_id', 255)->index();
            $table->string('task_id', 255)->nullable()->index();
            $table->string('category_title_snapshot', 128);
            $table->string('task_title_snapshot', 255);
            $table->integer('category_sort_order')->default(0);
            $table->integer('task_sort_order')->default(0);
            $table->tinyInteger('is_done')->default(0)->index();
            $table->text('note')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_checklist_submission_item');
    }
}
