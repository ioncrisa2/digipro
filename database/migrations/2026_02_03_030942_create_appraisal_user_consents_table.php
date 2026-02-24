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
         Schema::create('appraisal_user_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('consent_document_id')->constrained('consent_document')->cascadeOnDelete();
            $table->string('code')->default('appraisal_request_consent');
            $table->string('version', 32);
            $table->string('hash', 64);
            $table->timestamp('accepted_at');
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'version']);
            $table->index(['user_id', 'version', 'hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_user_consents');
    }
};
