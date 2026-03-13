<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccessPathTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('access_path', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('pid', 255)->nullable()->index();
            $table->string('nama', 255)->nullable();
            $table->string('icon', 255)->nullable();
            $table->integer('urutan')->nullable();
            $table->text('urutan_path')->nullable();
            $table->string('link', 255)->nullable();
            $table->text('pid_path')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('access_path');
    }
}