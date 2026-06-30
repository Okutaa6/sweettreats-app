<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail untuk modul checklist akreditasi.
 *
 * Di-tulis dari controller setiap state transition penting (create kategori,
 * update task, submit, approve, reject, reopen, lock). Tujuan utama:
 *  - audit akreditasi (siapa edit apa, kapan)
 *  - traceability bug / dispute
 *
 * before_json / after_json menyimpan snapshot field yg relevan (bukan
 * seluruh row — hemat storage). Diisi JSON kecil 2-5 key biasanya.
 */
class CreateSysChecklistAuditLogTable extends Migration
{
    public function up()
    {
        Schema::create('sys_checklist_audit_log', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('actor_user_id', 255)->nullable()->index();
            $table->string('raw_branch_id', 255)->nullable()->index();
            $table->string('target_type', 32)->index();       // 'submission' | 'task' | 'category'
            $table->string('target_id', 255)->index();
            $table->string('action', 32)->index();            // create | update | delete | submit | approve | reject | reopen | lock
            $table->text('before_json')->nullable();
            $table->text('after_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_checklist_audit_log');
    }
}
