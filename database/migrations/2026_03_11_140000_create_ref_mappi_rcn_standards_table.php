<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_mappi_rcn_standards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guideline_set_id')->constrained('ref_guideline_sets');
            $table->smallInteger('year');
            $table->string('reference_region')->default('DKI Jakarta');
            $table->string('building_type')->nullable();
            $table->string('building_class')->nullable();
            $table->string('storey_pattern')->nullable();
            $table->bigInteger('rcn_value');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['guideline_set_id', 'year'], 'idx_mappi_rcn_guideline_year');
            $table->index(['building_type', 'building_class'], 'idx_mappi_rcn_type_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_mappi_rcn_standards');
    }
};
