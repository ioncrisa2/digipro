<?php

namespace App\Services\Admin;

use App\Models\AppraisalUserConsent;
use App\Models\ConsentDocument;
use App\Models\Faq;
use App\Models\Feature;
use App\Models\LandingMediaSetting;
use App\Models\PrivacyPolicy;
use App\Models\Testimonial;
use App\Models\TermsDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminLegalContentWorkspaceService
{
    public function faqsIndexPayload(array $filters): array
    {
        return $this->simpleContentIndexPayload(
            resource: ['key' => 'faqs', 'title' => 'FAQ', 'create_label' => 'Tambah FAQ'],
            filters: $filters,
            summary: [
                'total' => Faq::query()->count(),
                'active' => Faq::query()->where('is_active', true)->count(),
            ],
            records: Faq::query()
                ->when($filters['q'] !== '', fn ($query) => $query->where('question', 'like', '%' . $filters['q'] . '%'))
                ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
                ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Faq $faq) => [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'sort_order' => (int) $faq->sort_order,
                    'is_active' => (bool) $faq->is_active,
                    'edit_url' => route('admin.content.legal.faqs.edit', $faq),
                    'destroy_url' => route('admin.content.legal.faqs.destroy', $faq),
                ])
                ->values()
                ->all(),
            createUrl: route('admin.content.legal.faqs.create'),
            extra: [
                'reorderUrl' => route('admin.content.legal.faqs.reorder'),
            ],
        );
    }

    public function faqFormPayload(?Faq $faq = null): array
    {
        return [
            'question' => $faq?->question ?? '',
            'answer' => $faq?->answer ?? '',
            'sort_order' => (int) ($faq?->sort_order ?? 0),
            'is_active' => (bool) ($faq?->is_active ?? true),
            'id' => $faq?->id,
        ];
    }

    public function reorderFaqs(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            foreach (array_values($ids) as $index => $id) {
                Faq::query()->whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });
    }

    public function featuresIndexPayload(array $filters): array
    {
        return $this->simpleContentIndexPayload(
            resource: ['key' => 'features', 'title' => 'Fitur', 'create_label' => 'Tambah Fitur'],
            filters: $filters,
            summary: [
                'total' => Feature::query()->count(),
                'active' => Feature::query()->where('is_active', true)->count(),
            ],
            records: Feature::query()
                ->when($filters['q'] !== '', fn ($query) => $query->where('title', 'like', '%' . $filters['q'] . '%'))
                ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
                ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Feature $feature) => [
                    'id' => $feature->id,
                    'icon' => $feature->icon,
                    'title' => $feature->title,
                    'description' => $feature->description,
                    'image_url' => $this->publicUrl($feature->image_path),
                    'sort_order' => (int) $feature->sort_order,
                    'is_active' => (bool) $feature->is_active,
                    'edit_url' => route('admin.content.legal.features.edit', $feature),
                    'destroy_url' => route('admin.content.legal.features.destroy', $feature),
                ])
                ->values()
                ->all(),
            createUrl: route('admin.content.legal.features.create'),
            extra: [
                'heroMedia' => $this->heroMediaPayload(),
                'heroUploadUrl' => route('admin.content.legal.features.hero-background.update'),
                'platformPreviewMedia' => $this->platformPreviewMediaPayload(),
            ],
        );
    }

    public function featureFormPayload(?Feature $feature = null): array
    {
        return [
            'id' => $feature?->id,
            'icon' => $feature?->icon ?? '__none',
            'title' => $feature?->title ?? '',
            'description' => $feature?->description ?? '',
            'image_url' => $this->publicUrl($feature?->image_path),
            'sort_order' => (int) ($feature?->sort_order ?? 0),
            'is_active' => (bool) ($feature?->is_active ?? true),
        ];
    }

    public function featureIconOptions(): array
    {
        return [
            ['value' => 'TrendingUp', 'label' => 'TrendingUp'],
            ['value' => 'Zap', 'label' => 'Zap'],
            ['value' => 'ShieldCheck', 'label' => 'ShieldCheck'],
            ['value' => 'Smartphone', 'label' => 'Smartphone'],
            ['value' => 'CheckCircle2', 'label' => 'CheckCircle2'],
            ['value' => 'Star', 'label' => 'Star'],
        ];
    }

    public function storeFeature(array $validated, ?UploadedFile $image = null): void
    {
        Feature::query()->create([
            ...$validated,
            'image_path' => $image?->store('features', 'public'),
        ]);
    }

    public function updateFeature(Feature $feature, array $validated, ?UploadedFile $image = null): void
    {
        $imagePath = $feature->image_path;

        if ($image) {
            $newImagePath = $image->store('features', 'public');

            if (filled($imagePath) && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            $imagePath = $newImagePath;
        }

        $feature->update([
            ...$validated,
            'image_path' => $imagePath,
        ]);
    }

    public function destroyFeature(Feature $feature): void
    {
        if (filled($feature->image_path) && Storage::disk('public')->exists($feature->image_path)) {
            Storage::disk('public')->delete($feature->image_path);
        }

        $feature->delete();
    }

    public function updateHeroBackground(UploadedFile $image): void
    {
        $setting = LandingMediaSetting::query()->firstOrNew(['key' => 'hero_background']);
        $this->replaceLandingMedia($setting, $image, 'landing/hero');
    }

    public function updatePlatformPreview(int $slot, UploadedFile $image): void
    {
        abort_unless(in_array($slot, [1, 2, 3], true), 404);

        $setting = LandingMediaSetting::query()->firstOrNew(['key' => 'platform_preview_slide_' . $slot]);
        $this->replaceLandingMedia($setting, $image, 'landing/platform-preview');
    }

    public function testimonialsIndexPayload(array $filters): array
    {
        return $this->simpleContentIndexPayload(
            resource: ['key' => 'testimonials', 'title' => 'Testimoni', 'create_label' => 'Tambah Testimoni'],
            filters: $filters,
            summary: [
                'total' => Testimonial::query()->count(),
                'active' => Testimonial::query()->where('is_active', true)->count(),
            ],
            records: Testimonial::query()
                ->when($filters['q'] !== '', fn ($query) => $query->where('name', 'like', '%' . $filters['q'] . '%'))
                ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
                ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Testimonial $testimonial) => [
                    'id' => $testimonial->id,
                    'name' => $testimonial->name,
                    'role' => $testimonial->role,
                    'quote' => $testimonial->quote,
                    'photo_url' => $this->publicUrl($testimonial->photo_path),
                    'sort_order' => (int) $testimonial->sort_order,
                    'is_active' => (bool) $testimonial->is_active,
                    'edit_url' => route('admin.content.legal.testimonials.edit', $testimonial),
                    'destroy_url' => route('admin.content.legal.testimonials.destroy', $testimonial),
                ])
                ->values()
                ->all(),
            createUrl: route('admin.content.legal.testimonials.create'),
        );
    }

    public function testimonialFormPayload(?Testimonial $testimonial = null): array
    {
        return [
            'id' => $testimonial?->id,
            'name' => $testimonial?->name ?? '',
            'role' => $testimonial?->role ?? '',
            'quote' => $testimonial?->quote ?? '',
            'photo_url' => $this->publicUrl($testimonial?->photo_path),
            'sort_order' => (int) ($testimonial?->sort_order ?? 0),
            'is_active' => (bool) ($testimonial?->is_active ?? true),
        ];
    }

    public function storeTestimonial(array $validated, ?UploadedFile $photo = null): void
    {
        Testimonial::query()->create([
            ...$validated,
            'photo_path' => $photo?->store('testimonials', 'public'),
        ]);
    }

    public function updateTestimonial(Testimonial $testimonial, array $validated, ?UploadedFile $photo = null): void
    {
        $photoPath = $testimonial->photo_path;

        if ($photo) {
            $newPhotoPath = $photo->store('testimonials', 'public');

            if (filled($photoPath) && Storage::disk('public')->exists($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }

            $photoPath = $newPhotoPath;
        }

        $testimonial->update([
            ...$validated,
            'photo_path' => $photoPath,
        ]);
    }

    public function destroyTestimonial(Testimonial $testimonial): void
    {
        if (filled($testimonial->photo_path) && Storage::disk('public')->exists($testimonial->photo_path)) {
            Storage::disk('public')->delete($testimonial->photo_path);
        }

        $testimonial->delete();
    }

    public function legalDocumentsIndexPayload(array $filters, string $modelClass, string $routePrefix, string $documentType): array
    {
        $records = $modelClass::query()
            ->when($filters['q'] !== '', fn ($query) => $query->where('title', 'like', '%' . $filters['q'] . '%'))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest('updated_at')
            ->get()
            ->map(function (Model $document) use ($routePrefix): array {
                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'company' => $document->company,
                    'version' => $document->version,
                    'effective_since' => $document->effective_since?->toDateString(),
                    'is_active' => (bool) $document->is_active,
                    'published_at' => $document->published_at?->toIso8601String(),
                    'edit_url' => route($routePrefix . '.edit', $document),
                    'destroy_url' => route($routePrefix . '.destroy', $document),
                ];
            })
            ->values()
            ->all();

        return [
            'resource' => [
                'key' => $documentType,
                'title' => $documentType === 'terms' ? 'Terms' : 'Privacy Policy',
                'create_label' => $documentType === 'terms' ? 'Tambah Terms' : 'Tambah Privacy Policy',
            ],
            'filters' => $filters,
            'statusOptions' => $this->simpleStatusOptions(),
            'summary' => [
                'total' => $modelClass::query()->count(),
                'active' => $modelClass::query()->where('is_active', true)->count(),
            ],
            'records' => $records,
            'createUrl' => route($routePrefix . '.create'),
            'links' => $this->legalModuleLinks(),
        ];
    }

    public function legalDocumentFormPagePayload(?Model $document, string $mode, string $indexUrl, string $submitUrl): array
    {
        return [
            'resource' => [
                'key' => str_contains($indexUrl, '/terms') ? 'terms' : 'privacy',
                'title' => str_contains($indexUrl, '/terms') ? 'Terms' : 'Privacy Policy',
                'singular' => str_contains($indexUrl, '/terms') ? 'Terms' : 'Privacy Policy',
            ],
            'mode' => $mode,
            'record' => $this->legalDocumentFormPayload($document),
            'indexUrl' => $indexUrl,
            'submitUrl' => $submitUrl,
            'links' => $this->legalModuleLinks(),
        ];
    }

    public function consentDocumentsIndexPayload(array $filters): array
    {
        $records = ConsentDocument::query()
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('title', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('code', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('version', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->latest('updated_at')
            ->get()
            ->map(fn (ConsentDocument $document) => [
                'id' => $document->id,
                'code' => $document->code,
                'version' => $document->version,
                'title' => $document->title,
                'status' => $document->status,
                'hash' => $document->hash,
                'published_at' => $document->published_at?->toIso8601String(),
                'sections_count' => count((array) $document->sections),
                'edit_url' => route('admin.content.legal.consent.edit', $document),
                'destroy_url' => route('admin.content.legal.consent.destroy', $document),
                'publish_url' => route('admin.content.legal.consent.publish', $document),
                'can_edit' => $document->status === 'draft',
                'can_delete' => $document->status === 'draft',
                'can_publish' => $document->status === 'draft',
            ])
            ->values()
            ->all();

        return [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'published', 'label' => 'Published'],
                ['value' => 'archived', 'label' => 'Arsip'],
            ],
            'summary' => [
                'total' => ConsentDocument::query()->count(),
                'draft' => ConsentDocument::query()->where('status', 'draft')->count(),
                'published' => ConsentDocument::query()->where('status', 'published')->count(),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.legal.consent.create'),
            'links' => $this->legalModuleLinks(),
        ];
    }

    public function consentDocumentFormPagePayload(ConsentDocument $document, string $mode, string $submitUrl): array
    {
        return [
            'mode' => $mode,
            'record' => $this->consentDocumentFormPayload($document),
            'indexUrl' => route('admin.content.legal.consent.index'),
            'submitUrl' => $submitUrl,
            'links' => $this->legalModuleLinks(),
        ];
    }

    public function persistConsentDocument(ConsentDocument $document, array $validated): void
    {
        $document->code = $validated['code'];
        $document->version = $validated['version'];
        $document->title = $validated['title'];
        $document->status = $validated['status'];
        $document->checkbox_label = $validated['checkbox_label'] ?? null;
        $document->sections = $this->decodeConsentSections($validated['sections_json']);
        $document->hash = $document->exists ? $document->hash : str_repeat('0', 64);
        $document->created_by = $document->exists ? $document->created_by : auth()->id();
        $document->updated_by = auth()->id();
        $document->save();
    }

    public function publishConsentDocument(ConsentDocument $consentDocument): void
    {
        ConsentDocument::query()
            ->forCode($consentDocument->code)
            ->published()
            ->where('id', '!=', $consentDocument->id)
            ->update(['status' => 'archived']);

        $consentDocument->status = 'published';
        $consentDocument->published_at = now();
        $consentDocument->hash = ConsentDocument::computeHash($consentDocument->payloadForHash());
        $consentDocument->updated_by = auth()->id();
        $consentDocument->save();
    }

    public function appraisalUserConsentsIndexPayload(array $filters, int $perPage): array
    {
        $records = AppraisalUserConsent::query()
            ->with(['user', 'document'])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('code', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('version', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', '%' . $filters['q'] . '%')
                            ->orWhere('email', 'like', '%' . $filters['q'] . '%'));
                });
            })
            ->when($filters['code'] !== 'all', fn ($query) => $query->where('code', $filters['code']))
            ->latest('accepted_at')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (AppraisalUserConsent $consent) => [
            'id' => $consent->id,
            'user_name' => $consent->user?->name ?? '-',
            'user_email' => $consent->user?->email ?? '-',
            'document_title' => $consent->document?->title ?? '-',
            'code' => $consent->code,
            'version' => $consent->version,
            'accepted_at' => $consent->accepted_at?->toIso8601String(),
            'ip' => $consent->ip,
            'show_url' => route('admin.content.legal.user-consents.show', $consent),
        ]);

        return [
            'filters' => $filters,
            'codeOptions' => AppraisalUserConsent::query()
                ->distinct()
                ->orderBy('code')
                ->pluck('code')
                ->filter()
                ->values()
                ->map(fn (string $code) => ['value' => $code, 'label' => $code])
                ->all(),
            'records' => $this->paginatedRecordsPayload($records),
            'links' => $this->legalModuleLinks(),
        ];
    }

    public function appraisalUserConsentShowPayload(AppraisalUserConsent $appraisalUserConsent): array
    {
        $appraisalUserConsent->loadMissing(['user', 'document']);

        return [
            'record' => [
                'id' => $appraisalUserConsent->id,
                'user_name' => $appraisalUserConsent->user?->name ?? '-',
                'user_email' => $appraisalUserConsent->user?->email ?? '-',
                'document_title' => $appraisalUserConsent->document?->title ?? '-',
                'code' => $appraisalUserConsent->code,
                'version' => $appraisalUserConsent->version,
                'hash' => $appraisalUserConsent->hash,
                'accepted_at' => $appraisalUserConsent->accepted_at?->toIso8601String(),
                'ip' => $appraisalUserConsent->ip,
                'user_agent' => $appraisalUserConsent->user_agent,
            ],
            'indexUrl' => route('admin.content.legal.user-consents.index'),
            'links' => $this->legalModuleLinks(),
        ];
    }

    public function legalModuleLinks(): array
    {
        return [
            ['label' => 'FAQ', 'url' => route('admin.content.legal.faqs.index')],
            ['label' => 'Fitur', 'url' => route('admin.content.legal.features.index')],
            ['label' => 'Testimoni', 'url' => route('admin.content.legal.testimonials.index')],
            ['label' => 'Terms', 'url' => route('admin.content.legal.terms.index')],
            ['label' => 'Privacy', 'url' => route('admin.content.legal.privacy.index')],
            ['label' => 'Consent', 'url' => route('admin.content.legal.consent.index')],
            ['label' => 'Audit Consent', 'url' => route('admin.content.legal.user-consents.index')],
        ];
    }

    public function simpleStatusOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Semua Status'],
            ['value' => 'active', 'label' => 'Aktif'],
            ['value' => 'inactive', 'label' => 'Nonaktif'],
        ];
    }

    private function simpleContentIndexPayload(
        array $resource,
        array $filters,
        array $summary,
        array $records,
        string $createUrl,
        array $extra = [],
    ): array {
        return [
            'resource' => $resource,
            'filters' => $filters,
            'statusOptions' => $this->simpleStatusOptions(),
            'summary' => $summary,
            'records' => $records,
            'createUrl' => $createUrl,
            'links' => $this->legalModuleLinks(),
            ...$extra,
        ];
    }

    private function heroMediaPayload(): array
    {
        $setting = LandingMediaSetting::query()->where('key', 'hero_background')->first();

        return [
            'image_url' => $this->publicUrl($setting?->file_path),
        ];
    }

    private function platformPreviewMediaPayload(): array
    {
        return collect([1, 2, 3])
            ->map(function (int $slot): array {
                $setting = LandingMediaSetting::query()->where('key', 'platform_preview_slide_' . $slot)->first();

                return [
                    'slot' => $slot,
                    'label' => 'Slide ' . $slot,
                    'image_url' => $this->publicUrl($setting?->file_path),
                    'upload_url' => route('admin.content.legal.features.platform-preview.update', ['slot' => $slot]),
                ];
            })
            ->values()
            ->all();
    }

    private function legalDocumentFormPayload(?Model $document): array
    {
        return [
            'id' => $document?->id,
            'title' => $document?->title ?? '',
            'company' => $document?->company ?? '',
            'version' => $document?->version ?? '',
            'effective_since' => $document?->effective_since?->format('Y-m-d'),
            'content_html' => $document?->content_html ?? '',
            'is_active' => (bool) ($document?->is_active ?? false),
            'published_at' => $document?->published_at?->format('Y-m-d\\TH:i'),
        ];
    }

    private function consentDocumentFormPayload(ConsentDocument $document): array
    {
        return [
            'id' => $document->id,
            'code' => $document->code ?: 'appraisal_request_consent',
            'version' => $document->version ?? '',
            'title' => $document->title ?? '',
            'status' => $document->status ?? 'draft',
            'checkbox_label' => $document->checkbox_label ?: 'Saya telah membaca, memahami, dan menyetujui seluruh Persetujuan dan Disclaimer di atas.',
            'hash' => $document->hash,
            'published_at' => $document->published_at?->toIso8601String(),
            'sections_json' => $this->formatConsentSectionsJson($document->sections),
        ];
    }

    private function formatConsentSectionsJson(mixed $sections): string
    {
        if (! is_array($sections) || $sections === []) {
            return json_encode([
                [
                    'heading' => 'Section 1',
                    'lead' => null,
                    'items' => ['Isi persetujuan pertama'],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) json_encode($sections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function decodeConsentSections(?string $sectionsJson): array
    {
        $decoded = json_decode((string) $sectionsJson, true);
        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)->map(function ($section) {
            $items = collect($section['items'] ?? [])
                ->map(fn ($item) => is_array($item) ? ($item['text'] ?? null) : $item)
                ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                ->values()
                ->all();

            return [
                'heading' => (string) ($section['heading'] ?? ''),
                'lead' => blank($section['lead'] ?? null) ? null : (string) $section['lead'],
                'items' => $items,
            ];
        })->filter(fn ($section) => $section['heading'] !== '' || $section['items'] !== [])
            ->values()
            ->all();
    }

    private function replaceLandingMedia(LandingMediaSetting $setting, UploadedFile $image, string $directory): void
    {
        $oldPath = $setting->file_path;
        $newPath = $image->store($directory, 'public');

        $setting->forceFill(['file_path' => $newPath])->save();

        if (filled($oldPath) && $oldPath !== $newPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    private function publicUrl(?string $path): ?string
    {
        return filled($path) ? Storage::disk('public')->url($path) : null;
    }

    private function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }
}
