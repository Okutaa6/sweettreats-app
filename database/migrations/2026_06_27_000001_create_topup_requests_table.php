<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan top-up petty cash dari Sales cabang ke Finance.
 *
 * Flow:
 *   pending  → Sales submit; notifikasi auto-fan ke Finance via target_role.
 *   approved → Finance approve; system create row petty_cash_transactions
 *              (is_topup=1) di periode aktif cabang & stamp executed_trx_id.
 *   rejected → Finance reject + alasan; tidak ada perubahan kas.
 *
 * Lintas periode: kalau request 30 Juni & approve 2 Juli → transaksi masuk
 * periode Juli (resolve ulang via PettyCashPeriod::ensurePeriod() saat approve).
 */
class CreateTopupRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('topup_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('raw_branch_id', 255)->index();
            $table->string('requested_by', 36);
            $table->unsignedBigInteger('fund_source_id');
            $table->decimal('amount_requested', 15, 2);
            $table->decimal('amount_approved', 15, 2)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('requested_at')->useCurrent();
            $table->string('reviewed_by', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->unsignedBigInteger('executed_trx_id')->nullable();
            $table->string('approval_note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['status', 'raw_branch_id'], 'idx_topup_status_branch');
            $table->index('requested_by',  'idx_topup_requester');
            $table->index('reviewed_by',   'idx_topup_reviewer');
            $table->index('executed_trx_id', 'idx_topup_executed_trx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('topup_requests');
    }
}
