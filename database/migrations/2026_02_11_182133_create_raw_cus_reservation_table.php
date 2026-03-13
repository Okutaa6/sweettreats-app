<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRawCusReservationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('raw_cus_reservation', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('raw_branch_id', 255)->nullable()->index();
            $table->string('raw_customer_id', 255)->nullable()->index();
            $table->string('raw_doctor_id', 255)->nullable()->index();
            $table->date('date')->nullable(false)->index();
            $table->time('start_hour')->nullable();
            $table->time('end_hour')->nullable();
            $table->tinyInteger('is_waiting')->nullable();
            $table->timestamp('created_at')->nullable(false);
            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by', 255)->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('raw_cus_reservation');
    }
}