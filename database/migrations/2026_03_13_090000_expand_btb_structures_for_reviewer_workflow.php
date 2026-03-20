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
        Schema::table('building_valuations', function (Blueprint $table): void {
            $table->string('worksheet_template', 100)->nullable()->after('building_name');
            $table->string('building_type', 120)->nullable()->after('worksheet_template');
            $table->string('building_class', 120)->nullable()->after('building_type');
            $table->unsignedSmallInteger('floor_count')->nullable()->after('building_class');
            $table->unsignedSmallInteger('valuation_year')->nullable()->after('floor_count');
            $table->string('ikk_region_code', 32)->nullable()->after('valuation_year');
            $table->string('ikk_region_label', 150)->nullable()->after('ikk_region_code');
            $table->decimal('ikk_value', 8, 4)->nullable()->after('ikk_region_label');
            $table->bigInteger('base_rcn_unit_cost')->nullable()->after('ikk_value');
            $table->decimal('material_quality_adjustment', 8, 4)->nullable()->after('base_rcn_unit_cost');
            $table->decimal('maintenance_adjustment_factor', 8, 4)->nullable()->after('material_quality_adjustment');
            $table->decimal('final_adjustment_factor', 8, 4)->nullable()->after('maintenance_adjustment_factor');
            $table->bigInteger('residual_land_value')->nullable()->after('total_depreciated_value');
            $table->bigInteger('residual_land_value_per_sqm')->nullable()->after('residual_land_value');
        });

        Schema::table('building_cost_items', function (Blueprint $table): void {
            $table->unsignedInteger('row_order')->nullable()->after('cost_element_id');
            $table->string('section_name', 120)->nullable()->after('row_order');
            $table->boolean('is_subtotal')->default(false)->after('section_name');
            $table->string('model_material_spec')->nullable()->after('element_name');
            $table->string('subject_material_spec')->nullable()->after('model_material_spec');
            $table->decimal('model_volume_percent', 8, 4)->nullable()->after('subject_material_spec');
            $table->decimal('subject_volume_percent', 8, 4)->nullable()->after('model_volume_percent');
            $table->decimal('other_adjustment_factor', 8, 4)->nullable()->after('subject_volume_percent');
            $table->bigInteger('direct_cost_result')->nullable()->after('line_total');
            $table->string('source_sheet', 120)->nullable()->after('direct_cost_result');
            $table->string('source_cell', 20)->nullable()->after('source_sheet');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('building_cost_items', function (Blueprint $table): void {
            $table->dropColumn([
                'row_order',
                'section_name',
                'is_subtotal',
                'model_material_spec',
                'subject_material_spec',
                'model_volume_percent',
                'subject_volume_percent',
                'other_adjustment_factor',
                'direct_cost_result',
                'source_sheet',
                'source_cell',
            ]);
        });

        Schema::table('building_valuations', function (Blueprint $table): void {
            $table->dropColumn([
                'worksheet_template',
                'building_type',
                'building_class',
                'floor_count',
                'valuation_year',
                'ikk_region_code',
                'ikk_region_label',
                'ikk_value',
                'base_rcn_unit_cost',
                'material_quality_adjustment',
                'maintenance_adjustment_factor',
                'final_adjustment_factor',
                'residual_land_value',
                'residual_land_value_per_sqm',
            ]);
        });
    }
};
