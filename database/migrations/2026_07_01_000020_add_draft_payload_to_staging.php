<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `draft_payload` (JSON) ke raw_cus_reservation_staging.
 *
 * Tujuan: user bisa "Simpan Draft" reservasi dari Edit & Schedule drawer
 * (dgn slot picker + services lengkap) tanpa langsung di-promote ke
 * raw_cus_reservation. Payload = full form POST disimpan apa adanya.
 * Bulk-promote nanti replay payload ini untuk bikin reservasi resmi
 * (termasuk services + end_hour yg tidak ada di staging columns).
 */
class AddDraftPayloadToStaging extends Migration
{
    public function up()
    {
        Schema::table('raw_cus_reservation_staging', function (Blueprint $t) {
            $t->json('draft_payload')->nullable()->after('raw_row');
        });
    }

    public function down()
    {
        Schema::table('raw_cus_reservation_staging', function (Blueprint $t) {
            $t->dropColumn('draft_payload');
        });
    }
}
