<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_request_revision_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_batch_id');
            $table->foreignId('appraisal_asset_id')->nullable();
            $table->string('item_type', 30);
            $table->string('requested_file_type', 50);
            $table->string('status', 30)->default('pending');
            $table->text('issue_note')->nullable();
            $table->foreignId('original_request_file_id')->nullable();
            $table->foreignId('original_asset_file_id')->nullable();
            $table->foreignId('replacement_request_file_id')->nullable();
            $table->foreignId('replacement_asset_file_id')->nullable();
            $table->timestamps();

            $table->foreign('revision_batch_id', 'arri_batch_fk')
                ->references('id')
                ->on('appraisal_request_revision_batches')
                ->cascadeOnDelete();
            $table->foreign('appraisal_asset_id', 'arri_asset_fk')
                ->references('id')
                ->on('appraisal_assets')
                ->nullOnDelete();
            $table->foreign('original_request_file_id', 'arri_orig_req_file_fk')
                ->references('id')
                ->on('appraisal_request_files')
                ->nullOnDelete();
            $table->foreign('original_asset_file_id', 'arri_orig_asset_file_fk')
                ->references('id')
                ->on('appraisal_asset_files')
                ->nullOnDelete();
            $table->foreign('replacement_request_file_id', 'arri_repl_req_file_fk')
                ->references('id')
                ->on('appraisal_request_files')
                ->nullOnDelete();
            $table->foreign('replacement_asset_file_id', 'arri_repl_asset_file_fk')
                ->references('id')
                ->on('appraisal_asset_files')
                ->nullOnDelete();

            $table->index(['revision_batch_id', 'status'], 'arri_batch_status_idx');
            $table->index(['item_type', 'requested_file_type'], 'arri_type_reqtype_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_request_revision_items');
    }
};
