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
use App\Services\Revisions\AppraisalRequestRevisionSubmissionService;
use App\Services\Workflow\AppraisalMarketPreviewService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles appraisal request pages and consent flows.
 */
class AppraisalController extends Controller
{
    private const MAX_NEGOTIATION_ROUNDS = 3;

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
        if (! $this->hasReadyBillingProfile($request->user())) {
            return redirect()
                ->route('profile.edit')
                ->with('error', 'Lengkapi profil billing terlebih dahulu sebelum membuat permohonan penilaian.');
        }

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
        $record = $this->resolveUserAppraisalRequest($request, $id);

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

        app(AdminNotificationService::class)->notifyAdmins(
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
        $record = $this->resolveUserAppraisalRequest($request, $id);

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
        $record = $this->resolveUserAppraisalRequest($request, $id);

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
        $record = $this->resolveUserAppraisalRequest($request, $id);
        $status = $this->getStatusValue($record);
        if ($status !== AppraisalStatusEnum::OfferSent->value) {
            return redirect()
                ->route('appraisal.offer.page', ['id' => $record->id])
                ->with('error', 'Penawaran belum dapat disetujui pada status saat ini.');
        }

        if (empty($record->contract_number) || empty($record->fee_total)) {
            return redirect()
                ->route('appraisal.offer.page', ['id' => $record->id])
                ->with('error', 'Data penawaran belum lengkap. Hubungi admin untuk pembaruan penawaran.');
        }

        $record->update([
            'status' => AppraisalStatusEnum::WaitingSignature,
            'contract_status' => ContractStatusEnum::WaitingSignature,
        ]);

        $record->offerNegotiations()->create([
            'user_id' => $request->user()->id,
            'action' => 'accept_offer',
            'round' => $this->countNegotiationRounds($record),
            'offered_fee' => $record->fee_total,
            'selected_fee' => $record->fee_total,
            'meta' => ['flow' => 'direct_accept'],
        ]);

        return redirect()
            ->route('appraisal.contract.page', ['id' => $record->id])
            ->with('success', 'Penawaran disetujui. Lanjutkan tanda tangan kontrak.');
    }

    public function submitOfferNegotiation(SubmitOfferNegotiationRequest $request, int $id)
    {
        $record = $this->resolveUserAppraisalRequest($request, $id);
        $status = $this->getStatusValue($record);

        if ($status !== AppraisalStatusEnum::OfferSent->value) {
            return redirect()
                ->route('appraisal.offer.page', ['id' => $record->id])
                ->with('error', 'Negosiasi hanya dapat diajukan saat penawaran berstatus dikirim.');
        }

        if (empty($record->contract_number) || empty($record->fee_total)) {
            return redirect()
                ->route('appraisal.offer.page', ['id' => $record->id])
                ->with('error', 'Data penawaran belum lengkap. Hubungi admin untuk pembaruan penawaran.');
        }

        $roundsUsed = $this->countNegotiationRounds($record);
        if ($roundsUsed >= self::MAX_NEGOTIATION_ROUNDS) {
            return redirect()
                ->route('appraisal.offer.page', ['id' => $record->id])
                ->with('error', 'Batas negosiasi maksimal 3 putaran telah tercapai.');
        }

        $data = $request->validated();

        $round = $roundsUsed + 1;

        $record->offerNegotiations()->create([
            'user_id' => $request->user()->id,
            'action' => 'counter_request',
            'round' => $round,
            'offered_fee' => $record->fee_total,
            'expected_fee' => $data['expected_fee'] ?? null,
            'reason' => trim((string) $data['reason']),
            'meta' => [
                'status_before' => $status,
                'contract_status_before' => $record->contract_status?->value ?? $record->contract_status,
            ],
        ]);

        $record->update([
            'status' => AppraisalStatusEnum::WaitingOffer,
            'contract_status' => ContractStatusEnum::Negotiation,
        ]);

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
        $record = $this->resolveUserAppraisalRequest($request, $id);
        $status = $this->getStatusValue($record);

        if ($status !== AppraisalStatusEnum::OfferSent->value) {
            return redirect()
                ->route('appraisal.offer.page', ['id' => $record->id])
                ->with('error', 'Pemilihan penawaran hanya dapat dilakukan saat status penawaran aktif.');
        }

        $roundsUsed = $this->countNegotiationRounds($record);
        if ($roundsUsed < self::MAX_NEGOTIATION_ROUNDS) {
            return redirect()
                ->route('appraisal.offer.page', ['id' => $record->id])
                ->with('error', 'Pemilihan akhir penawaran tersedia setelah 3 putaran negosiasi.');
        }

        $data = $request->validated();

        $selectedFee = (int) $data['selected_fee'];
        $offeredFees = $this->getOfferedFeeOptions($record);

        if (! in_array($selectedFee, $offeredFees, true)) {
            return redirect()
                ->route('appraisal.offer.page', ['id' => $record->id])
                ->with('error', 'Fee terpilih tidak termasuk dalam riwayat penawaran yang tersedia.');
        }

        $record->offerNegotiations()->create([
            'user_id' => $request->user()->id,
            'action' => 'accept_offer',
            'round' => $roundsUsed,
            'offered_fee' => $record->fee_total,
            'selected_fee' => $selectedFee,
            'reason' => isset($data['reason']) ? trim((string) $data['reason']) : null,
            'meta' => ['flow' => 'offer_selection_after_limit'],
        ]);

        $record->update([
            'fee_total' => $selectedFee,
            'status' => AppraisalStatusEnum::WaitingSignature,
            'contract_status' => ContractStatusEnum::WaitingSignature,
        ]);

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
        $record = AppraisalRequest::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $status = $record->status?->value ?? $record->status;
        if (! $this->isContractAccessibleStatus($status)) {
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
        $record = $this->resolveUserAppraisalRequest($request, $id);

        $status = $this->getStatusValue($record);
        if ($status !== AppraisalStatusEnum::WaitingSignature->value) {
            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('error', 'Status saat ini tidak dapat menandatangani kontrak.');
        }

        $request->validated();

        try {
            $snapshot = $this->createSignedContractSnapshot($request, $record, $appraisalService);
        } catch (\Throwable $e) {
            report($e);
            return redirect()
                ->route('appraisal.contract.page', ['id' => $record->id])
                ->with('error', 'Gagal memproses tanda tangan digital. Silakan coba lagi.');
        }

        $record->offerNegotiations()->create([
            'user_id' => $request->user()->id,
            'action' => 'contract_sign_mock',
            'round' => $this->countNegotiationRounds($record),
            'offered_fee' => $record->fee_total,
            'selected_fee' => $record->fee_total,
            'reason' => 'Mock digital signature (clickwrap).',
            'meta' => [
                'flow' => 'mock_contract_signature',
                'provider' => 'mock',
                'method' => 'clickwrap',
                'signature_id' => $snapshot['signature_id'],
                'signed_at' => $snapshot['signed_at'],
                'signed_by_name' => $snapshot['signed_by_name'],
                'signed_by_email' => $snapshot['signed_by_email'],
                'ip' => $snapshot['ip'],
                'user_agent' => $snapshot['user_agent'],
                'document_hash' => $snapshot['document_hash'],
                'signed_pdf_path' => $snapshot['signed_pdf_path'],
            ],
        ]);

        $record->update([
            'status' => AppraisalStatusEnum::ContractSigned,
            'contract_status' => ContractStatusEnum::ContractSigned,
        ]);

        return redirect()
            ->route('appraisal.payment.page', ['id' => $record->id])
            ->with('success', 'Kontrak berhasil ditandatangani. Lanjutkan ke proses pembayaran.');
    }

    public function approveMarketPreview(
        CustomerAccessRequest $request,
        int $id,
        AppraisalMarketPreviewService $previewService
    ) {
        $record = $this->resolveUserAppraisalRequest($request, $id);
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
        $record = $this->resolveUserAppraisalRequest($request, $id);
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
        $record = AppraisalRequest::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $status = $record->status?->value ?? $record->status;
        if (! $this->isContractAccessibleStatus($status)) {
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

    private function resolveUserAppraisalRequest(Request $request, int $id): AppraisalRequest
    {
        return AppraisalRequest::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    private function getStatusValue(AppraisalRequest $record): string
    {
        return $record->status?->value ?? (string) $record->status;
    }

    private function countNegotiationRounds(AppraisalRequest $record): int
    {
        return (int) $record->offerNegotiations()
            ->where('action', 'counter_request')
            ->count();
    }

    private function isContractAccessibleStatus(string $status): bool
    {
        return in_array($status, [
            AppraisalStatusEnum::WaitingSignature->value,
            AppraisalStatusEnum::ContractSigned->value,
            AppraisalStatusEnum::ValuationOnProgress->value,
            AppraisalStatusEnum::ValuationCompleted->value,
            AppraisalStatusEnum::PreviewReady->value,
            AppraisalStatusEnum::ReportPreparation->value,
            AppraisalStatusEnum::ReportReady->value,
            AppraisalStatusEnum::Completed->value,
        ], true);
    }

    private function hasReadyBillingProfile(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return filled($user->phone_number)
            && filled($user->billing_recipient_name)
            && filled($user->billing_address_detail);
    }

    /**
     * @return array<int, int>
     */
    private function getOfferedFeeOptions(AppraisalRequest $record): array
    {
        $fees = $record->offerNegotiations()
            ->where('action', 'counter_request')
            ->whereNotNull('offered_fee')
            ->pluck('offered_fee')
            ->map(fn ($fee): int => (int) $fee)
            ->values();

        if ($record->fee_total !== null) {
            $fees->push((int) $record->fee_total);
        }

        return $fees->unique()->values()->all();
    }

    private function createSignedContractSnapshot(
        Request $request,
        AppraisalRequest $record,
        AppraisalService $appraisalService
    ): array {
        $signedAt = now();
        $signatureId = (string) Str::uuid();
        $signerName = (string) ($request->user()?->name ?? '-');
        $signerEmail = (string) ($request->user()?->email ?? '-');
        $userAgent = substr((string) $request->userAgent(), 0, 255);
        $ipAddress = (string) $request->ip();

        $doc = $appraisalService->buildContractDocumentPayload($record);
        $doc['accepted_at'] = $signedAt->toDateTimeString();
        $doc['signature'] = array_merge((array) ($doc['signature'] ?? []), [
            'is_signed' => true,
            'signed_at' => $signedAt->toDateTimeString(),
            'signed_by_name' => $signerName,
            'signed_by_email' => $signerEmail,
            'signature_id' => $signatureId,
            'method' => 'clickwrap',
            'provider' => 'mock',
        ]);

        $pdfBinary = Pdf::loadView('pdfs.appraisal-contract-offer', [
            'doc' => $doc,
        ])
            ->setPaper('a4', 'portrait')
            ->output();

        $documentHash = 'sha256:' . hash('sha256', $pdfBinary);
        $requestNumber = preg_replace('/[^A-Za-z0-9\-_.]/', '-', (string) ($record->request_number ?? ('REQ-' . $record->id)));
        $storedName = "signed-contract-{$requestNumber}-{$signedAt->format('YmdHis')}.pdf";
        $storedPath = "appraisal-requests/{$record->id}/contracts/{$storedName}";

        Storage::disk('public')->put($storedPath, $pdfBinary);

        $record->files()->create([
            'type' => 'contract_signed_pdf',
            'path' => $storedPath,
            'original_name' => "Penawaran-Tertandatangani-{$requestNumber}.pdf",
            'mime' => 'application/pdf',
            'size' => strlen($pdfBinary),
        ]);

        return [
            'signature_id' => $signatureId,
            'signed_at' => $signedAt->toIso8601String(),
            'signed_by_name' => $signerName,
            'signed_by_email' => $signerEmail,
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
            'document_hash' => $documentHash,
            'signed_pdf_path' => $storedPath,
        ];
    }

    private function notifyAdmins(
        Request $request,
        AppraisalRequest $record,
        string $title,
        string $body,
        string $icon = 'heroicon-o-bell-alert'
    ): void {
        $adminUsers = $this->resolveAdminUsers($request);
        if ($adminUsers->isEmpty()) {
            return;
        }

        $url = route('admin.appraisal-requests.show', ['appraisalRequest' => $record->id]);

        app(AdminNotificationService::class)->notifyAdmins(
            $title,
            $body,
            $url,
            $icon,
            $request->user()?->id,
        );
    }

    private function resolveAdminUsers(Request $request)
    {
        return app(AdminNotificationService::class)->recipients($request->user()?->id);
    }
}
