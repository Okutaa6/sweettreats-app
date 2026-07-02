<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header pengisian checklist harian. Satu row per (user_id, submission_date).
 *
 * State machine:
 *   draft → submitted → approved | rejected
 *   rejected → draft (resubmit oleh karyawan)
 *   draft → missed (lewat cron, Fase 2 — saat hari sudah lewat tapi belum submit)
 *
 * division_id & raw_branch_id di-snapshot saat row dibuat, supaya kalau
 * karyawan pindah divisi/cabang, history tetap akurat.
 */
class CreateSysChecklistSubmissionTable extends Migration
{
    public function up()
    {
        Schema::create('sys_checklist_submission', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('user_id', 255)->index();
            $table->string('division_id', 32)->index();
            $table->string('raw_branch_id', 255)->index();
            $table->date('submission_date')->index();
            $table->string('status', 16)->default('draft')->index();
            $table->integer('total_tasks')->default(0);
            $table->integer('done_tasks')->default(0);
            $table->string('reviewer_user_id', 255)->nullable()->index();
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            // Jangan dobel — 1 user hanya boleh 1 submission per tanggal
            $table->unique(['user_id', 'submission_date'], 'uniq_user_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_checklist_submission');
    }
}
