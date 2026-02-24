<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('appraisal_assets', 'estimated_value_low')) {
                $table->bigInteger('estimated_value_low')->nullable()->after('market_value_final');
            }

            if (! Schema::hasColumn('appraisal_assets', 'estimated_value_high')) {
                $table->bigInteger('estimated_value_high')->nullable()->after('estimated_value_low');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_assets', function (Blueprint $table) {
            if (Schema::hasColumn('appraisal_assets', 'estimated_value_high')) {
                $table->dropColumn('estimated_value_high');
            }

            if (Schema::hasColumn('appraisal_assets', 'estimated_value_low')) {
                $table->dropColumn('estimated_value_low');
            }
        });
    }
};
