<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_request_revision_items', function (Blueprint $table): void {
            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('replacement_asset_file_id');
            $table->timestamp('reviewed_at')
                ->nullable()
                ->after('reviewed_by');
            $table->text('review_note')
                ->nullable()
                ->after('reviewed_at');

            $table->foreign('reviewed_by', 'arr_items_reviewed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_request_revision_items', function (Blueprint $table): void {
            $table->dropForeign('arr_items_reviewed_by_fk');
            $table->dropColumn([
                'reviewed_by',
                'reviewed_at',
                'review_note',
            ]);
        });
    }
};
