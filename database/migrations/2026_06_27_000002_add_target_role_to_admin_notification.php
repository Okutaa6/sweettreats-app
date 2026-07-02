<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom target_role ke admin_notification.
 *
 * Memungkinkan broadcast 1 row notifikasi yang otomatis terlihat oleh
 * semua user yang punya role tersebut (mis. 'Finance'), tanpa fan-out
 * insert per user. Diquery di AdminNotification::unreadForUser() yang
 * OR target_role = session('group_name').
 */
class AddTargetRoleToAdminNotification extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('admin_notification')) {
            // Pengaman: kalau di lingkungan dev/test tabel belum ada,
            // skip migrasi ini. Tabel admin_notification di-create di
            // luar Laravel migrations (raw SQL on prod).
            return;
        }
        if (! Schema::hasColumn('admin_notification', 'target_role')) {
            Schema::table('admin_notification', function (Blueprint $table) {
                $table->string('target_role', 60)->nullable()->after('raw_branch_id');
                $table->index(['is_read', 'target_role'], 'idx_notif_read_role');
            });
        }
    }

    public function down()
    {
        if (! Schema::hasTable('admin_notification')) return;
        if (Schema::hasColumn('admin_notification', 'target_role')) {
            Schema::table('admin_notification', function (Blueprint $table) {
                $table->dropIndex('idx_notif_read_role');
                $table->dropColumn('target_role');
            });
        }
    }
}
