<?php

namespace App\Services\Customer;

use App\Models\AppraisalRequest;
use App\Models\AppraisalUserConsent;
use App\Models\ConsentDocument;
use App\Services\Customer\Payloads\AppraisalContractDocumentBuilder;
use App\Services\Customer\Payloads\CustomerAppraisalCreateBuilder;
use App\Services\Customer\Payloads\CustomerAppraisalDocumentBuilder;
use App\Services\Customer\Payloads\CustomerAppraisalIndexBuilder;
use App\Services\Customer\Payloads\CustomerAppraisalMarketPreviewBuilder;
use App\Services\Customer\Payloads\CustomerAppraisalShowBuilder;
use App\Services\Customer\Payloads\CustomerRepresentativeLetterBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Builds appraisal UI payloads and handles consent flows for users.
 */
class AppraisalService
{
    public function __construct(
        private readonly CustomerAppraisalIndexBuilder $indexBuilder,
        private readonly CustomerAppraisalCreateBuilder $createBuilder,
        private readonly CustomerAppraisalDocumentBuilder $documentBuilder,
        private readonly CustomerAppraisalShowBuilder $showBuilder,
        private readonly CustomerAppraisalMarketPreviewBuilder $marketPreviewBuilder,
        private readonly AppraisalContractDocumentBuilder $contractDocumentBuilder,
        private readonly CustomerRepresentativeLetterBuilder $representativeLetterBuilder,
    ) {
    }

    public function buildIndexPayload(int $userId, string $q, string $status, int $perPage = 10): array
    {
        return $this->indexBuilder->build($userId, $q, $status, $perPage);
    }

    public function buildCreatePayload(?int $provinceId, ?int $regencyId, ?int $districtId, bool $needsConsent, ?array $consentData): array
    {
        return $this->createBuilder->build($provinceId, $regencyId, $districtId, $needsConsent, $consentData);
    }

    public function buildDocumentsIndexPayload(int $userId): array
    {
        return $this->documentBuilder->buildIndexPayload($userId);
    }

    public function buildDocumentsShowPayload(int $userId, int $id): array
    {
        return $this->documentBuilder->buildShowPayload($userId, $id);
    }

    public function buildShowPayload(int $userId, int $id): array
    {
        return $this->showBuilder->build($userId, $id);
    }

    public function buildMarketPreviewPayload(int $userId, int $id): array
    {
        return $this->marketPreviewBuilder->build($userId, $id);
    }

    public function buildContractDocumentPayload(AppraisalRequest $record): array
    {
        return $this->contractDocumentBuilder->build($record);
    }

    public function buildRepresentativeLetterPayload(AppraisalRequest $record, array $signatureMeta = []): array
    {
        return $this->representativeLetterBuilder->build($record, $signatureMeta);
    }

    public function acceptConsent(Request $request): void
    {
        $doc = ConsentDocument::query()
            ->published()
            ->forCode('appraisal_request_consent')
            ->orderByDesc('published_at')
            ->firstOrFail();

        $request->session()->put('appraisal_consent.accepted', true);
        $request->session()->put('appraisal_consent.document_id', $doc->id);
        $request->session()->put('appraisal_consent.code', $doc->code);
        $request->session()->put('appraisal_consent.version', $doc->version);
        $request->session()->put('appraisal_consent.hash', $doc->hash);

        AppraisalUserConsent::create([
            'user_id' => auth()->id(),
            'consent_document_id' => $doc->id,
            'code' => $doc->code,
            'version' => $doc->version,
            'hash' => $doc->hash,
            'accepted_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }

    public function declineConsent(Request $request): void
    {
        $request->session()->forget([
            'appraisal_consent',
            'appraisal_consent_accepted',
            'appraisal_consent_document_id',
            'appraisal_consent_version',
            'appraisal_consent_hash',
        ]);
    }

    public function hasAcceptedLatestConsent(Request $request): bool
    {
        $props = $this->buildConsentProps();

        $version = Session::get('appraisal_consent.version');
        $hash = Session::get('appraisal_consent.hash');

        if ($version === null || $hash === null) {
            $legacyVersion = Session::get('appraisal_consent_version');
            $legacyHash = Session::get('appraisal_consent_hash');

            if ($legacyVersion !== null && $legacyHash !== null) {
                $version = $legacyVersion;
                $hash = $legacyHash;
                Session::put('appraisal_consent.version', $legacyVersion);
                Session::put('appraisal_consent.hash', $legacyHash);
            }
        }

        if ($version === $props['version'] && $hash === $props['hash']) {
            return true;
        }

        $accepted = DB::table('appraisal_user_consents')
            ->where('user_id', $request->user()->id)
            ->where('version', $props['version'])
            ->where('hash', $props['hash'])
            ->exists();

        if ($accepted) {
            Session::put('appraisal_consent.version', $props['version']);
            Session::put('appraisal_consent.hash', $props['hash']);
        }

        return $accepted;
    }

    public function buildConsentProps(): array
    {
        $doc = ConsentDocument::query()
            ->published()
            ->forCode('appraisal_request_consent')
            ->orderByDesc('published_at')
            ->firstOrFail();

        return [
            'document_id' => $doc->id,
            'version' => $doc->version,
            'hash' => $doc->hash,
            'title' => $doc->title,
            'sections' => $doc->sections,
            'checkbox_label' => $doc->checkbox_label
                ?? 'Saya telah membaca, memahami, dan menyetujui seluruh Persetujuan dan Disclaimer di atas.',
        ];
    }
}
