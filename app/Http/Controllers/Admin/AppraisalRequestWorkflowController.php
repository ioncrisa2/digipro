<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAppraisalRequestRevisionBatchRequest;
use App\Http\Requests\Admin\StoreAppraisalOfferRequest;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestRevisionItem;
use App\Services\Admin\AppraisalRequestRevisionService;
use App\Services\Admin\AppraisalRequestRevisionReviewService;
use App\Services\Admin\AppraisalRequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppraisalRequestWorkflowController extends Controller
{
    public function storeRevisionBatch(
        StoreAppraisalRequestRevisionBatchRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalRequestRevisionService $revisionService
    ): RedirectResponse {
        try {
            $revisionService->createBatch(
                $appraisalRequest,
                (int) $request->user()->id,
                $request->resolvedItems(),
                $request->string('admin_note')->toString()
            );

            return back()->with('success', 'Permintaan revisi dokumen berhasil dibuat dan customer perlu mengunggah ulang dokumen yang diminta.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function sendOffer(
        StoreAppraisalOfferRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): RedirectResponse {
        try {
            $result = $workflowService->sendOffer(
                $appraisalRequest,
                (int) $request->user()->id,
                $request->validated()
            );

            $message = $result['action'] === 'offer_revised'
                ? 'Counter offer berhasil dikirim.'
                : 'Penawaran berhasil dikirim.';

            return back()->with('success', $message);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function approveLatestNegotiation(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService,
        Request $request
    ): RedirectResponse {
        try {
            $workflowService->approveLatestNegotiation($appraisalRequest, (int) $request->user()->id);

            return back()->with('success', 'Harapan fee user disetujui. Request langsung masuk ke tahap tanda tangan kontrak.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function verifyDocs(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService,
        Request $request
    ): RedirectResponse {
        try {
            $workflowService->verifyDocs($appraisalRequest, (int) $request->user()->id);

            return back()->with('success', 'Dokumen berhasil diverifikasi. Request masuk ke tahap menunggu penawaran.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function markDocsIncomplete(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): RedirectResponse {
        try {
            $workflowService->markDocsIncomplete($appraisalRequest);

            return back()->with('success', 'Request berhasil ditandai dokumen kurang.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function markContractSigned(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): RedirectResponse {
        try {
            $workflowService->markContractSigned($appraisalRequest);

            return back()->with('success', 'Status kontrak berhasil diperbarui menjadi ditandatangani.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function verifyPayment(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): RedirectResponse {
        try {
            $workflowService->verifyPayment($appraisalRequest);

            return back()->with('success', 'Pembayaran terverifikasi. Request masuk ke proses valuasi.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function approveRevisionItem(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestRevisionItem $revisionItem,
        AppraisalRequestRevisionReviewService $reviewService,
        Request $request
    ): RedirectResponse {
        try {
            $reviewService->approveItem(
                $appraisalRequest,
                $revisionItem,
                (int) $request->user()->id
            );

            return back()->with('success', 'Dokumen revisi berhasil disetujui.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function rejectRevisionItem(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestRevisionItem $revisionItem,
        AppraisalRequestRevisionReviewService $reviewService,
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $reviewService->rejectItem(
                $appraisalRequest,
                $revisionItem,
                (int) $request->user()->id,
                (string) $validated['review_note']
            );

            return back()->with('success', 'Item revisi dibuka kembali dan customer diminta mengunggah ulang dokumen.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
