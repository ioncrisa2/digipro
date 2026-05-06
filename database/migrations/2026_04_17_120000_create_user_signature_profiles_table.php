<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_signature_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('peruri_signit');
            $table->string('peruri_email')->nullable();
            $table->string('peruri_phone')->nullable();
            $table->string('nik', 32)->nullable();
            $table->unsignedBigInteger('reference_province_id')->nullable();
            $table->unsignedBigInteger('reference_city_id')->nullable();
            $table->string('registration_status')->nullable();
            $table->string('kyc_status')->nullable();
            $table->string('specimen_status')->nullable();
            $table->string('certificate_status')->nullable();
            $table->string('keyla_status')->nullable();
            $table->longText('keyla_qr_image')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('identity_payload')->nullable();
            $table->string('kyc_video_path')->nullable();
            $table->string('specimen_image_path')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_signature_profiles');
    }
};
