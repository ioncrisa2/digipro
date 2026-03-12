<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_assets_comparables', function (Blueprint $table): void {
            $table->decimal('material_quality_adjustment', 8, 4)
                ->nullable()
                ->after('assumed_discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_assets_comparables', function (Blueprint $table): void {
            $table->dropColumn('material_quality_adjustment');
        });
    }
};
