<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddStatusToSysComplaintTable extends Migration
{
    public function up()
    {
        Schema::table('sys_complaint', function (Blueprint $table) {
            $table->string('status', 20)->default('new')->after('message')->index();
        });

        // Migrasi data lama: is_read=0 -> 'new', is_read=1 -> 'received'
        if (Schema::hasColumn('sys_complaint', 'is_read')) {
            DB::statement("UPDATE sys_complaint SET status = CASE WHEN is_read = 1 THEN 'received' ELSE 'new' END");

            Schema::table('sys_complaint', function (Blueprint $table) {
                $table->dropIndex(['is_read']);
                $table->dropColumn('is_read');
            });
        }
    }

    public function down()
    {
        Schema::table('sys_complaint', function (Blueprint $table) {
            $table->tinyInteger('is_read')->default(0)->index()->after('message');
        });

        DB::statement("UPDATE sys_complaint SET is_read = CASE WHEN status IN ('received', 'handled') THEN 1 ELSE 0 END");

        Schema::table('sys_complaint', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
}
