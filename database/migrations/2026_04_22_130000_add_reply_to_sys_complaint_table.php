<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReplyToSysComplaintTable extends Migration
{
    public function up()
    {
        Schema::table('sys_complaint', function (Blueprint $table) {
            $table->text('reply')->nullable()->after('status');
        });
    }

    public function down()
    {
        Schema::table('sys_complaint', function (Blueprint $table) {
            $table->dropColumn('reply');
        });
    }
}
