<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('billing_recipient_name')->nullable()->after('company_name');
            $table->string('billing_province_id', 2)->nullable()->after('billing_address');
            $table->string('billing_regency_id', 4)->nullable()->after('billing_province_id');
            $table->string('billing_district_id', 7)->nullable()->after('billing_regency_id');
            $table->string('billing_village_id', 10)->nullable()->after('billing_district_id');
            $table->string('billing_postal_code', 10)->nullable()->after('billing_village_id');
            $table->text('billing_address_detail')->nullable()->after('billing_postal_code');
        });

        DB::table('users')
            ->whereNull('billing_recipient_name')
            ->whereNotNull('company_name')
            ->update([
                'billing_recipient_name' => DB::raw('company_name'),
            ]);

        DB::table('users')
            ->whereNull('billing_address_detail')
            ->whereNotNull('billing_address')
            ->update([
                'billing_address_detail' => DB::raw('billing_address'),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_recipient_name',
                'billing_province_id',
                'billing_regency_id',
                'billing_district_id',
                'billing_village_id',
                'billing_postal_code',
                'billing_address_detail',
            ]);
        });
    }
};
