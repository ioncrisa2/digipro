<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Models\PrivacyPolicy;
use App\Models\TermsDocument;

class LegalController extends Controller
{
    public function policy()
    {
        $policy = PrivacyPolicy::active()
            ->orderByDesc('published_at')
            ->first();

        return inertia('Landing/PolicyPage', [
            'policyDocument' => $policy?->toPublicArray(),
        ]);
    }

    public function terms()
    {
        $terms = TermsDocument::active()
            ->orderByDesc('published_at')
            ->first();

        return inertia('Landing/TermsPage', [
            'termsDocument' => $terms?->toPublicArray(),
        ]);
    }
}
