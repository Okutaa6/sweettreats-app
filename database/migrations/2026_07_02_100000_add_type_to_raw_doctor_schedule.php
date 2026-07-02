<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setiap baris jadwal punya tipe: 'work' (jam kerja bookable) atau 'break'
 * (jam istirahat — jam ini di-EXCLUDE dari slot bookable). Break jadi entry
 * mandiri supaya admin bisa tambah berkali-kali per hari (mis. break pagi
 * 10:00-10:15 + makan siang 12:00-13:00), dan hitung slot di trait tinggal
 * "subtract break dari work" per hari+cabang+dokter.
 *
 * Default 'work' supaya legacy row otomatis behave sama seperti sebelum.
 */
class AddTypeToRawDoctorSchedule extends Migration
{
    public function up()
    {
        Schema::table('raw_doctor_schedule', function (Blueprint $t) {
            $t->string('type', 8)->default('work')->after('end_hour')->index();
        });
    }

    public function down()
    {
        Schema::table('raw_doctor_schedule', function (Blueprint $t) {
            $t->dropIndex(['type']);
            $t->dropColumn('type');
        });
    }
}
