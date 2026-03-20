<?php

namespace App\Http\Controllers;

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use Illuminate\Http\Request;
use App\Models\AppraisalRequest;
use App\Models\User;
use App\Services\AppraisalService;
use App\Services\AppraisalRequestService;
use App\Http\Requests\StoreAppraisalRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Handles appraisal request pages and consent flows.
 */
class AppraisalController extends Controller
{
    private const MAX_NEGOTIATION_ROUNDS = 3;

    public function index(Request $request, AppraisalService $appraisalService)
    {
        $userId = $request->user()->id;

        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', 'all');
        $payload = $appraisalService->buildIndexPayload($userId, $q, $status);

        return inertia('Penilaian/Index', array_merge($payload, [
            'filters' => [
                'q' => $q,
                'status' => $status,
            ],
        ]));
    }

    /**
     * Create new appraisal request
     * Now includes consent check inline - if user hasn't accepted latest consent,
     * we pass consent data to the form instead of redirecting
     */
    public function create(Request $request, AppraisalService $appraisalService)
    {
        $provinceId = $request->get('province_id');
        $regencyId = $request->get('regency_id');
        $districtId = $request->get('district_id');

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

    public function show(Request $request, int $id, AppraisalService $appraisalService)
    {
        $userId = $request->user()->id;
        $payload = $appraisalService->buildShowPayload($userId, $id);

        return inertia('Penilaian/Show', $payload);
    }

    public function offerPage(Request $request, int $id, AppraisalService $appraisalService)
    {
        $userId = $request->user()->id;
        $payload = $appraisalService->buildShowPayload($userId, $id);

        return inertia('Penilaian/Offer', $payload);
    }

    public function acceptOffer(Request $request, int $id)
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

    public function submitOfferNegotiation(Request $request, int $id)
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

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'expected_fee' => ['nullable', 'integer', 'min:0'],
        ]);

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

    public function selectOffer(Request $request, int $id)
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

        $data = $request->validate([
            'selected_fee' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

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

    public function cancelOffer(Request $request, int $id)
    {
        $record = $this->resolveUserAppraisalRequest($request, $id);
        $status = $this->getStatusValue($record);

        if (! in_array($status, [
            AppraisalStatusEnum::OfferSent->value,
            AppraisalStatusEnum::WaitingOffer->value,
        ], true)) {
            return redirect()
                ->route('appraisal.offer.page', ['id' => $record->id])
                ->with('error', 'Permohonan tidak dapat dibatalkan pada status saat ini.');
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $record->offerNegotiations()->create([
            'user_id' => $request->user()->id,
            'action' => 'cancel_request',
            'round' => $this->countNegotiationRounds($record),
            'offered_fee' => $record->fee_total,
            'reason' => isset($data['reason']) ? trim((string) $data['reason']) : null,
            'meta' => ['flow' => 'cancel_from_offer_page'],
        ]);

        $record->update([
            'status' => AppraisalStatusEnum::Cancelled,
            'contract_status' => ContractStatusEnum::Cancelled,
        ]);

        $this->notifyAdmins(
            $request,
            $record,
            'Permohonan dibatalkan oleh user',
            ($record->request_number ?? ('#' . $record->id)) . ' dibatalkan dari halaman penawaran.',
            'heroicon-o-x-circle'
        );

        return redirect()
            ->route('appraisal.show', ['id' => $record->id])
            ->with('success', 'Permohonan berhasil dibatalkan.');
    }

    public function contractSignaturePage(Request $request, int $id, AppraisalService $appraisalService)
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

    public function signContract(Request $request, int $id, AppraisalService $appraisalService)
    {
        $record = $this->resolveUserAppraisalRequest($request, $id);

        $status = $this->getStatusValue($record);
        if ($status !== AppraisalStatusEnum::WaitingSignature->value) {
            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('error', 'Status saat ini tidak dapat menandatangani kontrak.');
        }

        $request->validate([
            'agree_contract' => ['accepted'],
        ], [
            'agree_contract.accepted' => 'Anda harus menyetujui dokumen sebelum menandatangani kontrak.',
        ]);

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

    public function downloadContractPdf(Request $request, int $id, AppraisalService $appraisalService)
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
    public function acceptConsent(Request $request, AppraisalService $appraisalService)
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
   public function declineConsent(Request $request, AppraisalService $appraisalService)
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
            AppraisalStatusEnum::ReportReady->value,
            AppraisalStatusEnum::Completed->value,
        ], true);
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

        FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->actions([
                FilamentAction::make('view')
                    ->label('Lihat')
                    ->url($url)
                    ->markAsRead(),
            ])
            ->icon($icon)
            ->sendToDatabase($adminUsers, true);
    }

    private function resolveAdminUsers(Request $request)
    {
        $guardName = config('auth.defaults.guard', 'web');
        $configuredSuperAdmin = config('filament-shield.super_admin.enabled', true)
            ? config('filament-shield.super_admin.name', 'super_admin')
            : null;

        $roleCandidates = array_values(array_filter([
            $configuredSuperAdmin,
            'admin',
        ]));

        $existingRoleNames = Role::query()
            ->whereIn('name', $roleCandidates)
            ->where('guard_name', $guardName)
            ->pluck('name')
            ->values()
            ->all();

        if (empty($existingRoleNames)) {
            return collect();
        }

        return User::query()
            ->role($existingRoleNames, $guardName)
            ->whereKeyNot($request->user()->id)
            ->get();
    }
}
