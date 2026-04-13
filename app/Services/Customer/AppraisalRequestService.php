<?php

namespace App\Services\Customer;

use App\Enums\AppraisalStatusEnum;
use App\Enums\ValuationObjectiveEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Notifications\AppraisalRequestCreated;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates appraisal requests, assets, files, and related notifications.
 */
class AppraisalRequestService
{
    public function __construct(
        private readonly AppraisalRequestSubmitterResolver $submitterResolver,
        private readonly GuidelineSetResolver $guidelineSetResolver,
        private readonly ConsentSnapshotResolver $consentSnapshotResolver,
        private readonly ReportDeliverySnapshotResolver $reportDeliverySnapshotResolver,
        private readonly AppraisalAssetPayloadNormalizer $assetPayloadNormalizer,
        private readonly AppraisalAssetFileStorage $assetFileStorage,
        private readonly AdminNotificationService $adminNotificationService,
    ) {
    }

    public function createFromRequest(Request $request): AppraisalRequest
    {
        $validated = method_exists($request, 'validated')
            ? $request->validated()
            : $request->all();

        $submitter = $this->submitterResolver->resolve($request);

        $format = 'both';
        $copies = 1;
        $reportTypeInput = $validated['report_type'] ?? 'terinci';
        $reportType = in_array($reportTypeInput, ['terinci', 'singkat'], true)
            ? (string) $reportTypeInput
            : 'terinci';

        // Kolom `purpose` masih wajib di schema, jadi set default aman.
        $purpose = $validated['purpose'] ?? 'jual_beli';
        $clientNameInput = trim((string) ($validated['client_name'] ?? ''));
        $clientName = $clientNameInput !== '' ? $clientNameInput : ($submitter?->name ?? null);
        $guidelineSetId = $this->guidelineSetResolver->resolveId();
        $consentSnapshot = $this->consentSnapshotResolver->resolve($request, $submitter);

        if (! $guidelineSetId) {
            throw ValidationException::withMessages([
                'guideline_set_id' => 'Guideline acuan belum tersedia. Aktifkan guideline terlebih dahulu.',
            ]);
        }

        $deliverySnapshot = $this->reportDeliverySnapshotResolver->resolve(
            $submitter,
            true
        );

        $appraisalRequest = DB::transaction(function () use ($request, $validated, $format, $copies, $reportType, $purpose, $clientName, $submitter, $guidelineSetId, $consentSnapshot, $deliverySnapshot) {

            $appraisalRequest = AppraisalRequest::create([
                'user_id' => $submitter?->getKey() ?? Auth::id(),
                'guideline_set_id' => $guidelineSetId,
                'purpose' => $purpose,
                'valuation_objective' => ValuationObjectiveEnum::KajianNilaiPasarRange,
                'client_name' => $clientName,
                'sertifikat_on_hand_confirmed' => (bool) ($validated['sertifikat_on_hand_confirmed'] ?? false),
                'certificate_not_encumbered_confirmed' => (bool) ($validated['certificate_not_encumbered_confirmed'] ?? false),
                'certificate_statements_accepted_at' => now(),
                'certificate_statement_ip' => (string) $request->ip(),
                'certificate_statement_user_agent' => substr((string) $request->userAgent(), 0, 255),
                'report_type' => $reportType,
                'report_format' => $format,
                'physical_copies_count' => $copies,
                'report_delivery_address' => $deliverySnapshot['address'],
                'report_delivery_recipient_name' => $deliverySnapshot['recipient_name'],
                'report_delivery_recipient_phone' => $deliverySnapshot['recipient_phone'],
                'requested_at' => now(),
                'consent_accepted_at' => $consentSnapshot['accepted_at'],
                'consent_version' => $consentSnapshot['version'],
                'consent_hash' => $consentSnapshot['hash'],
                'consent_ip' => $consentSnapshot['ip'],
                'consent_user_agent' => $consentSnapshot['user_agent'],
                'status' => AppraisalStatusEnum::Submitted,
            ]);

            $baseDir = "appraisal-requests/{$appraisalRequest->id}";

            $assetsPayload = $validated['assets'] ?? [];

            foreach ($assetsPayload as $i => $row) {
                $preparedAsset = $this->assetPayloadNormalizer->prepare($row);
                $address = $preparedAsset['address'];
                $mapsLink = $preparedAsset['maps_link'];
                $lat = $preparedAsset['coordinates_lat'];
                $lng = $preparedAsset['coordinates_lng'];

                if (! $address) {
                    throw ValidationException::withMessages([
                        "assets.$i.address" => "Alamat aset wajib diisi.",
                    ]);
                }

                if (($lat === null || $lng === null) && ! $mapsLink) {
                    throw ValidationException::withMessages([
                        "assets.$i.location" => "Lokasi wajib diisi (koordinat atau link Google Maps).",
                    ]);
                }

                $asset = AppraisalAsset::create(
                    $this->assetPayloadNormalizer->toModelAttributes((int) $appraisalRequest->id, $preparedAsset)
                );

                $assetDir = "$baseDir/assets/{$asset->id}";
                $this->assetFileStorage->storeForAsset(
                    $request,
                    $i,
                    $asset,
                    (bool) $preparedAsset['has_building'],
                    $assetDir
                );
            }

            return $appraisalRequest;
        });

        if ($submitter) {
            $submitter->notify(
                new AppraisalRequestCreated(
                    $appraisalRequest->id,
                    $appraisalRequest->request_number ?? null
                )
            );
        }

        $adminUsers = $this->adminNotificationService->recipients(Auth::id());

        if ($adminUsers->isNotEmpty()) {
            $requestNumber = $appraisalRequest->request_number ?? ('#' . $appraisalRequest->id);
            $creatorName = $request->user()?->name ?? 'User';
            $url = route('admin.appraisal-requests.show', ['appraisalRequest' => $appraisalRequest->id]);

            $this->adminNotificationService->notifyAdmins(
                'Permohonan penilaian baru',
                "{$requestNumber} dibuat oleh {$creatorName}.",
                $url,
                'heroicon-o-clipboard-document-check',
                Auth::id(),
            );
        }

        return $appraisalRequest;
    }
}
