<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lookup divisi karyawan operasional klinik (Front Office, General
 * Services, Assistant Dentist). Dipakai sebagai grouping checklist
 * akreditasi: tiap divisi punya katalog kategori + task sendiri,
 * dan karyawan dipetakan ke 1 divisi aktif via sys_user_position.
 *
 * Pakai PK string (kode divisi: FO/GS/ASDENT) supaya:
 *  - readable di query (`WHERE division_id='FO'`)
 *  - tidak butuh extra join untuk display singkat
 *  - konsisten dgn convention string PK lain di sistem
 */
class CreateSysChecklistDivisionTable extends Migration
{
    public function up()
    {
        Schema::create('sys_checklist_division', function (Blueprint $table) {
            $table->string('id', 32)->primary();       // 'FO' / 'GS' / 'ASDENT'
            $table->string('name', 64);                 // display: 'Front Office'
            $table->string('description', 255)->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->tinyInteger('is_active')->default(1)->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_checklist_division');
    }
}
