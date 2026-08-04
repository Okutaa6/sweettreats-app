<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto before/after OPSIONAL untuk item Form Mingguan (sementara khusus divisi GS,
 * mengikuti Form Checklist Monitoring Kegiatan GS Weekly yang memang meminta bukti
 * foto before/after tiap task).
 *
 *   - photo_before_path : path relatif dari public/ ke foto "before" (nullable)
 *   - photo_after_path  : path relatif dari public/ ke foto "after"  (nullable)
 *
 * Terpisah dari kolom `photo_path` (mekanisme "wajib foto" tunggal yang sudah ada).
 * Nullable → tidak breaking untuk item lama & divisi non-GS.
 */
class AddBeforeAfterPhotoToSysChecklistWeeklySubmissionItemTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_weekly_submission_item', function (Blueprint $table) {
            $table->string('photo_before_path', 255)->nullable()->after('photo_path');
            $table->string('photo_after_path', 255)->nullable()->after('photo_before_path');
        });
    }

    public function down()
    {
        Schema::table('sys_checklist_weekly_submission_item', function (Blueprint $table) {
            $table->dropColumn(['photo_before_path', 'photo_after_path']);
        });
    }
}
