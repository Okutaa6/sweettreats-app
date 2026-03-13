<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRawDoctorScheduleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('raw_doctor_schedule', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('raw_doctor_id', 255)->nullable()->index();
            $table->string('raw_branch_id', 255)->nullable()->index();
            $table->double('day')->nullable(false)->index();
            $table->time('start_hour')->nullable(false);
            $table->time('end_hour')->nullable(false);
            $table->timestamp('created_at')->nullable(false);
            $table->string('created_by', 255)->nullable();
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
        Schema::dropIfExists('raw_doctor_schedule');
    }
}