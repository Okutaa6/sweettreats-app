<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field bergaya "Pengajuan" untuk pengajuan top-up petty cash.
 *
 *   requester_name → nama pemohon (default nama user login, boleh diedit di form).
 *   needed_by      → "butuh kapan": tanggal dana diharapkan cair (nullable).
 *   is_urgent      → flag DARURAT untuk prioritas antrian review Finance.
 *
 * Modal "Ajukan Top Up" direstyle mengikuti form Pengajuan ROP; tiga data ini
 * ikut disubmit Sales dan ditampilkan ke Finance sebagai konteks approval.
 */
class AddPengajuanFieldsToTopupRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('topup_requests', function (Blueprint $table) {
            $table->string('requester_name', 255)->nullable()->after('requested_by');
            $table->date('needed_by')->nullable()->after('description');
            $table->boolean('is_urgent')->default(false)->after('needed_by');
        });
    }

    public function down()
    {
        Schema::table('topup_requests', function (Blueprint $table) {
            $table->dropColumn(['requester_name', 'needed_by', 'is_urgent']);
        });
    }
}
