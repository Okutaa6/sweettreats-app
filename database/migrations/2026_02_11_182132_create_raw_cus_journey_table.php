<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRawCusJourneyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('raw_cus_journey', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('raw_customer_id', 255)->nullable()->index();
            $table->string('journey_label', 255)->nullable();
            $table->timestamp('created_at')->nullable(false);
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('raw_cus_journey');
    }
}