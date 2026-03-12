<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_assets_comparables', function (Blueprint $table): void {
            $table->decimal('assumed_discount_percent', 5, 2)
                ->nullable()
                ->after('raw_data_date');
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_assets_comparables', function (Blueprint $table): void {
            $table->dropColumn('assumed_discount_percent');
        });
    }
};
