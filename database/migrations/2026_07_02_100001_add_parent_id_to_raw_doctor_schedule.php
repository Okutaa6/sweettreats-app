<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ikatan child istirahat ke parent jam kerja. Break rows (type='break')
 * PUNYA parent_id → id row jam kerja (type='work') yang menaunginya.
 * Work rows sendiri parent_id = NULL.
 *
 * Kenapa parent_id: user ingin edit jam istirahat lewat modal jam kerja
 * yang sama (repeater di dalam), dan istirahat "menempel" ke shift
 * spesifik. Kalau shift work dihapus → cascade hapus semua break child.
 */
class AddParentIdToRawDoctorSchedule extends Migration
{
    public function up()
    {
        Schema::table('raw_doctor_schedule', function (Blueprint $t) {
            $t->string('parent_id', 255)->nullable()->after('type')->index();
        });
    }

    public function down()
    {
        Schema::table('raw_doctor_schedule', function (Blueprint $t) {
            $t->dropIndex(['parent_id']);
            $t->dropColumn('parent_id');
        });
    }
}
