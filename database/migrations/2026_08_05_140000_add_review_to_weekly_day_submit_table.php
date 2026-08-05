<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status review PER-HARI untuk submit mingguan. Supervisor bisa approve/tolak
 * tiap hari (mid-week) tanpa nunggu seminggu penuh. Status header mingguan
 * diturunkan (roll-up) dari kumpulan review_status ini.
 *
 * review_status: pending (default saat dikirim) | approved | rejected.
 * Hari 'rejected' dianggap "belum terkirim" (dibuka lagi utk diperbaiki), tapi
 * barisnya tetap disimpan supaya catatan tolak & reviewer tetap tampil.
 */
class AddReviewToWeeklyDaySubmitTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_weekly_day_submit', function (Blueprint $table) {
            $table->string('review_status', 16)->default('pending')->index()->after('note');
            $table->string('reviewer_user_id', 255)->nullable()->index()->after('review_status');
            $table->string('review_notes', 500)->nullable()->after('reviewer_user_id');
            $table->timestamp('reviewed_at')->nullable()->after('review_notes');
        });
    }

    public function down()
    {
        Schema::table('sys_checklist_weekly_day_submit', function (Blueprint $table) {
            $table->dropColumn(['review_status', 'reviewer_user_id', 'review_notes', 'reviewed_at']);
        });
    }
}
