<?php

namespace App\Support\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;
use App\Services\Admin\AppraisalRequestWorkflowService;

class AppraisalRequestActionResolver
{
    public function buildAvailableActions(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): array {
        $actions = [];

        $verifyDocsState = $workflowService->verifyDocsState($appraisalRequest);

        if ($verifyDocsState['show'] ?? false) {
            $actions[] = [
                'key' => 'verify-docs',
                'label' => 'Verifikasi Dokumen',
                'variant' => 'default',
                'message' => 'Lanjutkan request ini ke tahap menunggu penawaran?',
                'url' => route('admin.appraisal-requests.actions.verify-docs', $appraisalRequest),
                'disabled' => ! ($verifyDocsState['ready'] ?? false),
                'disabled_reason' => $verifyDocsState['message'] ?? null,
            ];
        }

        if ($workflowService->canMarkContractSigned($appraisalRequest)) {
            $actions[] = [
                'key' => 'contract-signed',
                'label' => 'Kontrak Ditandatangani',
                'variant' => 'default',
                'message' => 'Ubah status request ini menjadi kontrak ditandatangani?',
                'url' => route('admin.appraisal-requests.actions.contract-signed', $appraisalRequest),
                'disabled' => false,
                'disabled_reason' => null,
            ];
        }

        if ($workflowService->canVerifyPayment($appraisalRequest)) {
            $actions[] = [
                'key' => 'verify-payment',
                'label' => 'Verifikasi Pembayaran',
                'variant' => 'default',
                'message' => 'Pembayaran sudah valid. Lanjutkan request ini ke proses valuasi?',
                'url' => route('admin.appraisal-requests.actions.verify-payment', $appraisalRequest),
                'disabled' => false,
                'disabled_reason' => null,
            ];
        }

        if ($workflowService->canCancelRequest($appraisalRequest)) {
            $actions[] = [
                'key' => 'cancel-request',
                'label' => 'Batalkan Request',
                'variant' => 'destructive',
                'message' => 'Batalkan request ini dari workspace admin. Alasan pembatalan wajib diisi dan akan terlihat oleh customer.',
                'url' => route('admin.appraisal-requests.actions.cancel', $appraisalRequest),
                'disabled' => false,
                'disabled_reason' => null,
                'requires_reason' => true,
            ];
        }

        return $actions;
    }

    public function buildOfferAction(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): ?array {
        if (! $workflowService->canSendOffer($appraisalRequest)) {
            return null;
        }

        $defaults = $workflowService->resolveOfferDefaults($appraisalRequest);
        $statusValue = $appraisalRequest->status?->value ?? $appraisalRequest->status;

        return [
            'label' => $statusValue === AppraisalStatusEnum::WaitingOffer->value
                ? 'Kirim Counter Offer'
                : 'Kirim Penawaran',
            'description' => $statusValue === AppraisalStatusEnum::WaitingOffer->value
                ? 'Gunakan form ini untuk merespons negosiasi user dengan penawaran revisi.'
                : 'Gunakan form ini untuk mengirim penawaran awal ke user.',
            'url' => route('admin.appraisal-requests.actions.send-offer', $appraisalRequest),
            'defaults' => $defaults,
        ];
    }

    public function buildApproveLatestNegotiationAction(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): ?array {
        if (! $workflowService->canApproveLatestNegotiation($appraisalRequest)) {
            return null;
        }

        $latestCounter = $workflowService->latestCounterRequest($appraisalRequest);

        if ($latestCounter === null) {
            return null;
        }

        return [
            'label' => 'Setujui Harapan Fee User',
            'message' => 'Fee akan mengikuti harapan fee terbaru dari user dan hasilnya langsung final ke tahap tanda tangan kontrak. Lanjutkan?',
            'url' => route('admin.appraisal-requests.actions.approve-latest-negotiation', $appraisalRequest),
            'expected_fee' => $latestCounter->expected_fee,
            'round' => $latestCounter->round,
            'reason' => $latestCounter->reason,
        ];
    }

    public function buildPaymentVerification(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): ?array {
        $state = $workflowService->paymentVerificationState($appraisalRequest);

        if (! ($state['show'] ?? false)) {
            return null;
        }

        return [
            'ready' => (bool) ($state['ready'] ?? false),
            'message' => $state['message'] ?? null,
            'action_url' => $workflowService->canVerifyPayment($appraisalRequest)
                ? route('admin.appraisal-requests.actions.verify-payment', $appraisalRequest)
                : null,
        ];
    }
}
