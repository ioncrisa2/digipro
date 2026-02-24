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
        // 1. Umur ekonomis bangunan
        Schema::create('ref_building_economic_life', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guideline_item_id')->constrained('ref_guideline_sets');
            $table->smallInteger('year');
            $table->string('category');
            $table->string('sub_category')->nullable();
            $table->string('building_type')->nullable();
            $table->string('building_class')->nullable();

            $table->unsignedSmallInteger('storey_min')->nullable();
            $table->unsignedSmallInteger('storey_max')->nullable();

            $table->unsignedSmallInteger('economic_life');
            $table->timestamps();
        });

        // 2. Indeks Lantai (IL)
        Schema::create('ref_floor_index', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guideline_set_id')->constrained('ref_guideline_sets');
            $table->smallInteger('year');

            $table->string('building_class');
            $table->unsignedSmallInteger('floor_count');
            $table->decimal('il_value', 8, 4);

            $table->timestamps();
        });

        // 3. BTB / Elemen biaya konstruksi
        Schema::create('ref_cost_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guideline_set_id')->constrained('ref_guideline_sets');
            $table->smallInteger('year');
            $table->string('base_region'); // "DKI Jakarta"

            $table->string('group');
            $table->string('element_code');
            $table->string('element_name');

            $table->string('building_type')->nullable();
            $table->string('building_class')->nullable();
            $table->string('storey_pattern')->nullable();

            $table->string('unit')->default('m2');
            $table->bigInteger('unit_cost');

            $table->json('spec_json')->nullable();
            $table->timestamps();
        });

        // 4. IKK
        Schema::create('ref_construction_cost_index', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guideline_set_id')->constrained('ref_guideline_sets');
            $table->smallInteger('year');

            $table->string('region_code');
            $table->string('region_name');
            $table->decimal('ikk_value', 8, 4);

            $table->timestamps();
        });

        // 5. Mapping peruntukan ↔ MAPPI
        Schema::create('ref_usage_to_mappi_group', function (Blueprint $table) {
            $table->id();

            $table->string('peruntukan_enum');         // dari enum internal
            $table->string('mappi_building_type');
            $table->string('mappi_building_class')->nullable();
            $table->string('default_storey_group')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ref_usage_to_mappi_group');
        Schema::dropIfExists('ref_construction_cost_index');
        Schema::dropIfExists('ref_cost_elements');
        Schema::dropIfExists('ref_floor_index');
        Schema::dropIfExists('ref_building_economic_life');
    }
};
