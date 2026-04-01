<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_requests', function (Blueprint $table) {
            $table->boolean('sertifikat_on_hand_confirmed')
                ->default(false)
                ->after('consent_user_agent');
            $table->boolean('certificate_not_encumbered_confirmed')
                ->default(false)
                ->after('sertifikat_on_hand_confirmed');
            $table->timestamp('certificate_statements_accepted_at')
                ->nullable()
                ->after('certificate_not_encumbered_confirmed');
            $table->string('certificate_statement_ip', 64)
                ->nullable()
                ->after('certificate_statements_accepted_at');
            $table->string('certificate_statement_user_agent', 255)
                ->nullable()
                ->after('certificate_statement_ip');
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_requests', function (Blueprint $table) {
            $table->dropColumn([
                'sertifikat_on_hand_confirmed',
                'certificate_not_encumbered_confirmed',
                'certificate_statements_accepted_at',
                'certificate_statement_ip',
                'certificate_statement_user_agent',
            ]);
        });
    }
};
