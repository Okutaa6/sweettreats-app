<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header pengisian checklist mingguan — 1 row per (user_id, week_start_date).
 * week_start_date selalu Senin (ISO week start).
 *
 * State machine sama dengan submission harian:
 *   draft → submitted → approved | rejected
 *   rejected → draft (resubmit)
 *
 * year_week disimpan redundan (mis. "2026-W26") supaya gampang filter/listing
 * tanpa expression kompleks, dan bisa dipakai sebagai display.
 */
class CreateSysChecklistWeeklySubmissionTable extends Migration
{
    public function up()
    {
        Schema::create('sys_checklist_weekly_submission', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('user_id', 255)->index();
            $table->string('division_id', 32)->index();
            $table->string('raw_branch_id', 255)->index();
            $table->date('week_start_date')->index();
            $table->string('year_week', 16)->index();
            $table->string('status', 16)->default('draft')->index();
            $table->integer('total_tasks')->default(0);
            $table->integer('done_tasks')->default(0);
            $table->string('reviewer_user_id', 255)->nullable()->index();
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['user_id', 'week_start_date'], 'uniq_user_week');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_checklist_weekly_submission');
    }
}
