<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToRawCusReservationTable extends Migration
{
    public function up()
    {
        Schema::table('raw_cus_reservation', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('end_hour');
            // pending = Menunggu Konfirmasi
            // confirmed = Dikonfirmasi
            // waitlist = Waiting List
            // ongoing = Berlangsung
            // completed = Selesai
            // cancelled = Dibatalkan
        });
    }

    public function down()
    {
        Schema::table('raw_cus_reservation', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}
