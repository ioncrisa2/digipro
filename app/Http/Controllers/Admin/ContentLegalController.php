<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreConsentDocumentRequest;
use App\Http\Requests\Admin\StoreFaqRequest;
use App\Http\Requests\Admin\StoreFeatureRequest;
use App\Http\Requests\Admin\StoreLegalDocumentRequest;
use App\Http\Requests\Admin\StoreTestimonialRequest;
use App\Models\AppraisalUserConsent;
use App\Models\ConsentDocument;
use App\Models\Faq;
use App\Models\Feature;
use App\Models\PrivacyPolicy;
use App\Models\Testimonial;
use App\Models\TermsDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ContentLegalController extends Controller
{
    public function faqsIndex(Request $request): Response
    {
        $filters = $this->simpleActiveFilters($request);

        $records = Faq::query()
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
            ->values();

        return inertia('Admin/SimpleContent/Index', [
            'resource' => ['key' => 'faqs', 'title' => 'FAQ', 'create_label' => 'Tambah FAQ'],
            'filters' => $filters,
            'statusOptions' => $this->simpleStatusOptions(),
            'summary' => [
                'total' => Faq::query()->count(),
                'active' => Faq::query()->where('is_active', true)->count(),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.legal.faqs.create'),
            'links' => $this->legalModuleLinks(),
        ]);
    }

    public function faqsCreate(): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'faqs', 'title' => 'FAQ', 'singular' => 'FAQ'],
            'mode' => 'create',
            'record' => [
                'question' => '',
                'answer' => '',
                'sort_order' => 0,
                'is_active' => true,
            ],
            'indexUrl' => route('admin.content.legal.faqs.index'),
            'submitUrl' => route('admin.content.legal.faqs.store'),
            'links' => $this->legalModuleLinks(),
        ]);
    }

    public function faqsStore(StoreFaqRequest $request): RedirectResponse
    {
        Faq::query()->create($request->validated());

        return redirect()->route('admin.content.legal.faqs.index')->with('success', 'FAQ berhasil ditambahkan.');
    }

    public function faqsEdit(Faq $faq): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'faqs', 'title' => 'FAQ', 'singular' => 'FAQ'],
            'mode' => 'edit',
            'record' => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'sort_order' => (int) $faq->sort_order,
                'is_active' => (bool) $faq->is_active,
            ],
            'indexUrl' => route('admin.content.legal.faqs.index'),
            'submitUrl' => route('admin.content.legal.faqs.update', $faq),
            'links' => $this->legalModuleLinks(),
        ]);
    }

    public function faqsUpdate(StoreFaqRequest $request, Faq $faq): RedirectResponse
    {
        $faq->update($request->validated());

        return redirect()->route('admin.content.legal.faqs.index')->with('success', 'FAQ berhasil diperbarui.');
    }

    public function faqsDestroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return redirect()->route('admin.content.legal.faqs.index')->with('success', 'FAQ berhasil dihapus.');
    }

    public function featuresIndex(Request $request): Response
    {
        $filters = $this->simpleActiveFilters($request);

        $records = Feature::query()
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
                'sort_order' => (int) $feature->sort_order,
                'is_active' => (bool) $feature->is_active,
                'edit_url' => route('admin.content.legal.features.edit', $feature),
                'destroy_url' => route('admin.content.legal.features.destroy', $feature),
            ])
            ->values();

        return inertia('Admin/SimpleContent/Index', [
            'resource' => ['key' => 'features', 'title' => 'Fitur', 'create_label' => 'Tambah Fitur'],
            'filters' => $filters,
            'statusOptions' => $this->simpleStatusOptions(),
            'summary' => [
                'total' => Feature::query()->count(),
                'active' => Feature::query()->where('is_active', true)->count(),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.legal.features.create'),
            'links' => $this->legalModuleLinks(),
        ]);
    }

    public function featuresCreate(): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'features', 'title' => 'Fitur', 'singular' => 'Fitur'],
            'mode' => 'create',
            'record' => [
                'icon' => '__none',
                'title' => '',
                'description' => '',
                'sort_order' => 0,
                'is_active' => true,
            ],
            'iconOptions' => $this->featureIconOptions(),
            'indexUrl' => route('admin.content.legal.features.index'),
            'submitUrl' => route('admin.content.legal.features.store'),
            'links' => $this->legalModuleLinks(),
        ]);
    }

    public function featuresStore(StoreFeatureRequest $request): RedirectResponse
    {
        Feature::query()->create($request->validated());

        return redirect()->route('admin.content.legal.features.index')->with('success', 'Fitur berhasil ditambahkan.');
    }

    public function featuresEdit(Feature $feature): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'features', 'title' => 'Fitur', 'singular' => 'Fitur'],
            'mode' => 'edit',
            'record' => [
                'id' => $feature->id,
                'icon' => $feature->icon ?? '__none',
                'title' => $feature->title,
                'description' => $feature->description,
                'sort_order' => (int) $feature->sort_order,
                'is_active' => (bool) $feature->is_active,
            ],
            'iconOptions' => $this->featureIconOptions(),
            'indexUrl' => route('admin.content.legal.features.index'),
            'submitUrl' => route('admin.content.legal.features.update', $feature),
            'links' => $this->legalModuleLinks(),
        ]);
    }

    public function featuresUpdate(StoreFeatureRequest $request, Feature $feature): RedirectResponse
    {
        $feature->update($request->validated());

        return redirect()->route('admin.content.legal.features.index')->with('success', 'Fitur berhasil diperbarui.');
    }

    public function featuresDestroy(Feature $feature): RedirectResponse
    {
        $feature->delete();

        return redirect()->route('admin.content.legal.features.index')->with('success', 'Fitur berhasil dihapus.');
    }

    public function testimonialsIndex(Request $request): Response
    {
        $filters = $this->simpleActiveFilters($request);

        $records = Testimonial::query()
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
                'sort_order' => (int) $testimonial->sort_order,
                'is_active' => (bool) $testimonial->is_active,
                'edit_url' => route('admin.content.legal.testimonials.edit', $testimonial),
                'destroy_url' => route('admin.content.legal.testimonials.destroy', $testimonial),
            ])
            ->values();

        return inertia('Admin/SimpleContent/Index', [
            'resource' => ['key' => 'testimonials', 'title' => 'Testimoni', 'create_label' => 'Tambah Testimoni'],
            'filters' => $filters,
            'statusOptions' => $this->simpleStatusOptions(),
            'summary' => [
                'total' => Testimonial::query()->count(),
                'active' => Testimonial::query()->where('is_active', true)->count(),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.legal.testimonials.create'),
            'links' => $this->legalModuleLinks(),
        ]);
    }

    public function testimonialsCreate(): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'testimonials', 'title' => 'Testimoni', 'singular' => 'Testimoni'],
            'mode' => 'create',
            'record' => [
                'name' => '',
                'role' => '',
                'quote' => '',
                'sort_order' => 0,
                'is_active' => true,
            ],
            'indexUrl' => route('admin.content.legal.testimonials.index'),
            'submitUrl' => route('admin.content.legal.testimonials.store'),
            'links' => $this->legalModuleLinks(),
        ]);
    }

    public function testimonialsStore(StoreTestimonialRequest $request): RedirectResponse
    {
        Testimonial::query()->create($request->validated());

        return redirect()->route('admin.content.legal.testimonials.index')->with('success', 'Testimoni berhasil ditambahkan.');
    }

    public function testimonialsEdit(Testimonial $testimonial): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'testimonials', 'title' => 'Testimoni', 'singular' => 'Testimoni'],
            'mode' => 'edit',
            'record' => [
                'id' => $testimonial->id,
                'name' => $testimonial->name,
                'role' => $testimonial->role,
                'quote' => $testimonial->quote,
                'sort_order' => (int) $testimonial->sort_order,
                'is_active' => (bool) $testimonial->is_active,
            ],
            'indexUrl' => route('admin.content.legal.testimonials.index'),
            'submitUrl' => route('admin.content.legal.testimonials.update', $testimonial),
            'links' => $this->legalModuleLinks(),
        ]);
    }

    public function testimonialsUpdate(StoreTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $testimonial->update($request->validated());

        return redirect()->route('admin.content.legal.testimonials.index')->with('success', 'Testimoni berhasil diperbarui.');
    }

    public function testimonialsDestroy(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();

        return redirect()->route('admin.content.legal.testimonials.index')->with('success', 'Testimoni berhasil dihapus.');
    }

    public function termsDocumentsIndex(Request $request): Response
    {
        return $this->legalDocumentsIndex(
            $request,
            TermsDocument::class,
            'Admin/LegalDocuments/Index',
            'admin.content.legal.terms',
            'terms'
        );
    }

    public function termsDocumentsCreate(): Response
    {
        return $this->legalDocumentFormResponse(
            'Admin/LegalDocuments/Form',
            'create',
            $this->legalDocumentFormPayload(new TermsDocument()),
            route('admin.content.legal.terms.index'),
            route('admin.content.legal.terms.store')
        );
    }

    public function termsDocumentsStore(StoreLegalDocumentRequest $request): RedirectResponse
    {
        TermsDocument::query()->create($request->validated());

        return redirect()->route('admin.content.legal.terms.index')->with('success', 'Dokumen terms berhasil ditambahkan.');
    }

    public function termsDocumentsEdit(TermsDocument $termsDocument): Response
    {
        return $this->legalDocumentFormResponse(
            'Admin/LegalDocuments/Form',
            'edit',
            $this->legalDocumentFormPayload($termsDocument, $this->legacyTermsDocumentUrl($termsDocument)),
            route('admin.content.legal.terms.index'),
            route('admin.content.legal.terms.update', $termsDocument)
        );
    }

    public function termsDocumentsUpdate(StoreLegalDocumentRequest $request, TermsDocument $termsDocument): RedirectResponse
    {
        $termsDocument->update($request->validated());

        return redirect()->route('admin.content.legal.terms.index')->with('success', 'Dokumen terms berhasil diperbarui.');
    }

    public function termsDocumentsDestroy(TermsDocument $termsDocument): RedirectResponse
    {
        $termsDocument->delete();

        return redirect()->route('admin.content.legal.terms.index')->with('success', 'Dokumen terms berhasil dihapus.');
    }

    public function privacyPoliciesIndex(Request $request): Response
    {
        return $this->legalDocumentsIndex(
            $request,
            PrivacyPolicy::class,
            'Admin/LegalDocuments/Index',
            'admin.content.legal.privacy',
            'privacy'
        );
    }

    public function privacyPoliciesCreate(): Response
    {
        return $this->legalDocumentFormResponse(
            'Admin/LegalDocuments/Form',
            'create',
            $this->legalDocumentFormPayload(new PrivacyPolicy()),
            route('admin.content.legal.privacy.index'),
            route('admin.content.legal.privacy.store')
        );
    }

    public function privacyPoliciesStore(StoreLegalDocumentRequest $request): RedirectResponse
    {
        PrivacyPolicy::query()->create($request->validated());

        return redirect()->route('admin.content.legal.privacy.index')->with('success', 'Dokumen privacy berhasil ditambahkan.');
    }

    public function privacyPoliciesEdit(PrivacyPolicy $privacyPolicy): Response
    {
        return $this->legalDocumentFormResponse(
            'Admin/LegalDocuments/Form',
            'edit',
            $this->legalDocumentFormPayload($privacyPolicy, $this->legacyPrivacyPolicyUrl($privacyPolicy)),
            route('admin.content.legal.privacy.index'),
            route('admin.content.legal.privacy.update', $privacyPolicy)
        );
    }

    public function privacyPoliciesUpdate(StoreLegalDocumentRequest $request, PrivacyPolicy $privacyPolicy): RedirectResponse
    {
        $privacyPolicy->update($request->validated());

        return redirect()->route('admin.content.legal.privacy.index')->with('success', 'Dokumen privacy berhasil diperbarui.');
    }

    public function privacyPoliciesDestroy(PrivacyPolicy $privacyPolicy): RedirectResponse
    {
        $privacyPolicy->delete();

        return redirect()->route('admin.content.legal.privacy.index')->with('success', 'Dokumen privacy berhasil dihapus.');
    }

    public function consentDocumentsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

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
            ->values();

        return inertia('Admin/ConsentDocuments/Index', [
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
        ]);
    }

    public function consentDocumentsCreate(): Response
    {
        return inertia('Admin/ConsentDocuments/Form', [
            'mode' => 'create',
            'record' => $this->consentDocumentFormPayload(new ConsentDocument()),
            'indexUrl' => route('admin.content.legal.consent.index'),
            'submitUrl' => route('admin.content.legal.consent.store'),
            'links' => $this->legalModuleLinks(),
        ]);
    }

    public function consentDocumentsStore(StoreConsentDocumentRequest $request): RedirectResponse
    {
        $document = new ConsentDocument();
        $this->persistConsentDocument($document, $request->validated());

        return redirect()->route('admin.content.legal.consent.index')->with('success', 'Dokumen consent berhasil ditambahkan.');
    }

    public function consentDocumentsEdit(ConsentDocument $consentDocument): Response
    {
        return inertia('Admin/ConsentDocuments/Form', [
            'mode' => 'edit',
            'record' => $this->consentDocumentFormPayload($consentDocument),
            'indexUrl' => route('admin.content.legal.consent.index'),
            'submitUrl' => route('admin.content.legal.consent.update', $consentDocument),
            'links' => $this->legalModuleLinks(),
        ]);
    }

    public function consentDocumentsUpdate(
        StoreConsentDocumentRequest $request,
        ConsentDocument $consentDocument
    ): RedirectResponse {
        if ($consentDocument->status !== 'draft') {
            return redirect()->route('admin.content.legal.consent.index')->with('error', 'Dokumen published tidak bisa diedit.');
        }

        $this->persistConsentDocument($consentDocument, $request->validated());

        return redirect()->route('admin.content.legal.consent.index')->with('success', 'Dokumen consent berhasil diperbarui.');
    }

    public function consentDocumentsDestroy(ConsentDocument $consentDocument): RedirectResponse
    {
        if ($consentDocument->status !== 'draft') {
            return redirect()->route('admin.content.legal.consent.index')->with('error', 'Dokumen published tidak bisa dihapus.');
        }

        $consentDocument->delete();

        return redirect()->route('admin.content.legal.consent.index')->with('success', 'Dokumen consent berhasil dihapus.');
    }

    public function consentDocumentsPublish(ConsentDocument $consentDocument): RedirectResponse
    {
        if ($consentDocument->status !== 'draft') {
            return redirect()->route('admin.content.legal.consent.index')->with('error', 'Hanya draft yang bisa dipublish.');
        }

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

        return redirect()->route('admin.content.legal.consent.index')->with('success', 'Dokumen consent berhasil dipublish.');
    }

    public function appraisalUserConsentsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'code' => (string) $request->query('code', 'all'),
        ];

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
            ->paginate(20)
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

        return inertia('Admin/AppraisalUserConsents/Index', [
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
        ]);
    }

    public function appraisalUserConsentsShow(AppraisalUserConsent $appraisalUserConsent): Response
    {
        $appraisalUserConsent->loadMissing(['user', 'document']);

        return inertia('Admin/AppraisalUserConsents/Show', [
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
        ]);
    }

    private function simpleActiveFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];
    }

    private function simpleStatusOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Semua Status'],
            ['value' => 'active', 'label' => 'Aktif'],
            ['value' => 'inactive', 'label' => 'Nonaktif'],
        ];
    }

    private function legalModuleLinks(): array
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

    private function featureIconOptions(): array
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

    private function legalDocumentsIndex(
        Request $request,
        string $modelClass,
        string $component,
        string $routePrefix,
        string $legacyType
    ): Response {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $records = $modelClass::query()
            ->when($filters['q'] !== '', fn ($query) => $query->where('title', 'like', '%' . $filters['q'] . '%'))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest('updated_at')
            ->get()
            ->map(function ($document) use ($routePrefix, $legacyType) {
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
            ->values();

        return inertia($component, [
            'resource' => [
                'key' => $legacyType,
                'title' => $legacyType === 'terms' ? 'Terms' : 'Privacy Policy',
                'create_label' => $legacyType === 'terms' ? 'Tambah Terms' : 'Tambah Privacy Policy',
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
        ]);
    }

    private function legalDocumentFormPayload(object $document, ?string $legacyUrl = null): array
    {
        return [
            'id' => $document->id ?? null,
            'title' => $document->title ?? '',
            'company' => $document->company ?? '',
            'version' => $document->version ?? '',
            'effective_since' => $document->effective_since?->format('Y-m-d'),
            'content_html' => $document->content_html ?? '',
            'is_active' => (bool) ($document->is_active ?? false),
            'published_at' => $document->published_at?->format('Y-m-d\\TH:i'),
        ];
    }

    private function legalDocumentFormResponse(
        string $component,
        string $mode,
        array $record,
        string $indexUrl,
        string $submitUrl
    ): Response {
        return inertia($component, [
            'resource' => [
                'key' => str_contains($indexUrl, '/terms') ? 'terms' : 'privacy',
                'title' => str_contains($indexUrl, '/terms') ? 'Terms' : 'Privacy Policy',
                'singular' => str_contains($indexUrl, '/terms') ? 'Terms' : 'Privacy Policy',
            ],
            'mode' => $mode,
            'record' => $record,
            'indexUrl' => $indexUrl,
            'submitUrl' => $submitUrl,
            'links' => $this->legalModuleLinks(),
        ]);
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

    private function persistConsentDocument(ConsentDocument $document, array $validated): void
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

    private function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }

    private function legacyFaqUrl(Faq $faq): ?string
    {
        return null;
    }

    private function legacyFeatureUrl(Feature $feature): ?string
    {
        return null;
    }

    private function legacyTestimonialUrl(Testimonial $testimonial): ?string
    {
        return null;
    }

    private function legacyTermsDocumentUrl(TermsDocument $document): ?string
    {
        return null;
    }

    private function legacyPrivacyPolicyUrl(PrivacyPolicy $policy): ?string
    {
        return null;
    }

    private function legacyConsentDocumentUrl(ConsentDocument $document): ?string
    {
        return null;
    }

    private function legacyAppraisalUserConsentUrl(AppraisalUserConsent $consent): ?string
    {
        return null;
    }
}
