<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\ContactMessage;
use App\Models\TermsDocument;
use App\Models\PrivacyPolicy;
use App\Models\Faq;
use App\Models\Testimonial;
use App\Models\Feature;

/**
 * Handles landing pages, public content, and contact form submissions.
 */
class LandingController extends Controller
{
    public function index()
    {
        $features = Feature::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($feature) => [
                'icon' => $feature->icon,
                'title' => $feature->title,
                'description' => $feature->description,
            ])
            ->values()
            ->all();

        $faqs = Faq::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn ($faq) => [
                'value' => 'faq-' . $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])
            ->values()
            ->all();

        $testimonials = Testimonial::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(6)
            ->get()
            ->map(fn ($testimonial) => [
                'name' => $testimonial->name,
                'role' => $testimonial->role,
                'quote' => $testimonial->quote,
            ])
            ->values()
            ->all();

        return inertia('Landing/LandingPage', [
            'features' => $features,
            'faqs' => $faqs,
            'testimonials' => $testimonials,
        ]);
    }

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

    public function contact()
    {
        return inertia('Landing/ContactPage');
    }

    public function storeContact(ContactRequest $request)
    {
        if ($request->filled('_trap')) {
            abort(422); // kemungkinan bot
        }

        $validated = $request->safe()->except(['_trap']);

        ContactMessage::create([
            ...$validated,
            'status'     => 'new',
            'source'     => 'landing-contact',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Your message has been saved. Our team will contact you soon.');
    }
}
