<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_request_cancellations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appraisal_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status_before_request', 50);
            $table->string('phone_snapshot', 30);
            $table->string('whatsapp_snapshot', 30)->nullable();
            $table->text('reason');
            $table->string('review_status', 30)->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['review_status', 'created_at'], 'arc_review_created_idx');
            $table->index(['appraisal_request_id', 'review_status'], 'arc_req_review_idx');
            $table->index('status_before_request', 'arc_status_before_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_request_cancellations');
    }
};
