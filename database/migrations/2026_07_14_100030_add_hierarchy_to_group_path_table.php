<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarki organisasi dipasang di Role (group_path) — karena SETIAP user punya
 * group_id, sedangkan divisi (sys_checklist_division) hanya dimiliki sebagian
 * user operasional. Role = jabatan: satu jabatan menampung banyak user, dan
 * kini bisa punya atasan (parent) untuk struktur organisasi / pembatasan akses.
 *
 *   parent_id → adjacency list, self-FK ke group_path.id (null = puncak)
 *   level     → kedalaman terdenormalisasi (root=0)
 *
 * Kolom nullable/default → backward compatible, role lama tak berubah.
 */
class AddHierarchyToGroupPathTable extends Migration
{
    public function up()
    {
        Schema::table('group_path', function (Blueprint $table) {
            $table->string('parent_id', 255)->nullable()->after('nama')->index();
            $table->tinyInteger('level')->default(0)->after('parent_id');
        });
    }

    public function down()
    {
        Schema::table('group_path', function (Blueprint $table) {
            $table->dropColumn(['parent_id', 'level']);
        });
    }
}
