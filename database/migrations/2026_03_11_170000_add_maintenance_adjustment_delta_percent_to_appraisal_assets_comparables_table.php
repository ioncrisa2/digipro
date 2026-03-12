<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_assets_comparables', function (Blueprint $table): void {
            $table->decimal('maintenance_adjustment_delta_percent', 6, 2)
                ->nullable()
                ->after('material_quality_adjustment');
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_assets_comparables', function (Blueprint $table): void {
            $table->dropColumn('maintenance_adjustment_delta_percent');
        });
    }
};
