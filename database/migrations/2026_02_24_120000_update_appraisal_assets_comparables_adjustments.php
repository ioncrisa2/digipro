<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_assets_comparables', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('external_source');
            $table->boolean('is_selected')->default(false)->after('image_url');
            $table->unsignedSmallInteger('manual_rank')->nullable()->after('is_selected');

            $table->decimal('auto_adjust_percent', 8, 4)->nullable()->after('score');

            $table->decimal('total_adjustment_percent_low', 8, 4)->nullable()->after('total_adjustment_percent');
            $table->decimal('total_adjustment_percent_high', 8, 4)->nullable()->after('total_adjustment_percent_low');
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_assets_comparables', function (Blueprint $table) {
            $table->dropColumn([
                'image_url',
                'is_selected',
                'manual_rank',
                'auto_adjust_percent',
                'total_adjustment_percent_low',
                'total_adjustment_percent_high',
            ]);
        });
    }
};
