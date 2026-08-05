<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atasan KEDUA (parent_id_2) untuk supervisi checklist.
 *
 * `parent_id` tetap atasan UTAMA & satu-satunya penentu struktur pohon (level,
 * drag-drop, deteksi siklus, akar bagan). `parent_id_2` (opsional) menambah SATU
 * atasan tambahan yang dipakai KHUSUS untuk supervisi checklist — mis. GS di
 * bawah Supervisor SEKALIGUS Coordinator Dentist, agar keduanya bisa saling
 * bantu me-review checklist GS.
 *
 * Kolom nullable → backward compatible, role lama tak berubah.
 */
class AddParentId2ToGroupPathTable extends Migration
{
    public function up()
    {
        Schema::table('group_path', function (Blueprint $table) {
            $table->string('parent_id_2', 255)->nullable()->after('parent_id')->index();
        });
    }

    public function down()
    {
        Schema::table('group_path', function (Blueprint $table) {
            $table->dropColumn('parent_id_2');
        });
    }
}
