<?php

namespace App\Http\Controllers\Customer;

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AppraisalCreatePageRequest;
use App\Http\Requests\Customer\AppraisalIndexRequest;
use App\Http\Requests\Customer\CancelOfferRequest;
use App\Http\Requests\Customer\CustomerAccessRequest;
use App\Http\Requests\Customer\SelectOfferRequest;
use App\Http\Requests\Customer\SignContractRequest;
use App\Http\Requests\Customer\StoreAppraisalRequest;
use App\Http\Requests\Customer\StoreCustomerAppraisalCancellationRequest;
use App\Http\Requests\Customer\SubmitAppraisalRevisionBatchRequest;
use App\Http\Requests\Customer\SubmitMarketPreviewAppealRequest;
use App\Http\Requests\Customer\SubmitOfferNegotiationRequest;
use Illuminate\Http\Request;
use App\Models\AppraisalRequest;
use App\Models\User;
use App\Notifications\AdminActionNotification;
use App\Notifications\AppraisalStatusUpdated;
use App\Services\AppraisalRequestCancellationService;
use App\Services\Admin\AdminNotificationService;
use App\Services\Customer\AppraisalRequestService;
use App\Services\Customer\AppraisalService;
use App\Services\Customer\CustomerAppraisalWorkflowService;
use App\Services\Revisions\AppraisalRequestRevisionSubmissionService;
use App\Services\Workflow\AppraisalMarketPreviewService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Handles appraisal request pages and consent flows.
 */
class AppraisalController extends Controller
{
    public function __construct(
        private readonly CustomerAppraisalWorkflowService $workflowService,
        private readonly AdminNotificationService $adminNotificationService,
    ) {
    }

    public function index(AppraisalIndexRequest $request, AppraisalService $appraisalService)
    {
        $userId = $request->user()->id;
        $filters = $request->filters();
        $q = $filters['q'];
        $status = $filters['status'];
        $payload = $appraisalService->buildIndexPayload($userId, $q, $status, $request->perPage());

        return inertia('Penilaian/Index', array_merge($payload, [
            'filters' => $filters,
        ]));
    }

    /**
     * Create new appraisal request
     * Now includes consent check inline - if user hasn't accepted latest consent,
     * we pass consent data to the form instead of redirecting
     */
    public function create(AppraisalCreatePageRequest $request, AppraisalService $appraisalService)
    {
        $provinceId = $request->provinceId();
        $regencyId = $request->regencyId();
        $districtId = $request->districtId();

        // Check if user needs to accept consent
        $needsConsent = !$appraisalService->hasAcceptedLatestConsent($request);
        $consentData = null;

        if ($needsConsent) {
            $consentData = $appraisalService->buildConsentProps();
        }

        $payload = $appraisalService->buildCreatePayload($provinceId, $regencyId, $districtId, $needsConsent, $consentData);

        return inertia('Penilaian/Create', $payload);
    }

    public function store(StoreAppraisalRequest $request, AppraisalRequestService $service)
    {
        $service->createFromRequest($request);

        return redirect()
            ->route('appraisal.list')
            ->with('success', 'Permohonan penilaian berhasil dikirim.');
    }

    public function show(CustomerAccessRequest $request, int $id, AppraisalService $appraisalService)
    {
        $userId = $request->user()->id;
        $payload = $appraisalService->buildShowPayload($userId, $id);

        return inertia('Penilaian/Show', $payload);
    }

    public function trackingPage(CustomerAccessRequest $request, int $id, AppraisalService $appraisalService)
    {
        $userId = $request->user()->id;
        $payload = $appraisalService->buildTrackingPayload($userId, $id);

        return inertia('Penilaian/Tracking', $payload);
    }

    public function submitCancellationRequest(
        StoreCustomerAppraisalCancellationRequest $request,
        int $id,
        AppraisalRequestCancellationService $cancellationService
    ) {
        $record = $this->workflowService->resolveUserAppraisalRequest($request, $id);

        try {
            $cancellation = $cancellationService->submitByCustomer(
                $record,
                $request->user(),
                (string) $request->validated('reason')
            );
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('error', $exception->getMessage());
        }

        $this->adminNotificationService->notifyAdmins(
            'Pengajuan pembatalan baru',
            ($record->request_number ?? ('#' . $record->id)) . ' mengajukan pembatalan request dan menunggu review admin.',
            route('admin.appraisal-requests.cancellations.show', $cancellation),
            'heroicon-o-exclamation-triangle',
            $request->user()?->id,
        );

        return redirect()
            ->route('appraisal.show', ['id' => $record->id])
            ->with('success', 'Pengajuan pembatalan berhasil dikirim. Admin akan menghubungi Anda untuk review lebih lanjut.');
    }

    public function marketPreviewPage(CustomerAccessRequest $request, int $id, AppraisalService $appraisalService)
    {
        $userId = $request->user()->id;
        $payload = $appraisalService->buildMarketPreviewPayload($userId, $id);

        return inertia('Penilaian/MarketPreview', $payload);
    }

    public function revisionPage(
        CustomerAccessRequest $request,
        int $id,
        AppraisalRequestRevisionSubmissionService $revisionService
    ) {
        $record = $this->workflowService->resolveUserAppraisalRequest($request, $id);

        try {
            return inertia('Penilaian/Revision', $revisionService->buildPagePayload($record));
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('error', $exception->getMessage());
        }
    }

    public function submitRevision(
        SubmitAppraisalRevisionBatchRequest $request,
        int $id,
        AppraisalRequestRevisionSubmissionService $revisionService
    ) {
        /** @var AppraisalRequest $record */
        $record = $this->workflowService->resolveUserAppraisalRequest($request, $id);

        try {
            $revisionService->submitOpenBatch(
                $record,
                (int) $request->user()->id,
                $request->replacementFiles(),
                $request->fieldValues()
            );

            $this->notifyAdmins(
                $request,
                $record,
                'Revisi data/dokumen dikirim user',
                ($record->request_number ?? ('#' . $record->id)) . ' mengirim ulang revisi data atau dokumen untuk ditinjau kembali.',
                'heroicon-o-arrow-up-tray'
            );

            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('success', 'Revisi berhasil dikirim. Admin akan meninjau ulang permohonan Anda.');
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('appraisal.revisions.page', ['id' => $record->id])
                ->with('error', $exception->getMessage());
        }
    }

    public function offerPage(CustomerAccessRequest $request, int $id, AppraisalService $appraisalService)
    {
        $userId = $request->user()->id;
        $payload = $appraisalService->buildShowPayload($userId, $id);

        return inertia('Penilaian/Offer', $payload);
    }

    public function acceptOffer(CustomerAccessRequest $request, int $id)
    {
        $record = $this->workflowService->resolveUserAppraisalRequest($request, $id);

        try {
            $this->workflowService->acceptOffer($record, (int) $request->user()->id);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('appraisal.offer.page', ['id' => $record->id])
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('appraisal.contract.page', ['id' => $record->id])
            ->with('success', 'Penawaran disetujui. Lanjutkan tanda tangan kontrak.');
    }

    public function submitOfferNegotiation(SubmitOfferNegotiationRequest $request, int $id)
    {
        $record = $this->workflowService->resolveUserAppraisalRequest($request, $id);
        $data = $request->validated();
        try {
            $round = $this->workflowService->submitOfferNegotiation(
                $record,
                (int) $request->user()->id,
                isset($data['expected_fee']) ? (int) $data['expected_fee'] : null,
                (string) $data['reason'],
            );
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('appraisal.offer.page', ['id' => $record->id])
                ->with('error', $exception->getMessage());
        }

        $this->notifyAdmins(
            $request,
            $record,
            'Keberatan fee baru',
            ($record->request_number ?? ('#' . $record->id)) . " mengajukan negosiasi putaran {$round}.",
            'heroicon-o-hand-raised'
        );

        return redirect()
            ->route('appraisal.offer.page', ['id' => $record->id])
            ->with('success', "Keberatan fee putaran {$round} berhasil dikirim.");
    }

    public function selectOffer(SelectOfferRequest $request, int $id)
    {
        $record = $this->workflowService->resolveUserAppraisalRequest($request, $id);
        $data = $request->validated();
        try {
            $this->workflowService->selectOffer(
                $record,
                (int) $request->user()->id,
                (int) $data['selected_fee'],
                isset($data['reason']) ? (string) $data['reason'] : null,
            );
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('appraisal.offer.page', ['id' => $record->id])
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('appraisal.contract.page', ['id' => $record->id])
            ->with('success', 'Pilihan fee disimpan. Lanjutkan ke proses tanda tangan kontrak.');
    }

    public function cancelOffer(CancelOfferRequest $request, int $id)
    {
        return redirect()
            ->route('appraisal.show', ['id' => $id])
            ->with('error', 'Pembatalan langsung tidak tersedia. Gunakan fitur Ajukan Pembatalan dari halaman detail request.');
    }

    public function contractSignaturePage(CustomerAccessRequest $request, int $id, AppraisalService $appraisalService)
    {
        $record = $this->workflowService->resolveUserAppraisalRequest($request, $id);

        $status = $record->status?->value ?? $record->status;
        if (! $this->workflowService->isContractAccessibleStatus((string) $status)) {
            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('error', 'Kontrak belum siap untuk ditandatangani.');
        }

        $payload = $appraisalService->buildShowPayload($request->user()->id, $id);

        return inertia('Penilaian/ContractSign', $payload);
    }

    public function signContract(
        SignContractRequest $request,
        int $id,
        AppraisalService $appraisalService
    )
    {
        $record = $this->workflowService->resolveUserAppraisalRequest($request, $id);

        $request->validated();

        try {
            $this->workflowService->signContract($request, $record, $appraisalService);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('error', $exception->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return redirect()
                ->route('appraisal.contract.page', ['id' => $record->id])
                ->with('error', 'Gagal memproses tanda tangan digital. Silakan coba lagi.');
        }

        return redirect()
            ->route('appraisal.payment.page', ['id' => $record->id])
            ->with('success', 'Kontrak berhasil ditandatangani. Lanjutkan ke proses pembayaran.');
    }

    public function approveMarketPreview(
        CustomerAccessRequest $request,
        int $id,
        AppraisalMarketPreviewService $previewService
    ) {
        $record = $this->workflowService->resolveUserAppraisalRequest($request, $id);
        $oldStatus = $record->status?->label() ?? 'Preview Kajian Siap';

        try {
            $previewService->approvePreview($record);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('appraisal.market-preview.page', ['id' => $record->id])
                ->with('error', $exception->getMessage());
        }

        $freshRecord = $record->fresh(['user']);
        $requestNumber = $freshRecord->request_number ?? ('REQ-' . $freshRecord->id);

        if ($freshRecord->user) {
            $freshRecord->user->notify(new AppraisalStatusUpdated(
                appraisalId: (int) $freshRecord->id,
                requestNumber: (string) $requestNumber,
                oldStatus: $oldStatus,
                newStatus: AppraisalStatusEnum::ReportPreparation->label(),
            ));
        }

        $this->notifyAdmins(
            $request,
            $freshRecord,
            'Preview kajian disetujui customer',
            "{$requestNumber} disetujui customer dan masuk tahap persiapan laporan final.",
            'heroicon-o-document-check'
        );

        return redirect()
            ->route('appraisal.show', ['id' => $freshRecord->id])
            ->with('success', 'Preview hasil kajian disetujui. Admin sedang menyiapkan laporan final.');
    }

    public function submitMarketPreviewAppeal(
        SubmitMarketPreviewAppealRequest $request,
        int $id,
        AppraisalMarketPreviewService $previewService
    ) {
        $record = $this->workflowService->resolveUserAppraisalRequest($request, $id);
        $validated = $request->validated();

        try {
            $previewService->submitAppeal($record, (string) $validated['reason']);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('appraisal.market-preview.page', ['id' => $record->id])
                ->with('error', $exception->getMessage());
        }

        $freshRecord = $record->fresh(['user']);
        $requestNumber = $freshRecord->request_number ?? ('REQ-' . $freshRecord->id);

        if ($freshRecord->user) {
            $freshRecord->user->notify(new AppraisalStatusUpdated(
                appraisalId: (int) $freshRecord->id,
                requestNumber: (string) $requestNumber,
                oldStatus: AppraisalStatusEnum::PreviewReady->label(),
                newStatus: AppraisalStatusEnum::ValuationOnProgress->label(),
            ));
        }

        $reviewerUrl = route('reviewer.reviews.show', ['review' => $freshRecord->id]);
        $reviewerMessage = "{$requestNumber} menerima banding customer dan kembali ke antrian valuasi.";
        $this->notifyAdmins(
            $request,
            $freshRecord,
            'Banding preview diajukan customer',
            $reviewerMessage,
            'heroicon-o-exclamation-circle'
        );

        $reviewers = User::query()->role('Reviewer')->get();
        if ($reviewers->isNotEmpty()) {
            Notification::send(
                $reviewers,
                new AdminActionNotification(
                    'Banding preview customer',
                    $reviewerMessage,
                    $reviewerUrl,
                    'Lihat Review',
                    'heroicon-o-arrow-path'
                )
            );
        }

        return redirect()
            ->route('appraisal.show', ['id' => $freshRecord->id])
            ->with('success', 'Banding berhasil dikirim. Reviewer akan memperbarui hasil preview Anda.');
    }

    public function downloadContractPdf(CustomerAccessRequest $request, int $id, AppraisalService $appraisalService)
    {
        $record = $this->workflowService->resolveUserAppraisalRequest($request, $id);

        $status = $record->status?->value ?? $record->status;
        if (! $this->workflowService->isContractAccessibleStatus((string) $status)) {
            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('error', 'Dokumen kontrak belum tersedia pada status saat ini.');
        }

        $doc = $appraisalService->buildContractDocumentPayload($record);
        $requestNumber = preg_replace('/[^A-Za-z0-9\-_.]/', '-', (string) ($record->request_number ?? ('REQ-' . $record->id)));
        $fileName = "Penawaran-{$requestNumber}.pdf";

        $signedPdfPath = data_get($doc, 'signature.signed_pdf_path');
        if (is_string($signedPdfPath) && $signedPdfPath !== '' && Storage::disk('public')->exists($signedPdfPath)) {
            return Storage::disk('public')->download($signedPdfPath, "Penawaran-Tertandatangani-{$requestNumber}.pdf");
        }

        return Pdf::loadView('pdfs.appraisal-contract-offer', [
            'doc' => $doc,
        ])
            ->setPaper('a4', 'portrait')
            ->download($fileName);
    }

    /**
     * POST handler: user accepts consent. Store log + set session flag.
     *
     * Route suggestion:
     *   Route::post('/buat-permohonan/consent', [AppraisalController::class, 'acceptConsent'])->name('appraisal.consent.accept');
     */
    public function acceptConsent(CustomerAccessRequest $request, AppraisalService $appraisalService)
    {
        $appraisalService->acceptConsent($request);

        return redirect()->route('appraisal.create');
    }

    /**
     * POST handler: user declines consent -> back to list.
     *
     * Route suggestion:
     *   Route::post('/buat-permohonan/consent/decline', [AppraisalController::class, 'declineConsent'])->name('appraisal.consent.decline');
     */
   public function declineConsent(CustomerAccessRequest $request, AppraisalService $appraisalService)
    {
        $appraisalService->declineConsent($request);

        return redirect()->route('appraisal.list');
    }

    private function notifyAdmins(
        Request $request,
        AppraisalRequest $record,
        string $title,
        string $body,
        string $icon = 'heroicon-o-bell-alert'
    ): void {
        $url = route('admin.appraisal-requests.show', ['appraisalRequest' => $record->id]);

        $this->adminNotificationService->notifyAdmins(
            $title,
            $body,
            $url,
            $icon,
            $request->user()?->id,
        );
    }
}
