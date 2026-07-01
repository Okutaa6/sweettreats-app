<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah field cadence ke kategori: 'daily' (default, perilaku lama) atau
 * 'weekly' (kategori dipakai untuk Form Checklist Mingguan, task di-tag
 * dengan day_of_week dan submission 1 row per ISO week per user).
 *
 * Existing data semua jadi 'daily' otomatis via default — tidak breaking.
 */
class AddCadenceToSysChecklistCategoryTable extends Migration
{
    public function up()
    {
        Schema::table('sys_checklist_category', function (Blueprint $table) {
            $table->string('cadence', 16)->default('daily')->after('description')->index();
        });
    }

    public function down()
    {
        Schema::table('sys_checklist_category', function (Blueprint $table) {
            $table->dropIndex(['cadence']);
            $table->dropColumn('cadence');
        });
    }
}
