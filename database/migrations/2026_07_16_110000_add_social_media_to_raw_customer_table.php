<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sosial media pasien (repeatable): 1 pasien bisa punya beberapa akun.
 * Disimpan sebagai JSON array of {platform, username} — cukup untuk 2-5 akun,
 * tanpa perlu tabel anak. Nullable → tidak breaking untuk data lama.
 */
class AddSocialMediaToRawCustomerTable extends Migration
{
    public function up()
    {
        Schema::table('raw_customer', function (Blueprint $table) {
            $table->json('social_media')->nullable()->after('alamat');
        });
    }

    public function down()
    {
        Schema::table('raw_customer', function (Blueprint $table) {
            $table->dropColumn('social_media');
        });
    }
}
