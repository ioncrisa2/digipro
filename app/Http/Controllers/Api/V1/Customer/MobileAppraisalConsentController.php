<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\MobileAppraisalConsentRequest;
use App\Services\Customer\MobileAppraisalDraftService;
use Illuminate\Http\JsonResponse;

class MobileAppraisalConsentController extends Controller
{
    public function __invoke(
        MobileAppraisalConsentRequest $request,
        MobileAppraisalDraftService $service,
    ): JsonResponse {
        $consent = $service->acceptConsent(
            $request->user(),
            $request,
            (int) $request->validated('document_id'),
            (string) $request->validated('hash'),
        );

        return response()->json([
            'data' => [
                'document_id' => $consent->consent_document_id,
                'version' => $consent->version,
                'hash' => $consent->hash,
                'accepted_at' => $consent->accepted_at?->toIso8601String(),
            ],
            'message' => 'Persetujuan berhasil disimpan.',
        ]);
    }
}
