<?php

use App\Models\ReportSigner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_signers', function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('email', 120)->nullable()->after('name');
            $table->string('phone_number', 40)->nullable()->after('email');
            $table->string('peruri_certificate_status', 40)->nullable()->after('is_active');
            $table->string('peruri_keyla_status', 40)->nullable()->after('peruri_certificate_status');
            $table->dateTime('peruri_last_checked_at')->nullable()->after('peruri_keyla_status');

            $table->index(['user_id'], 'rs_user_idx');
        });

        Schema::table('appraisal_requests', function (Blueprint $table): void {
            $table->foreignId('contract_public_appraiser_signer_id')
                ->nullable()
                ->after('report_public_appraiser_signer_id')
                ->constrained('report_signers')
                ->nullOnDelete();

            $table->json('contract_signer_snapshot')
                ->nullable()
                ->after('contract_public_appraiser_signer_id');

            $table->index(['contract_public_appraiser_signer_id'], 'ar_contract_public_appraiser_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_requests', function (Blueprint $table): void {
            $table->dropIndex('ar_contract_public_appraiser_idx');
            $table->dropConstrainedForeignId('contract_public_appraiser_signer_id');
            $table->dropColumn('contract_signer_snapshot');
        });

        Schema::table('report_signers', function (Blueprint $table): void {
            $table->dropIndex('rs_user_idx');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'email',
                'phone_number',
                'peruri_certificate_status',
                'peruri_keyla_status',
                'peruri_last_checked_at',
            ]);
        });
    }
};

