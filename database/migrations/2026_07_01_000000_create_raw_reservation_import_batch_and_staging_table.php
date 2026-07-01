<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging area untuk impor jadwal reservasi dari CSV/Sheets cabang.
 * Pasien dari sheets di-parse ke staging dulu — CS pilah/edit/promote
 * per baris ke raw_cus_reservation supaya cegah konflik slot dokter.
 */
class CreateRawReservationImportBatchAndStagingTable extends Migration
{
    public function up()
    {
        Schema::create('raw_reservation_import_batch', function (Blueprint $t) {
            $t->string('id', 36)->primary();
            $t->string('raw_branch_id', 36)->index();
            $t->string('uploaded_by', 36)->nullable();
            $t->string('file_name', 255);
            $t->integer('total_rows')->default(0);
            $t->integer('parsed_rows')->default(0);
            $t->integer('skipped_rows')->default(0);
            $t->integer('promoted_rows')->default(0);
            $t->timestamp('uploaded_at')->useCurrent();
        });

        Schema::create('raw_cus_reservation_staging', function (Blueprint $t) {
            $t->string('id', 36)->primary();
            $t->string('batch_id', 36)->index();
            $t->string('raw_branch_id', 36)->index();
            $t->integer('source_row_index');

            // Parsed patient fields
            $t->string('patient_name', 255)->nullable();
            $t->string('patient_phone', 64)->nullable();
            $t->string('patient_email', 255)->nullable();
            $t->string('patient_nik', 32)->nullable();
            $t->string('patient_note', 500)->nullable();

            // Schedule
            $t->date('treatment_date')->nullable()->index();
            $t->time('start_hour')->nullable();

            // Doctor resolution
            $t->string('doctor_name_raw', 255)->nullable();
            $t->string('resolved_raw_doctor_id', 36)->nullable()->index();

            // Status mapping
            $t->string('status_raw', 32)->nullable();
            $t->string('mapped_status', 32)->nullable();

            // Treatment & money
            $t->text('tindakan_text')->nullable();
            $t->decimal('total_reservasi', 14, 2)->nullable();

            // Audit / origin
            $t->string('pic_name', 64)->nullable();
            $t->string('tanggal_reservasi_raw', 32)->nullable();
            $t->tinyInteger('is_waiting')->default(0);

            // State machine
            $t->enum('state', ['pending', 'promoted', 'skipped', 'error'])
                ->default('pending')
                ->index();
            $t->string('promoted_to_id', 36)->nullable();
            $t->json('parse_warnings')->nullable();
            $t->json('raw_row')->nullable();

            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->nullable();
            $t->string('updated_by', 36)->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('raw_cus_reservation_staging');
        Schema::dropIfExists('raw_reservation_import_batch');
    }
}
