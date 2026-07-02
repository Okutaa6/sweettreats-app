<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toggle per-cabang untuk modul "Biaya Transportasi". Default true supaya
 * tidak ada perubahan behavior untuk cabang existing. Super-admin bisa
 * matikan via halaman Kelola Wallet — sekali OFF, tab "Biaya Transportasi"
 * hilang di pettycash dan akses URL `?tab=transport` di-redirect ke
 * `?tab=petty_cash`.
 */
class AddAllowTransportToRawBranchTable extends Migration
{
    public function up(): void
    {
        Schema::table('raw_branch', function (Blueprint $table) {
            $table->boolean('allow_transport')->default(true)->after('transport_cost_default_float');
        });
    }

    public function down(): void
    {
        Schema::table('raw_branch', function (Blueprint $table) {
            $table->dropColumn('allow_transport');
        });
    }
}
