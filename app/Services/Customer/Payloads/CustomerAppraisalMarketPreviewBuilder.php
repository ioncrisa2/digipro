<?php

namespace App\Services\Customer\Payloads;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;

class CustomerAppraisalMarketPreviewBuilder
{
    public function __construct(
        private readonly AppraisalPreviewStateBuilder $previewStateBuilder,
    ) {
    }

    public function build(int $userId, int $id): array
    {
        $record = AppraisalRequest::query()
            ->where('user_id', $userId)
            ->with([
                'user:id,name,email',
                'assets:id,appraisal_request_id,asset_type,address,land_area,building_area,estimated_value_low,estimated_value_high,market_value_final',
            ])
            ->findOrFail($id);

        $previewState = $this->previewStateBuilder->build($record);
        $status = $record->status?->value ?? (string) $record->status;

        if ($status !== AppraisalStatusEnum::PreviewReady->value) {
            abort(404);
        }

        return [
            'preview' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
                'status' => $status,
                'status_label' => $record->status?->label() ?? (string) $record->status,
                'report_type_label' => $record->report_type?->label() ?? '-',
                'version' => $previewState['version'],
                'published_at' => $previewState['published_at'],
                'summary' => $previewState['summary'],
                'assets' => $previewState['assets'],
                'can_approve' => true,
                'can_appeal' => $previewState['appeal_remaining'] > 0,
                'appeal_remaining' => $previewState['appeal_remaining'],
                'appeal_reason' => $record->market_preview_appeal_reason,
                'approve_url' => route('appraisal.market-preview.approve', ['id' => $record->id]),
                'appeal_url' => route('appraisal.market-preview.appeal', ['id' => $record->id]),
            ],
        ];
    }
}
