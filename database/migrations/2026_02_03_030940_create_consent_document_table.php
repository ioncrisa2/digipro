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
        Schema::create('consent_document', function (Blueprint $table) {
            $table->id();
             $table->string('code', 100)->index();          // appraisal_request_consent
            $table->string('version', 50)->index();        // 2026-02-03-v1.1
            $table->string('title', 200);

            // structured content for stable hashing + UI rendering
            $table->json('sections');                      // [{heading, lead, items[]}, ...]
            $table->string('checkbox_label', 255)->nullable();

            $table->char('hash', 64)->index();             // sha256(payload)
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consent_document');
    }
};
