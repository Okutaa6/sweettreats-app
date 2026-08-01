<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alur approval catatan servis AC. GS mencatat servis → status 'pending';
 * hanya setelah supervisor 'approved' record itu resmi masuk riwayat, me-reset
 * jadwal cuci, dan dihitung di KPI biaya. 'rejected' = ditolak (dengan alasan).
 *
 * Default 'approved' agar record LAMA (sebelum fitur ini) tetap terhitung —
 * record BARU di-set 'pending' eksplisit oleh storeService().
 */
class AddApprovalToSysAcServiceRecordTable extends Migration
{
    public function up()
    {
        Schema::table('sys_ac_service_record', function (Blueprint $table) {
            $table->string('status', 20)->default('approved')->after('is_wash')->index();
            $table->string('reviewed_by_user_id')->nullable()->after('created_by_user_id');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->string('review_note', 500)->nullable()->after('reviewed_at');
        });
    }

    public function down()
    {
        Schema::table('sys_ac_service_record', function (Blueprint $table) {
            $table->dropColumn(['status', 'reviewed_by_user_id', 'reviewed_at', 'review_note']);
        });
    }
}
