<?php

namespace App\Services\Customer;

use App\Models\AppraisalUserConsent;
use App\Models\User;
use Illuminate\Http\Request;

class ConsentSnapshotResolver
{
    public function resolve(Request $request, ?User $submitter): array
    {
        $sessionVersion = $request->session()->get('appraisal_consent.version');
        $sessionHash = $request->session()->get('appraisal_consent.hash');
        $sessionDocumentId = $request->session()->get('appraisal_consent.document_id');

        $consent = null;

        if ($submitter?->getKey()) {
            $consentQuery = AppraisalUserConsent::query()
                ->where('user_id', $submitter->getKey())
                ->latest('accepted_at');

            if ($sessionDocumentId) {
                $consent = (clone $consentQuery)
                    ->where('consent_document_id', $sessionDocumentId)
                    ->first();
            }

            if (! $consent && $sessionVersion && $sessionHash) {
                $consent = (clone $consentQuery)
                    ->where('version', $sessionVersion)
                    ->where('hash', $sessionHash)
                    ->first();
            }
        }

        return [
            'accepted_at' => $consent?->accepted_at ?? now(),
            'version' => $sessionVersion ?: ($consent?->version ?: null),
            'hash' => $sessionHash ?: ($consent?->hash ?: null),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ];
    }
}
