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
        Schema::create('appraisal_assets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appraisal_request_id')
                ->constrained('appraisal_requests')
                ->cascadeOnDelete();

            $table->string('asset_code')->nullable();
            $table->enum('asset_type', ['tanah', 'tanah_bangunan']);

            $table->string('peruntukan')->nullable();

            $table->string('province_id', 2)->nullable();
            $table->string('regency_id', 4)->nullable();
            $table->string('district_id', 7)->nullable();
            $table->string('village_id', 10)->nullable();

            $table->text('address')->nullable();

            $table->decimal('coordinates_lat', 10, 7)->nullable();
            $table->decimal('coordinates_lng', 10, 7)->nullable();

            $table->decimal('land_area', 12, 2)->nullable();
            $table->decimal('building_area', 12, 2)->nullable();

            $table->unsignedSmallInteger('building_floors')->nullable();
            $table->unsignedSmallInteger('build_year')->nullable();
            $table->unsignedSmallInteger('renovation_year')->nullable();

            $table->foreignId('ikk_ref_id')
                ->nullable()
                ->constrained('ref_construction_cost_index');
            $table->decimal('ikk_value_used', 8, 4)->nullable();

            $table->bigInteger('land_value_final')->nullable();
            $table->bigInteger('building_value_final')->nullable();
            $table->bigInteger('market_value_final')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_assets');
    }
};
