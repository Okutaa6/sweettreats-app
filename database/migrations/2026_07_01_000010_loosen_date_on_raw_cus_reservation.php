<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Longgarkan kolom `date` di raw_cus_reservation jadi nullable supaya
 * pasien waiting list (belum punya tanggal treatment) bisa di-promote
 * dari staging tanpa rejected oleh constraint NOT NULL. UI list sudah
 * handle date null (lihat list.blade.php — pakai "—" / placeholder).
 *
 * Butuh doctrine/dbal supaya $table->change() jalan di MySQL — sudah
 * ada di composer.json via package indirect (cek bila gagal: composer
 * require doctrine/dbal --dev).
 */
class LoosenDateOnRawCusReservation extends Migration
{
    public function up()
    {
        Schema::table('raw_cus_reservation', function (Blueprint $table) {
            $table->date('date')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('raw_cus_reservation', function (Blueprint $table) {
            $table->date('date')->nullable(false)->change();
        });
    }
}
