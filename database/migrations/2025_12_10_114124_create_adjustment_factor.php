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
        Schema::create('adjustment_factors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->enum('scope', ['land', 'building', 'both'])->default('land');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('land_adjustments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appraisal_asset_comparable_id')
                ->constrained('appraisal_assets_comparables')
                ->cascadeOnDelete();

            $table->foreignId('factor_id')
                ->constrained('adjustment_factors');

            $table->string('subject_value')->nullable();
            $table->string('comparable_value')->nullable();

            $table->decimal('adjustment_percent', 8, 4)->nullable();
            $table->bigInteger('adjustment_amount')->nullable();

            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('land_adjustments');
        Schema::dropIfExists('adjustment_factors');
    }
};
