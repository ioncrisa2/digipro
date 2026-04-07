<?php

namespace App\Services\Customer\Payloads;

use App\Enums\AppraisalStatusEnum;
use App\Enums\ReportTypeEnum;
use App\Models\AppraisalRequest;

class CustomerAppraisalDocumentBuilder
{
    public function __construct(
        private readonly AppraisalPayloadFormatter $formatter,
        private readonly AppraisalDocumentCatalogBuilder $documentCatalogBuilder,
    ) {
    }

    public function buildIndexPayload(int $userId): array
    {
        $records = AppraisalRequest::query()
            ->where('user_id', $userId)
            ->with([
                'user:id,name,email',
                'assets:id,appraisal_request_id,asset_type,address',
                'assets.files:id,appraisal_asset_id,type,path,original_name,mime,size,created_at',
                'files:id,appraisal_request_id,type,path,original_name,mime,size,created_at',
                'payments:id,appraisal_request_id,amount,method,gateway,external_payment_id,status,paid_at,metadata,updated_at,created_at',
            ])
            ->latest('requested_at')
            ->get();

        $reports = $records->map(function (AppraisalRequest $record): array {
            $collections = $this->documentCatalogBuilder->resolveCollections($record);
            $summary = $this->documentCatalogBuilder->buildSummary($collections);
            $statusValue = $this->formatter->enumBackedValue(AppraisalStatusEnum::class, $record->status);

            return [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'client' => $record->client_name ?: ($record->user?->name ?? '-'),
                'report_type' => $this->formatter->enumLabel(ReportTypeEnum::class, $record->report_type)
                    ?? $this->formatter->headlineOrDashValue($this->formatter->reportTypeValue($record->report_type)),
                'status' => $this->formatter->enumLabel(AppraisalStatusEnum::class, $record->status)
                    ?? $this->formatter->headlineOrDashValue($statusValue),
                'status_key' => $statusValue,
                'address' => $collections['first_asset_address'],
                'updated_at' => optional($record->updated_at)->toDateString(),
                'customer_documents_count' => $summary['customer_documents_count'],
                'customer_photos_count' => $summary['customer_photos_count'],
                'system_documents_count' => $summary['system_documents_count'],
                'ready_contract' => $summary['ready_contract'],
                'ready_report' => $summary['ready_report'],
                'ready_invoice' => $summary['ready_invoice'],
                'ready_legal_documents' => $summary['ready_legal_documents'],
                'total_documents_count' => $summary['total_documents_count'],
            ];
        })->values()->all();

        return ['reports' => $reports];
    }

    public function buildShowPayload(int $userId, int $id): array
    {
        $record = AppraisalRequest::query()
            ->where('user_id', $userId)
            ->with([
                'user:id,name,email',
                'assets:id,appraisal_request_id,asset_type,address',
                'assets.files:id,appraisal_asset_id,type,path,original_name,mime,size,created_at',
                'files:id,appraisal_request_id,type,path,original_name,mime,size,created_at',
                'payments:id,appraisal_request_id,amount,method,gateway,external_payment_id,status,paid_at,metadata,updated_at,created_at',
            ])
            ->findOrFail($id);

        $collections = $this->documentCatalogBuilder->resolveCollections($record);
        $summary = $this->documentCatalogBuilder->buildSummary($collections);
        $statusValue = $this->formatter->enumBackedValue(AppraisalStatusEnum::class, $record->status);

        return [
            'report' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'client' => $record->client_name ?: ($record->user?->name ?? '-'),
                'report_type' => $this->formatter->enumLabel(ReportTypeEnum::class, $record->report_type)
                    ?? $this->formatter->headlineOrDashValue($this->formatter->reportTypeValue($record->report_type)),
                'status' => $this->formatter->enumLabel(AppraisalStatusEnum::class, $record->status)
                    ?? $this->formatter->headlineOrDashValue($statusValue),
                'status_key' => $statusValue,
                'address' => $collections['first_asset_address'],
                'updated_at' => optional($record->updated_at)->toDateTimeString(),
                'summary' => $summary,
                'request_upload_documents' => $collections['request_upload_documents'],
                'asset_sections' => $collections['asset_sections'],
                'system_documents' => $collections['system_documents'],
                'legal_documents' => $collections['legal_documents'],
                'billing_documents' => $collections['billing_documents'],
            ],
        ];
    }
}
