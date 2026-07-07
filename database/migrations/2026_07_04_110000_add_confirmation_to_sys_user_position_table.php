<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flow baru: karyawan operasional memilih sendiri divisi + cabang dari halaman
 * my-checklist (usulan), lalu supervisor tinggal konfirmasi di halaman posisi.
 *
 *   confirmed_at NULL  → usulan (pending), belum aktif — belum bisa isi checklist
 *   confirmed_at terisi → sudah dikonfirmasi supervisor → aktif
 *
 * scopeActiveOn() sekarang mewajibkan confirmed_at NOT NULL. Data lama (yang
 * dulu di-assign langsung oleh supervisor) di-backfill sebagai sudah dikonfirmasi
 * supaya tidak tiba-tiba jadi pending.
 */
class AddConfirmationToSysUserPositionTable extends Migration
{
    public function up()
    {
        Schema::table('sys_user_position', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('effective_to');
            $table->string('confirmed_by', 255)->nullable()->after('confirmed_at');
        });

        // Backfill: semua posisi lama dianggap sudah dikonfirmasi.
        DB::table('sys_user_position')
            ->whereNull('confirmed_at')
            ->update(['confirmed_at' => DB::raw('COALESCE(created_at, NOW())')]);
    }

    public function down()
    {
        Schema::table('sys_user_position', function (Blueprint $table) {
            $table->dropColumn(['confirmed_at', 'confirmed_by']);
        });
    }
}
