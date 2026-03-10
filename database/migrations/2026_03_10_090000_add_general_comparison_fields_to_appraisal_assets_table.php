<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_assets', function (Blueprint $table) {
            $table->string('title_document')->nullable()->after('peruntukan');
            $table->string('land_shape')->nullable()->after('title_document');
            $table->string('land_position')->nullable()->after('land_shape');
            $table->string('land_condition')->nullable()->after('land_position');
            $table->string('topography')->nullable()->after('land_condition');
            $table->decimal('frontage_width', 12, 2)->nullable()->after('topography');
            $table->decimal('access_road_width', 12, 2)->nullable()->after('frontage_width');
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_assets', function (Blueprint $table) {
            $table->dropColumn([
                'title_document',
                'land_shape',
                'land_position',
                'land_condition',
                'topography',
                'frontage_width',
                'access_road_width',
            ]);
        });
    }
};
