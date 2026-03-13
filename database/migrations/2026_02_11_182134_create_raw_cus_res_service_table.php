<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRawCusResServiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('raw_cus_res_service', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('raw_doctor_id', 255)->nullable()->index();
            $table->string('raw_cus_reservation_id', 255)->nullable()->index();
            $table->string('raw_doc_service_id', 255)->nullable()->index();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('raw_cus_res_service');
    }
}