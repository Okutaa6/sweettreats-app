<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyimpanan pengaturan sederhana (key-value) untuk teks yang bisa diedit
 * admin tanpa deploy — mis. teks Info/pengumuman running-text di my-checklist.
 */
class CreateSysSettingTable extends Migration
{
    public function up()
    {
        Schema::create('sys_setting', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_setting');
    }
}
