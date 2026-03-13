<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSysProfileTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sys_profile', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('syslogo', 255)->nullable();
            $table->string('systitle', 255)->nullable();
            $table->string('sysname', 255)->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sys_profile');
    }
}