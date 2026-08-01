<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index gabungan untuk query KPI "Biaya Servis" di monitor() — yang memfilter
 * per cabang + tahun (service_date) dan hanya record non-deleted. Tanpa index ini
 * query harus scan seluruh record per cabang per tahun.
 */
class AddBranchDateIndexToSysAcServiceRecordTable extends Migration
{
    public function up()
    {
        Schema::table('sys_ac_service_record', function (Blueprint $table) {
            $table->index(['raw_branch_id', 'service_date', 'deleted_at'], 'idx_ac_service_branch_date');
        });
    }

    public function down()
    {
        Schema::table('sys_ac_service_record', function (Blueprint $table) {
            $table->dropIndex('idx_ac_service_branch_date');
        });
    }
}
