<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appraisal_assets_comparables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appraisal_asset_id')
                ->constrained('appraisal_assets')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('external_id');
            $table->string('external_source')->default('pembanding_service');

            $table->json('snapshot_json')->nullable();

        $table->float('score')->nullable();
            $table->decimal('weight', 8, 4)->nullable();

            $table->decimal('total_adjustment_percent', 8, 4)->nullable();
            $table->bigInteger('adjusted_unit_value')->nullable();
            $table->bigInteger('indication_value')->nullable();

            $table->unsignedSmallInteger('rank')->nullable();

            $table->bigInteger('raw_price')->nullable();          // dari harga
            $table->decimal('raw_land_area', 12, 2)->nullable();  // dari luas_tanah
            $table->decimal('raw_building_area', 12, 2)->nullable(); // dari luas_bangunan
            $table->decimal('raw_unit_price_land', 18, 2)->nullable(); // harga/m2 tanah
            $table->string('raw_peruntukan')->nullable();         // dari peruntukan
            $table->date('raw_data_date')->nullable();            // dari tanggal_data

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_assets_comparables');
    }
};
