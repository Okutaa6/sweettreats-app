<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('nama', 255)->nullable();
            $table->string('email', 255)->nullable()->unique();
            $table->string('password', 255)->nullable();
            $table->tinyInteger('status')->nullable();
            $table->dateTime('last_login')->nullable();
            $table->string('reset_token', 255)->nullable();
            $table->string('register_token', 255)->nullable();
            $table->string('group_id', 255)->nullable()->index();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}