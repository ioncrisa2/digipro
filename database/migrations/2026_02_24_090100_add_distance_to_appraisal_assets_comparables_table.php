<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_assets_comparables', function (Blueprint $table) {
            $table->decimal('distance_meters', 12, 2)->nullable()->after('weight');
            $table->unique(['appraisal_asset_id', 'external_source', 'external_id'], 'comparables_unique_source');
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_assets_comparables', function (Blueprint $table) {
            $table->dropUnique('comparables_unique_source');
            $table->dropColumn('distance_meters');
        });
    }
};
