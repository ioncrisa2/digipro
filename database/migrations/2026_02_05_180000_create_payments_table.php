<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_request_id')
                ->constrained('appraisal_requests')
                ->cascadeOnDelete();

            $table->bigInteger('amount')->default(0);
            $table->enum('method', ['manual', 'gateway'])->default('manual');
            $table->string('gateway')->nullable();
            $table->string('external_payment_id', 120)->nullable();

            $table->enum('status', ['pending', 'paid', 'failed', 'rejected', 'refunded'])
                ->default('pending');

            $table->timestamp('paid_at')->nullable();

            $table->string('proof_file_path', 500)->nullable();
            $table->string('proof_original_name', 255)->nullable();
            $table->string('proof_mime', 120)->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->enum('proof_type', ['upload', 'gateway_id'])->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('method');
            $table->index('gateway');
            $table->index('external_payment_id');
            $table->index(['appraisal_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};