<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapping karyawan (users) ke divisi operasional + cabang. History-aware
 * via effective_from / effective_to supaya saat karyawan pindah divisi
 * atau resign, submission lama tetap utuh untuk audit akreditasi.
 *
 * Resolusi "divisi aktif user" = row dengan is_primary=1 DAN
 * (effective_to IS NULL OR effective_to >= today).
 */
class CreateSysUserPositionTable extends Migration
{
    public function up()
    {
        Schema::create('sys_user_position', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('user_id', 255)->index();
            $table->string('division_id', 32)->index();      // FK sys_checklist_division
            $table->string('raw_branch_id', 255)->index();   // FK raw_branch (scoping)
            $table->tinyInteger('is_primary')->default(1)->index();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_user_position');
    }
}
