<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRawDocServiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('raw_doc_service', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('name', 255)->nullable();
            $table->double('duration_minutes')->nullable(false);
            $table->double('price')->nullable(false);
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
        Schema::dropIfExists('raw_doc_service');
    }
}