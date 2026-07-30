<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tandai apakah satu catatan servis mengandung "Cuci AC" — hanya visit yang
 * mencuci yang me-reset jadwal cuci (last_wash_date). Visit isi-freon /
 * sparepart saja TIDAK menggeser jatuh tempo cuci. Default 1: record lama
 * (sebelum pemisahan Cuci/Freon) semuanya dianggap kejadian cuci.
 */
class AddIsWashToSysAcServiceRecordTable extends Migration
{
    public function up()
    {
        Schema::table('sys_ac_service_record', function (Blueprint $table) {
            $table->tinyInteger('is_wash')->default(1)->after('service_date')->index();
        });
    }

    public function down()
    {
        Schema::table('sys_ac_service_record', function (Blueprint $table) {
            $table->dropColumn('is_wash');
        });
    }
}
