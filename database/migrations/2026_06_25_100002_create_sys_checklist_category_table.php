<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori pengelompok task per divisi. Contoh untuk Asdent: "Persiapan
 * Ruang & Alat", "Saat Tindakan", "Sterilisasi", dll.
 *
 * raw_branch_id NULL = master katalog berlaku global; non-null = override
 * khusus cabang tertentu (Fase 2). Untuk MVP biarkan NULL — supervisor
 * atur satu katalog yang berlaku semua cabang.
 *
 * Soft delete (deleted_at): kategori yg punya history submission tidak
 * boleh hard-delete supaya snapshot di item lama tetap aman. Controller
 * cek referensi sebelum destroy.
 */
class CreateSysChecklistCategoryTable extends Migration
{
    public function up()
    {
        Schema::create('sys_checklist_category', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('division_id', 32)->index();        // FK sys_checklist_division
            $table->string('raw_branch_id', 255)->nullable()->index();
            $table->string('name', 128);
            $table->string('description', 255)->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->tinyInteger('is_active')->default(1)->index();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_checklist_category');
    }
}
