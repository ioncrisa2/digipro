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
        Schema::table('appraisal_requests', function (Blueprint $table) {
            $table->foreignId('contract_number_assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('contract_number');

            $table->timestamp('contract_number_assigned_at')
                ->nullable()
                ->after('contract_number_assigned_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('contract_number_assigned_by');
        Schema::drop('contract_number_assigned_at');
    }
};
