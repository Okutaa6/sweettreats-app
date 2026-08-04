<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan servis (kejadian cuci AC) yang diinput GS cabang — satu record =
 * satu kunjungan servis pada satu tanggal. Rincian biaya (cuci, isi freon,
 * ganti sparepart, dll.) disimpan per baris di sys_ac_service_item.
 * raw_branch_id di-snapshot agar rekap per cabang tidak perlu join unit.
 */
class CreateSysAcServiceRecordTable extends Migration
{
    public function up()
    {
        Schema::create('sys_ac_service_record', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('ac_unit_id', 255)->index();
            $table->string('raw_branch_id', 255)->index();
            $table->date('service_date')->index();
            $table->string('created_by_user_id', 255)->index();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['ac_unit_id', 'service_date'], 'idx_ac_service_unit_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_ac_service_record');
    }
}
