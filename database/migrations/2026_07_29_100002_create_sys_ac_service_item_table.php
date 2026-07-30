<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian biaya per catatan servis AC. Contoh untuk satu kunjungan:
 * "Cuci AC" 340.000, "Isi freon 1/2 PK" 270.000, "Ganti kapasitor" 150.000.
 * Baris bisa ditambah bebas dari form GS (repeatable rows).
 */
class CreateSysAcServiceItemTable extends Migration
{
    public function up()
    {
        Schema::create('sys_ac_service_item', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('service_record_id', 255)->index();
            $table->string('description', 500);
            $table->bigInteger('amount')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_ac_service_item');
    }
}
