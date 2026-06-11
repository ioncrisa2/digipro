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
        Schema::table('user_signature_profiles', function (Blueprint $table) {
            $table->string('reference_province_id', 32)->nullable()->change();
            $table->string('reference_city_id', 32)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_signature_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_province_id')->nullable()->change();
            $table->unsignedBigInteger('reference_city_id')->nullable()->change();
        });
    }
};
