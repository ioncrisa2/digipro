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
            $table->timestamp('consent_accepted_at')->nullable()->after('requested_at');
            $table->string('consent_version', 32)->nullable()->after('consent_accepted_at');
            $table->string('consent_hash', 64)->nullable()->after('consent_version');
            $table->string('consent_ip', 64)->nullable()->after('consent_hash');
            $table->string('consent_user_agent', 255)->nullable()->after('consent_ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appraisal_requests', function (Blueprint $table) {
            $table->dropColumn([
                'consent_accepted_at',
                'consent_version',
                'consent_hash',
                'consent_ip',
                'consent_user_agent',
            ]);
        });
    }
};
