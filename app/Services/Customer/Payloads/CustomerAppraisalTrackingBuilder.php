<?php

namespace App\Services\Customer\Payloads;

use App\Enums\ReportTypeEnum;
use App\Models\AppraisalRequest;
use App\Services\AppraisalPhysicalReportSummaryBuilder;
use App\Services\AppraisalRequestCancellationService;
use App\Services\Payments\MidtransSnapService;
use App\Services\Revisions\AppraisalRequestRevisionSubmissionService;
use Illuminate\Support\Facades\Storage;

class CustomerAppraisalTrackingBuilder
{
    public function __construct(
        private readonly AppraisalPayloadFormatter $formatter,
        private readonly AppraisalStatusTimelineBuilder $statusTimelineBuilder,
        private readonly AppraisalPreviewStateBuilder $previewStateBuilder,
        private readonly AppraisalProgressSummaryBuilder $progressSummaryBuilder,
        private readonly AppraisalPhysicalReportSummaryBuilder $physicalReportSummaryBuilder,
        private readonly MidtransSnapService $midtransSnapService,
        private readonly AppraisalRequestRevisionSubmissionService $revisionSubmissionService,
        private readonly AppraisalRequestCancellationService $cancellationService,
    ) {
    }

    public function build(int $userId, int $id): array
    {
        $record = AppraisalRequest::query()
            ->where('user_id', $userId)
            ->withCount([
                'assets',
                'offerNegotiations as negotiation_rounds_used' => fn ($query) => $query->where('action', 'counter_request'),
            ])
            ->with([
                'cancelledBy:id,name',
                'physicalReportPrintedBy:id,name',
                'latestCancellationRequest' => function ($query): void {
                    $query->select([
                        'appraisal_request_cancellations.id',
                        'appraisal_request_cancellations.appraisal_request_id',
                        'appraisal_request_cancellations.status_before_request',
                        'appraisal_request_cancellations.review_status',
                        'appraisal_request_cancellations.reason',
                        'appraisal_request_cancellations.review_note',
                        'appraisal_request_cancellations.contacted_at',
                        'appraisal_request_cancellations.reviewed_by',
                        'appraisal_request_cancellations.reviewed_at',
                        'appraisal_request_cancellations.created_at',
                        'appraisal_request_cancellations.updated_at',
                    ])->with('reviewedBy:id,name');
                },
                'offerNegotiations:id,appraisal_request_id,user_id,action,round,offered_fee,expected_fee,selected_fee,reason,meta,created_at',
                'offerNegotiations.user:id,name',
                'payments:id,appraisal_request_id,amount,method,gateway,external_payment_id,status,paid_at,metadata,updated_at,created_at',
            ])
            ->findOrFail($id);

        $statusTimeline = $this->statusTimelineBuilder->build($record);
        $previewState = $this->previewStateBuilder->build($record);
        $revisionSummary = $this->revisionSubmissionService->buildSummary($record);
        $latestPayment = $record->payments->sortByDesc('id')->first();
        $reportPdfUrl = null;
        $latestCancellationRequest = $record->latestCancellationRequest;

        if ($record->report_pdf_path && Storage::disk('public')->exists($record->report_pdf_path)) {
            $reportPdfUrl = Storage::disk('public')->url($record->report_pdf_path);
        }

        return [
            'request' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'report_type_label' => $this->formatter->enumLabel(ReportTypeEnum::class, $record->report_type)
                    ?? $this->formatter->headlineOrDashValue($this->formatter->enumBackedValue(ReportTypeEnum::class, $record->report_type)),
                'status' => $record->status?->value ?? (string) $record->status,
                'status_label' => $record->status?->label() ?? '-',
                'assets_count' => (int) ($record->assets_count ?? 0),
                'requested_at' => optional($record->requested_at)->toDateTimeString(),
                'verified_at' => optional($record->verified_at)->toDateTimeString(),
                'cancelled_at' => optional($record->cancelled_at)->toDateTimeString(),
                'cancelled_by_name' => $record->cancelledBy?->name,
                'cancellation_reason' => $record->cancellation_reason,
                'cancellation_request' => [
                    'has_open_request' => in_array($latestCancellationRequest?->review_status, ['pending', 'in_progress'], true),
                    'status' => $latestCancellationRequest?->review_status,
                    'status_label' => match ($latestCancellationRequest?->review_status) {
                        'pending' => 'Menunggu Review Pembatalan',
                        'in_progress' => 'Sedang Dihubungi Admin',
                        'approved' => 'Pembatalan Disetujui',
                        'rejected' => 'Pengajuan Ditolak',
                        default => null,
                    },
                    'requested_at' => optional($latestCancellationRequest?->created_at)->toDateTimeString(),
                    'reason' => $latestCancellationRequest?->reason,
                    'review_note' => $latestCancellationRequest?->review_note,
                    'reviewed_by_name' => $latestCancellationRequest?->reviewedBy?->name,
                ],
                'progress_summary' => $this->progressSummaryBuilder->build(
                    $record,
                    $revisionSummary,
                    $previewState,
                    $latestPayment,
                    $statusTimeline,
                    $reportPdfUrl
                ),
                'physical_report' => $this->physicalReportSummaryBuilder->build($record),
                'status_timeline' => $statusTimeline,
                'tracking_page_url' => route('appraisal.tracking.page', ['id' => $record->id]),
                'tracking_context' => [
                    'title' => 'Tracking Progress',
                    'description' => 'Riwayat lengkap perubahan status dan aktivitas penting permohonan penilaian.',
                    'back_url' => route('appraisal.show', ['id' => $record->id]),
                ],
                'payment_summary' => [
                    'status' => $latestPayment?->status,
                    'status_label' => $this->midtransSnapService->paymentStatusLabel($latestPayment),
                    'paid_at' => optional($latestPayment?->paid_at)->toDateTimeString(),
                ],
                'preview_state' => [
                    'has_preview' => (bool) ($previewState['has_preview'] ?? false),
                    'status' => $previewState['status'] ?? null,
                    'version' => $previewState['version'] ?? null,
                ],
                'revision_summary' => $revisionSummary,
            ],
        ];
    }
}
