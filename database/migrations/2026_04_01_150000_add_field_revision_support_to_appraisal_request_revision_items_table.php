<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_request_revision_items', function (Blueprint $table): void {
            $table->string('requested_field_key', 100)->nullable()->after('requested_file_type');
            $table->json('original_value')->nullable()->after('requested_field_key');
            $table->json('replacement_value')->nullable()->after('replacement_asset_file_id');
            $table->index(['item_type', 'requested_field_key'], 'arri_type_field_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_request_revision_items', function (Blueprint $table): void {
            $table->dropIndex('arri_type_field_idx');
            $table->dropColumn([
                'requested_field_key',
                'original_value',
                'replacement_value',
            ]);
        });
    }
};
