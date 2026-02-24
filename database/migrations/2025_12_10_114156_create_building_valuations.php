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
        Schema::create('building_valuations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appraisal_asset_id')
                ->constrained('appraisal_assets')
                ->cascadeOnDelete();

            $table->foreignId('guideline_set_id')
                ->nullable()
                ->constrained('ref_guideline_sets');

            $table->string('building_name')->nullable();

            $table->decimal('gross_floor_area', 12, 2)->nullable();
            $table->unsignedSmallInteger('effective_age')->nullable();
            $table->unsignedSmallInteger('economic_life')->nullable();

            $table->foreignId('economic_life_ref_id')
                ->nullable()
                ->constrained('ref_building_economic_life');

            $table->decimal('depreciation_percent', 5, 2)->nullable();

            $table->foreignId('il_ref_id')
                ->nullable()
                ->constrained('ref_floor_index');
            $table->decimal('il_value', 8, 4)->nullable();

            $table->bigInteger('hard_cost_total')->nullable();
            $table->bigInteger('soft_cost_total')->nullable();
            $table->bigInteger('site_improvement_total')->nullable();

            $table->bigInteger('total_rcn')->nullable();
            $table->bigInteger('total_depreciated_value')->nullable();

            $table->json('calculation_json')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('building_valuations');
    }
};
