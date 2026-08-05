<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rolling cabang: karyawan sewaktu-waktu ditugaskan ke cabang lain untuk durasi
 * tertentu, lalu OTOMATIS balik ke cabang asal setelah periodenya lewat.
 *
 *   kind = 'home'    → posisi utama/asal (open-ended, effective_to NULL)
 *   kind = 'rolling' → penugasan sementara (WAJIB bounded: effective_from..effective_to)
 *
 * Rolling adalah row TERPISAH — tak pernah mengubah row home, jadi histori cabang
 * asal aman & revert-nya otomatis (scopeActiveOn mengutamakan rolling di window-nya,
 * lalu row-nya gugur sendiri dari filter tanggal begitu effective_to lewat).
 *
 * Data lama = home. Lihat rencana rolling + syncFromUser()/pending() yang kini
 * dibatasi hanya mengurus row home.
 */
class AddKindToSysUserPositionTable extends Migration
{
    public function up()
    {
        Schema::table('sys_user_position', function (Blueprint $table) {
            $table->string('kind', 16)->default('home')->after('is_primary')->index();
            $table->string('note', 500)->nullable()->after('confirmed_by');
        });

        // Backfill: semua posisi lama adalah posisi asal (home).
        DB::table('sys_user_position')
            ->whereNull('kind')
            ->update(['kind' => 'home']);
    }

    public function down()
    {
        Schema::table('sys_user_position', function (Blueprint $table) {
            $table->dropColumn(['kind', 'note']);
        });
    }
}
