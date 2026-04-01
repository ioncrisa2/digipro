<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_signers', function (Blueprint $table) {
            $table->id();
            $table->string('role', 40);
            $table->string('name');
            $table->string('position_title')->nullable();
            $table->string('title_suffix')->nullable();
            $table->string('certification_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['role', 'is_active']);
        });

        Schema::table('appraisal_assets', function (Blueprint $table) {
            $table->string('certificate_number')->nullable()->after('title_document');
            $table->string('certificate_holder_name')->nullable()->after('certificate_number');
            $table->date('certificate_issued_at')->nullable()->after('certificate_holder_name');
            $table->date('land_book_date')->nullable()->after('certificate_issued_at');
            $table->decimal('document_land_area', 12, 2)->nullable()->after('land_book_date');
            $table->text('legal_notes')->nullable()->after('document_land_area');
        });

        Schema::table('appraisal_requests', function (Blueprint $table) {
            $table->foreignId('report_reviewer_signer_id')
                ->nullable()
                ->after('report_generated_by')
                ->constrained('report_signers')
                ->nullOnDelete();
            $table->foreignId('report_public_appraiser_signer_id')
                ->nullable()
                ->after('report_reviewer_signer_id')
                ->constrained('report_signers')
                ->nullOnDelete();
            $table->json('report_signer_snapshot')
                ->nullable()
                ->after('report_public_appraiser_signer_id');
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('report_reviewer_signer_id');
            $table->dropConstrainedForeignId('report_public_appraiser_signer_id');
            $table->dropColumn('report_signer_snapshot');
        });

        Schema::table('appraisal_assets', function (Blueprint $table) {
            $table->dropColumn([
                'certificate_number',
                'certificate_holder_name',
                'certificate_issued_at',
                'land_book_date',
                'document_land_area',
                'legal_notes',
            ]);
        });

        Schema::dropIfExists('report_signers');
    }
};
