<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPhotoToRawDoctorTable extends Migration
{
    public function up()
    {
        Schema::table('raw_doctor', function (Blueprint $table) {
            $table->string('photo', 255)->nullable()->after('name');
        });
    }

    public function down()
    {
        Schema::table('raw_doctor', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
}
