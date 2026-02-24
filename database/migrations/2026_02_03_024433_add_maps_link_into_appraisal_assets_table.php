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
         Schema::table('appraisal_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('appraisal_assets', 'maps_link')) {
                $table->text('maps_link')->nullable()->after('address');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('appraisal_assets', function (Blueprint $table) {
            if (Schema::hasColumn('appraisal_assets', 'maps_link')) {
                $table->dropColumn('maps_link');
            }
        });
    }
};
