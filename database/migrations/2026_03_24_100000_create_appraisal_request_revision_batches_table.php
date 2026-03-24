<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_request_revision_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_request_id');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('submitted_by')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->string('status', 30)->default('open');
            $table->text('admin_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('appraisal_request_id', 'arrb_request_fk')
                ->references('id')
                ->on('appraisal_requests')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'arrb_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('submitted_by', 'arrb_submitted_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('reviewed_by', 'arrb_reviewed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['appraisal_request_id', 'status'], 'arrb_request_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_request_revision_batches');
    }
};
