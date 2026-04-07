<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone_number', 30)->nullable()->after('email');
            $table->string('whatsapp_number', 30)->nullable()->after('phone_number');
            $table->text('address')->nullable()->after('whatsapp_number');
            $table->string('company_name')->nullable()->after('address');
            $table->text('billing_address')->nullable()->after('company_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'phone_number',
                'whatsapp_number',
                'address',
                'company_name',
                'billing_address',
            ]);
        });
    }
};
