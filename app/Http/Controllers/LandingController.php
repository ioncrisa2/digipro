<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\Testimonial;
use App\Models\Feature;
use App\Models\LandingMediaSetting;
use App\Models\PrivacyPolicy;
use App\Models\TermsDocument;
use Illuminate\Support\Facades\Storage;

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
                'image_url' => filled($feature->image_path) ? Storage::disk('public')->url($feature->image_path) : null,
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
                'quote' => $this->plainText($testimonial->quote),
                'photo_url' => filled($testimonial->photo_path) ? Storage::disk('public')->url($testimonial->photo_path) : null,
            ])
            ->values()
            ->all();

        $heroMedia = LandingMediaSetting::query()
            ->where('key', 'hero_background')
            ->first();

        $platformPreviewImages = collect([1, 2, 3])
            ->map(function (int $slot): ?string {
                $setting = LandingMediaSetting::query()
                    ->where('key', 'platform_preview_slide_' . $slot)
                    ->first();

                return filled($setting?->file_path) ? Storage::disk('public')->url($setting->file_path) : null;
            })
            ->all();

        return inertia('Landing/LandingPage', [
            'features' => $features,
            'faqs' => $faqs,
            'testimonials' => $testimonials,
            'heroBackgroundUrl' => filled($heroMedia?->file_path) ? Storage::disk('public')->url($heroMedia->file_path) : null,
            'platformPreviewImages' => $platformPreviewImages,
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

    private function plainText(?string $value): string
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text);
    }
}
