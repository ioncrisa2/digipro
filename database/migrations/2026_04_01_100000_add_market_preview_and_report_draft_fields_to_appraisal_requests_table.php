<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_requests', function (Blueprint $table) {
            $table->json('market_preview_snapshot')->nullable()->after('report_pdf_size');
            $table->unsignedInteger('market_preview_version')->nullable()->after('market_preview_snapshot');
            $table->timestamp('market_preview_published_at')->nullable()->after('market_preview_version');
            $table->timestamp('market_preview_approved_at')->nullable()->after('market_preview_published_at');
            $table->unsignedTinyInteger('market_preview_appeal_count')->default(0)->after('market_preview_approved_at');
            $table->text('market_preview_appeal_reason')->nullable()->after('market_preview_appeal_count');
            $table->timestamp('market_preview_appeal_submitted_at')->nullable()->after('market_preview_appeal_reason');
            $table->timestamp('report_draft_generated_at')->nullable()->after('market_preview_appeal_submitted_at');
            $table->string('report_draft_pdf_path', 500)->nullable()->after('report_draft_generated_at');
            $table->unsignedBigInteger('report_draft_pdf_size')->nullable()->after('report_draft_pdf_path');
        });

        $this->updateStatusEnum([
            'draft',
            'submitted',
            'docs_incomplete',
            'verified',
            'waiting_offer',
            'offer_sent',
            'waiting_signature',
            'contract_signed',
            'valuation_in_progress',
            'valuation_completed',
            'preview_ready',
            'report_preparation',
            'report_ready',
            'completed',
            'cancelled',
        ]);
    }

    public function down(): void
    {
        $this->updateStatusEnum([
            'draft',
            'submitted',
            'docs_incomplete',
            'verified',
            'waiting_offer',
            'offer_sent',
            'waiting_signature',
            'contract_signed',
            'valuation_in_progress',
            'valuation_completed',
            'report_ready',
            'completed',
            'cancelled',
        ]);

        Schema::table('appraisal_requests', function (Blueprint $table) {
            $table->dropColumn([
                'market_preview_snapshot',
                'market_preview_version',
                'market_preview_published_at',
                'market_preview_approved_at',
                'market_preview_appeal_count',
                'market_preview_appeal_reason',
                'market_preview_appeal_submitted_at',
                'report_draft_generated_at',
                'report_draft_pdf_path',
                'report_draft_pdf_size',
            ]);
        });
    }

    private function updateStatusEnum(array $values): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $quoted = implode(', ', array_map(fn (string $value) => "'" . $value . "'", $values));
        DB::statement("ALTER TABLE appraisal_requests MODIFY status ENUM({$quoted}) NOT NULL DEFAULT 'draft'");
    }
};
