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
        Schema::create('appraisal_asset_files', function (Blueprint $table) {
           $table->id();

            $table->foreignId('appraisal_asset_id')
                ->constrained('appraisal_assets')
                ->cascadeOnDelete();

            $table->string('type', 50); // doc_pbb, doc_imb, doc_old_report, doc_certs, photos
            $table->string('path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();

            $table->index(['appraisal_asset_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_asset_files');
    }
};
