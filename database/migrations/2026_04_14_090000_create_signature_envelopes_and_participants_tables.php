<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_envelopes', function (Blueprint $table): void {
            $table->id();

            $table->string('subject_type', 160);
            $table->unsignedBigInteger('subject_id');
            $table->string('document_type', 40); // contract, report, ...
            $table->string('provider', 40); // peruri_signit, ...
            $table->string('model', 20); // tier, parallel, ...

            $table->string('external_envelope_id', 120)->nullable(); // e.g. orderIdTier
            $table->string('uploader_email', 120)->nullable();
            $table->string('status', 30)->default('draft'); // draft|awaiting_customer|awaiting_internal|completed|failed

            $table->string('document_hash', 100)->nullable();
            $table->string('original_pdf_path', 500)->nullable();
            $table->string('signed_pdf_path', 500)->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'se_subject_idx');
            $table->index(['provider', 'document_type', 'status'], 'se_provider_doc_status_idx');
            $table->unique(['subject_type', 'subject_id', 'document_type', 'provider'], 'se_subject_doc_provider_unique');
        });

        Schema::create('signature_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('signature_envelope_id')
                ->constrained('signature_envelopes')
                ->cascadeOnDelete();

            $table->string('role', 30); // customer|public_appraiser|reviewer|...
            $table->unsignedTinyInteger('sequence')->nullable(); // 1,2,... for tier
            $table->string('email', 120);
            $table->string('name', 120)->nullable();
            $table->string('external_order_id', 120)->nullable(); // orderId per signer
            $table->string('status', 20)->default('pending'); // pending|signed|failed|expired
            $table->dateTime('signed_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['signature_envelope_id', 'status'], 'sp_envelope_status_idx');
            $table->unique(['signature_envelope_id', 'role'], 'sp_envelope_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_participants');
        Schema::dropIfExists('signature_envelopes');
    }
};

