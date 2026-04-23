<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSysComplaintTable extends Migration
{
    public function up()
    {
        Schema::create('sys_complaint', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('sender_user_id', 255)->index();
            $table->string('sender_name', 255)->nullable();
            $table->string('raw_branch_id', 255)->nullable()->index();
            $table->text('message');
            $table->tinyInteger('is_read')->default(0)->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_complaint');
    }
}
