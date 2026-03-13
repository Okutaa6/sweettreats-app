<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRawCustomerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('raw_customer', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('source_app', 255)->nullable()->index();
            $table->string('name', 255)->nullable();
            $table->string('phone', 255)->nullable();
            $table->string('channel', 255)->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('raw_customer');
    }
}