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
        Schema::create('appraisal_request_files', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appraisal_request_id')
                ->constrained('appraisal_requests')
                ->cascadeOnDelete();

            $table->string('type', 50); // npwp, representative, permission
            $table->string('path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();

            $table->index(['appraisal_request_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_request_files');
    }
};
