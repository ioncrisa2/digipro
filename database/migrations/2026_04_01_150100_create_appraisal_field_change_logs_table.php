<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_field_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appraisal_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appraisal_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('revision_batch_id')->nullable()->constrained('appraisal_request_revision_batches')->nullOnDelete();
            $table->foreignId('revision_item_id')->nullable()->constrained('appraisal_request_revision_items')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_source', 30);
            $table->string('field_key', 100);
            $table->string('field_label', 150);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['appraisal_request_id', 'appraisal_asset_id'], 'afcl_request_asset_idx');
            $table->index(['change_source', 'field_key'], 'afcl_source_field_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_field_change_logs');
    }
};
