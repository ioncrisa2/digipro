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
        Schema::table('user_signature_profiles', function (Blueprint $table): void {
            $table->boolean('is_wna')->default(false)->after('nik');
            $table->string('gender', 1)->nullable()->after('reference_city_id');
            $table->string('place_of_birth')->nullable()->after('gender');
            $table->date('date_of_birth')->nullable()->after('place_of_birth');
            $table->string('ktp_photo_path')->nullable()->after('identity_payload');
            $table->string('npwp', 40)->nullable()->after('ktp_photo_path');
            $table->string('npwp_photo_path')->nullable()->after('npwp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_signature_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'is_wna',
                'gender',
                'place_of_birth',
                'date_of_birth',
                'ktp_photo_path',
                'npwp',
                'npwp_photo_path',
            ]);
        });
    }
};
