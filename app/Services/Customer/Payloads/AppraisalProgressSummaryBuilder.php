<?php

namespace App\Services\Customer\Payloads;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;

class AppraisalProgressSummaryBuilder
{
    private const MILESTONES = [
        ['key' => 'submission', 'label' => 'Pengajuan'],
        ['key' => 'verification', 'label' => 'Verifikasi Dokumen'],
        ['key' => 'offer', 'label' => 'Penawaran'],
        ['key' => 'contract_payment', 'label' => 'Kontrak & Pembayaran'],
        ['key' => 'valuation', 'label' => 'Proses Kajian'],
        ['key' => 'report_done', 'label' => 'Laporan Selesai'],
    ];

    public function build(
        AppraisalRequest $record,
        array $revisionSummary = [],
        array $previewState = [],
        ?object $latestPayment = null,
        array $statusTimeline = [],
        ?string $reportPdfUrl = null
    ): array {
        $status = $record->status?->value ?? (string) $record->status;
        $stageKey = $this->resolveStageKey($record, $status, $latestPayment);
        $stageIndex = $this->stageIndex($stageKey);
        $milestones = collect(self::MILESTONES)
            ->map(function (array $milestone, int $index) use ($stageIndex): array {
                return [
                    'key' => $milestone['key'],
                    'label' => $milestone['label'],
                    'state' => $index < $stageIndex
                        ? 'completed'
                        : ($index === $stageIndex ? 'current' : 'upcoming'),
                ];
            })
            ->values()
            ->all();

        $substatus = $this->resolveSubstatus($record, $status, $revisionSummary, $previewState, $latestPayment);
        $helperText = $this->resolveHelperText($record, $status, $revisionSummary, $previewState, $latestPayment);
        $primaryAction = $this->resolvePrimaryAction($record, $status, $revisionSummary, $previewState, $latestPayment, $reportPdfUrl);
        $lastEventAt = $this->resolveLastEventAt($record, $statusTimeline);

        return [
            'current_step' => $stageIndex + 1,
            'total_steps' => count(self::MILESTONES),
            'current_key' => $stageKey,
            'current_label' => self::MILESTONES[$stageIndex]['label'],
            'status_label' => $record->status?->label() ?? $status,
            'milestones' => $milestones,
            'substatus' => $substatus,
            'helper_text' => $helperText,
            'last_event_at' => $lastEventAt,
            'terminal_state' => $status === AppraisalStatusEnum::Cancelled->value ? 'cancelled' : null,
            'primary_action' => $primaryAction,
        ];
    }

    private function resolveStageKey(AppraisalRequest $record, string $status, ?object $latestPayment): string
    {
        return match ($status) {
            AppraisalStatusEnum::Draft->value,
            AppraisalStatusEnum::Submitted->value => 'submission',
            AppraisalStatusEnum::DocsIncomplete->value,
            AppraisalStatusEnum::Verified->value => 'verification',
            AppraisalStatusEnum::WaitingOffer->value,
            AppraisalStatusEnum::OfferSent->value => 'offer',
            AppraisalStatusEnum::WaitingSignature->value,
            AppraisalStatusEnum::ContractSigned->value => 'contract_payment',
            AppraisalStatusEnum::CancellationReviewPending->value => $this->resolveCancellationReviewStageKey($record, $latestPayment),
            AppraisalStatusEnum::ValuationOnProgress->value,
            AppraisalStatusEnum::ValuationCompleted->value,
            AppraisalStatusEnum::PreviewReady->value,
            AppraisalStatusEnum::ReportPreparation->value => 'valuation',
            AppraisalStatusEnum::ReportReady->value,
            AppraisalStatusEnum::Completed->value => 'report_done',
            AppraisalStatusEnum::Cancelled->value => $this->resolveCancelledStageKey($record, $latestPayment),
            default => 'submission',
        };
    }

    private function resolveCancellationReviewStageKey(AppraisalRequest $record, ?object $latestPayment): string
    {
        $statusBefore = $record->latestCancellationRequest?->status_before_request;

        if (! filled($statusBefore)) {
            return $this->resolveCancelledStageKey($record, $latestPayment);
        }

        return $this->resolveStageKey($record, (string) $statusBefore, $latestPayment);
    }

    private function resolveCancelledStageKey(AppraisalRequest $record, ?object $latestPayment): string
    {
        if ($record->report_generated_at) {
            return 'report_done';
        }

        if (
            $record->market_preview_published_at
            || $record->market_preview_approved_at
            || $record->market_preview_appeal_submitted_at
            || $record->report_draft_generated_at
        ) {
            return 'valuation';
        }

        if ($record->contract_number || $latestPayment) {
            return 'contract_payment';
        }

        if ($record->offerNegotiations->isNotEmpty()) {
            return 'offer';
        }

        if ($record->verified_at) {
            return 'verification';
        }

        return 'submission';
    }

    private function resolveSubstatus(
        AppraisalRequest $record,
        string $status,
        array $revisionSummary,
        array $previewState,
        ?object $latestPayment
    ): ?array {
        if ($status === AppraisalStatusEnum::Cancelled->value) {
            return [
                'label' => 'Permohonan dibatalkan',
                'tone' => 'danger',
            ];
        }

        if ($status === AppraisalStatusEnum::CancellationReviewPending->value) {
            return [
                'label' => 'Pengajuan pembatalan sedang direview admin',
                'tone' => 'warning',
            ];
        }

        if (($revisionSummary['has_open_batch'] ?? false) === true) {
            return [
                'label' => 'Revisi data atau dokumen dibutuhkan',
                'tone' => 'warning',
            ];
        }

        if ($status === AppraisalStatusEnum::WaitingOffer->value && (int) ($record->negotiation_rounds_used ?? 0) > 0) {
            return [
                'label' => 'Menunggu respon admin atas negosiasi',
                'tone' => 'warning',
            ];
        }

        if ($status === AppraisalStatusEnum::OfferSent->value) {
            return [
                'label' => (int) ($record->negotiation_rounds_used ?? 0) > 0
                    ? 'Penawaran revisi tersedia'
                    : 'Penawaran aktif menunggu keputusan',
                'tone' => 'info',
            ];
        }

        if ($status === AppraisalStatusEnum::WaitingSignature->value) {
            return [
                'label' => 'Kontrak siap ditandatangani',
                'tone' => 'info',
            ];
        }

        if ($status === AppraisalStatusEnum::ContractSigned->value) {
            $paymentStatus = (string) ($latestPayment->status ?? '');

            return match ($paymentStatus) {
                'pending' => ['label' => 'Pembayaran sedang menunggu penyelesaian', 'tone' => 'warning'],
                'failed' => ['label' => 'Pembayaran gagal diproses', 'tone' => 'danger'],
                'expired' => ['label' => 'Sesi pembayaran kedaluwarsa', 'tone' => 'warning'],
                'rejected' => ['label' => 'Pembayaran ditolak', 'tone' => 'danger'],
                'paid' => ['label' => 'Pembayaran berhasil diterima', 'tone' => 'success'],
                default => ['label' => 'Kontrak sudah ditandatangani', 'tone' => 'success'],
            };
        }

        if ($status === AppraisalStatusEnum::PreviewReady->value && ($previewState['has_preview'] ?? false)) {
            return [
                'label' => 'Preview kajian siap ditinjau',
                'tone' => 'info',
            ];
        }

        if ($status === AppraisalStatusEnum::ReportPreparation->value) {
            return [
                'label' => 'Laporan final sedang disiapkan',
                'tone' => 'info',
            ];
        }

        if (in_array($status, [AppraisalStatusEnum::ReportReady->value, AppraisalStatusEnum::Completed->value], true)) {
            return [
                'label' => 'Laporan siap diunduh',
                'tone' => 'success',
            ];
        }

        if ($status === AppraisalStatusEnum::Verified->value) {
            return [
                'label' => 'Dokumen berhasil diverifikasi',
                'tone' => 'success',
            ];
        }

        return null;
    }

    private function resolveHelperText(
        AppraisalRequest $record,
        string $status,
        array $revisionSummary,
        array $previewState,
        ?object $latestPayment
    ): string {
        if ($status === AppraisalStatusEnum::Cancelled->value) {
            if (filled($record->cancellation_reason)) {
                return 'Permohonan tidak dapat dilanjutkan. Alasan pembatalan: ' . $record->cancellation_reason;
            }

            return 'Permohonan tidak dapat dilanjutkan karena sudah masuk status dibatalkan.';
        }

        if ($status === AppraisalStatusEnum::CancellationReviewPending->value) {
            return 'Pengajuan pembatalan Anda sedang ditinjau. Admin akan menghubungi Anda untuk memastikan keputusan akhir permohonan ini.';
        }

        if (($revisionSummary['has_open_batch'] ?? false) === true) {
            $itemsCount = (int) ($revisionSummary['items_count'] ?? 0);

            return $itemsCount > 0
                ? "Admin meminta Anda memperbaiki {$itemsCount} item data, dokumen, atau foto sebelum proses berlanjut."
                : 'Admin meminta Anda memperbaiki data atau dokumen sebelum proses berlanjut.';
        }

        if ($status === AppraisalStatusEnum::WaitingOffer->value && (int) ($record->negotiation_rounds_used ?? 0) > 0) {
            return 'Keberatan fee Anda sudah terkirim. Tim admin sedang meninjau hasil negosiasi terbaru.';
        }

        if ($status === AppraisalStatusEnum::OfferSent->value) {
            return 'Review penawaran yang tersedia, lalu setujui, negosiasikan, atau lanjutkan keputusan yang diperlukan.';
        }

        if ($status === AppraisalStatusEnum::WaitingSignature->value) {
            return 'Penawaran sudah disepakati. Lanjutkan ke halaman tanda tangan kontrak untuk membuka tahap pembayaran.';
        }

        if ($status === AppraisalStatusEnum::ContractSigned->value) {
            $paymentStatus = (string) ($latestPayment->status ?? '');

            return match ($paymentStatus) {
                'pending' => 'Selesaikan pembayaran agar permohonan masuk ke tahap proses kajian.',
                'failed' => 'Pembayaran terakhir gagal. Buat sesi pembayaran baru untuk melanjutkan proses.',
                'expired' => 'Sesi pembayaran terakhir sudah kedaluwarsa. Anda dapat membuat sesi pembayaran baru.',
                'rejected' => 'Bukti pembayaran ditolak. Periksa detail pembayaran dan kirim ulang melalui halaman pembayaran.',
                'paid' => 'Pembayaran telah diterima. Tim reviewer akan melanjutkan proses kajian pasar.',
                default => 'Kontrak sudah ditandatangani. Lanjutkan ke halaman pembayaran untuk memulai proses valuasi.',
            };
        }

        if ($status === AppraisalStatusEnum::PreviewReady->value && ($previewState['has_preview'] ?? false)) {
            return 'Preview hasil kajian tersedia dalam bentuk range. Tinjau hasilnya sebelum laporan final disiapkan.';
        }

        if ($status === AppraisalStatusEnum::ReportPreparation->value) {
            return 'Preview sudah disetujui. Admin sedang melengkapi laporan final beserta dokumen pendukungnya.';
        }

        if ($status === AppraisalStatusEnum::ReportReady->value) {
            return 'Laporan final sudah tersedia dan dapat diunduh dari permohonan ini.';
        }

        if ($status === AppraisalStatusEnum::Completed->value) {
            return 'Seluruh proses permohonan penilaian telah selesai.';
        }

        if ($status === AppraisalStatusEnum::Verified->value) {
            return 'Dokumen awal sudah diverifikasi. Permohonan akan masuk ke tahap penawaran.';
        }

        if ($status === AppraisalStatusEnum::Submitted->value) {
            return 'Permohonan sudah terkirim dan sedang menunggu verifikasi awal dari admin.';
        }

        return 'Pantau progres permohonan Anda berdasarkan milestone utama yang sedang berjalan.';
    }

    private function resolvePrimaryAction(
        AppraisalRequest $record,
        string $status,
        array $revisionSummary,
        array $previewState,
        ?object $latestPayment,
        ?string $reportPdfUrl
    ): ?array {
        if (($revisionSummary['has_open_batch'] ?? false) === true && filled($revisionSummary['page_url'] ?? null)) {
            return [
                'label' => 'Buka Halaman Revisi',
                'url' => (string) $revisionSummary['page_url'],
                'variant' => 'default',
                'external' => false,
            ];
        }

        if ($status === AppraisalStatusEnum::CancellationReviewPending->value) {
            return null;
        }

        if (in_array($status, [AppraisalStatusEnum::WaitingOffer->value, AppraisalStatusEnum::OfferSent->value], true)) {
            return [
                'label' => 'Halaman Penawaran',
                'url' => route('appraisal.offer.page', ['id' => $record->id]),
                'variant' => $status === AppraisalStatusEnum::OfferSent->value ? 'default' : 'outline',
                'external' => false,
            ];
        }

        if ($status === AppraisalStatusEnum::WaitingSignature->value) {
            return [
                'label' => 'Tanda Tangan Kontrak',
                'url' => route('appraisal.contract.page', ['id' => $record->id]),
                'variant' => 'default',
                'external' => false,
            ];
        }

        if ($status === AppraisalStatusEnum::ContractSigned->value) {
            $paymentStatus = (string) ($latestPayment->status ?? '');

            return [
                'label' => $paymentStatus === 'paid' ? 'Lihat Invoice' : 'Halaman Pembayaran',
                'url' => route($paymentStatus === 'paid' ? 'appraisal.invoice.page' : 'appraisal.payment.page', ['id' => $record->id]),
                'variant' => 'default',
                'external' => false,
            ];
        }

        if ($status === AppraisalStatusEnum::PreviewReady->value && ($previewState['page_url'] ?? null)) {
            return [
                'label' => 'Review Preview Kajian',
                'url' => (string) $previewState['page_url'],
                'variant' => 'default',
                'external' => false,
            ];
        }

        if (in_array($status, [AppraisalStatusEnum::ReportReady->value, AppraisalStatusEnum::Completed->value], true) && filled($reportPdfUrl)) {
            return [
                'label' => 'Download Laporan',
                'url' => $reportPdfUrl,
                'variant' => 'default',
                'external' => true,
            ];
        }

        return null;
    }

    private function resolveLastEventAt(AppraisalRequest $record, array $statusTimeline): ?string
    {
        $lastTimelineEntry = collect($statusTimeline)->last();

        if (is_array($lastTimelineEntry) && filled($lastTimelineEntry['at'] ?? null)) {
            return (string) $lastTimelineEntry['at'];
        }

        return optional($record->updated_at)->toDateTimeString();
    }

    private function stageIndex(string $stageKey): int
    {
        foreach (self::MILESTONES as $index => $milestone) {
            if ($milestone['key'] === $stageKey) {
                return $index;
            }
        }

        return 0;
    }
}
