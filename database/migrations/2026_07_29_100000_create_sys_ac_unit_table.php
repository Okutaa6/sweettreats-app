<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unit AC per cabang (aset fisik). Contoh: "DEPAN FO" 1.5 PK di PAMULANG.
 * Jadwal cuci dihitung dari servis 'cuci' terakhir + wash_interval_days
 * (default 60 hari ~ 2 bulan, bisa diubah per unit oleh supervisor).
 */
class CreateSysAcUnitTable extends Migration
{
    public function up()
    {
        Schema::create('sys_ac_unit', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('raw_branch_id', 255)->index();
            $table->string('room_name', 255);
            $table->decimal('capacity_pk', 4, 2)->nullable();
            $table->integer('wash_interval_days')->default(60);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->tinyInteger('is_active')->default(1)->index();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_ac_unit');
    }
}
