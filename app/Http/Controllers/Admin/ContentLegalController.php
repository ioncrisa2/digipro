<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppraisalUserConsentIndexRequest;
use App\Http\Requests\Admin\ConsentDocumentIndexRequest;
use App\Http\Requests\Admin\ReorderFaqRequest;
use App\Http\Requests\Admin\SimpleStatusIndexRequest;
use App\Http\Requests\Admin\StoreConsentDocumentRequest;
use App\Http\Requests\Admin\StoreFaqRequest;
use App\Http\Requests\Admin\StoreFeatureRequest;
use App\Http\Requests\Admin\StoreLegalDocumentRequest;
use App\Http\Requests\Admin\StoreTestimonialRequest;
use App\Http\Requests\Admin\UploadLandingImageRequest;
use App\Http\Requests\Admin\UploadPlatformPreviewImageRequest;
use App\Models\AppraisalUserConsent;
use App\Models\ConsentDocument;
use App\Models\Faq;
use App\Models\Feature;
use App\Models\PrivacyPolicy;
use App\Models\Testimonial;
use App\Models\TermsDocument;
use App\Services\Admin\AdminLegalContentWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class ContentLegalController extends Controller
{
    public function __construct(
        private readonly AdminLegalContentWorkspaceService $legalContentWorkspaceService,
    ) {
    }

    public function faqsIndex(SimpleStatusIndexRequest $request): Response
    {
        return inertia('Admin/SimpleContent/Index', $this->legalContentWorkspaceService
            ->faqsIndexPayload($request->filters()));
    }

    public function faqsCreate(): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'faqs', 'title' => 'FAQ', 'singular' => 'FAQ'],
            'mode' => 'create',
            'record' => $this->legalContentWorkspaceService->faqFormPayload(),
            'indexUrl' => route('admin.content.legal.faqs.index'),
            'submitUrl' => route('admin.content.legal.faqs.store'),
            'links' => $this->legalContentWorkspaceService->legalModuleLinks(),
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
            'record' => $this->legalContentWorkspaceService->faqFormPayload($faq),
            'indexUrl' => route('admin.content.legal.faqs.index'),
            'submitUrl' => route('admin.content.legal.faqs.update', $faq),
            'links' => $this->legalContentWorkspaceService->legalModuleLinks(),
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

    public function faqsReorder(ReorderFaqRequest $request): RedirectResponse
    {
        $this->legalContentWorkspaceService->reorderFaqs($request->validated()['ids']);

        return redirect()->route('admin.content.legal.faqs.index')->with('success', 'Urutan FAQ berhasil diperbarui.');
    }

    public function featuresIndex(SimpleStatusIndexRequest $request): Response
    {
        return inertia('Admin/SimpleContent/Index', $this->legalContentWorkspaceService
            ->featuresIndexPayload($request->filters()));
    }

    public function featuresCreate(): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'features', 'title' => 'Fitur', 'singular' => 'Fitur'],
            'mode' => 'create',
            'record' => $this->legalContentWorkspaceService->featureFormPayload(),
            'iconOptions' => $this->legalContentWorkspaceService->featureIconOptions(),
            'indexUrl' => route('admin.content.legal.features.index'),
            'submitUrl' => route('admin.content.legal.features.store'),
            'links' => $this->legalContentWorkspaceService->legalModuleLinks(),
        ]);
    }

    public function featuresStore(StoreFeatureRequest $request): RedirectResponse
    {
        $this->legalContentWorkspaceService->storeFeature(
            $request->validated(),
            $request->file('image')
        );

        return redirect()->route('admin.content.legal.features.index')->with('success', 'Fitur berhasil ditambahkan.');
    }

    public function featuresEdit(Feature $feature): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'features', 'title' => 'Fitur', 'singular' => 'Fitur'],
            'mode' => 'edit',
            'record' => $this->legalContentWorkspaceService->featureFormPayload($feature),
            'iconOptions' => $this->legalContentWorkspaceService->featureIconOptions(),
            'indexUrl' => route('admin.content.legal.features.index'),
            'submitUrl' => route('admin.content.legal.features.update', $feature),
            'links' => $this->legalContentWorkspaceService->legalModuleLinks(),
        ]);
    }

    public function featuresUpdate(StoreFeatureRequest $request, Feature $feature): RedirectResponse
    {
        $this->legalContentWorkspaceService->updateFeature(
            $feature,
            $request->validated(),
            $request->file('image')
        );

        return redirect()->route('admin.content.legal.features.index')->with('success', 'Fitur berhasil diperbarui.');
    }

    public function featuresDestroy(Feature $feature): RedirectResponse
    {
        $this->legalContentWorkspaceService->destroyFeature($feature);

        return redirect()->route('admin.content.legal.features.index')->with('success', 'Fitur berhasil dihapus.');
    }

    public function featuresHeroBackgroundUpdate(UploadLandingImageRequest $request): RedirectResponse
    {
        $this->legalContentWorkspaceService->updateHeroBackground($request->file('image'));

        return redirect()->route('admin.content.legal.features.index')->with('success', 'Background hero landing berhasil diperbarui.');
    }

    public function featuresPlatformPreviewUpdate(UploadPlatformPreviewImageRequest $request, int $slot): RedirectResponse
    {
        $this->legalContentWorkspaceService->updatePlatformPreview($slot, $request->file('image'));

        return redirect()->route('admin.content.legal.features.index')->with('success', 'Gambar platform preview berhasil diperbarui.');
    }

    public function testimonialsIndex(SimpleStatusIndexRequest $request): Response
    {
        return inertia('Admin/SimpleContent/Index', $this->legalContentWorkspaceService
            ->testimonialsIndexPayload($request->filters()));
    }

    public function testimonialsCreate(): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'testimonials', 'title' => 'Testimoni', 'singular' => 'Testimoni'],
            'mode' => 'create',
            'record' => $this->legalContentWorkspaceService->testimonialFormPayload(),
            'indexUrl' => route('admin.content.legal.testimonials.index'),
            'submitUrl' => route('admin.content.legal.testimonials.store'),
            'links' => $this->legalContentWorkspaceService->legalModuleLinks(),
        ]);
    }

    public function testimonialsStore(StoreTestimonialRequest $request): RedirectResponse
    {
        $this->legalContentWorkspaceService->storeTestimonial(
            $request->validated(),
            $request->file('photo')
        );

        return redirect()->route('admin.content.legal.testimonials.index')->with('success', 'Testimoni berhasil ditambahkan.');
    }

    public function testimonialsEdit(Testimonial $testimonial): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'testimonials', 'title' => 'Testimoni', 'singular' => 'Testimoni'],
            'mode' => 'edit',
            'record' => $this->legalContentWorkspaceService->testimonialFormPayload($testimonial),
            'indexUrl' => route('admin.content.legal.testimonials.index'),
            'submitUrl' => route('admin.content.legal.testimonials.update', $testimonial),
            'links' => $this->legalContentWorkspaceService->legalModuleLinks(),
        ]);
    }

    public function testimonialsUpdate(StoreTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $this->legalContentWorkspaceService->updateTestimonial(
            $testimonial,
            $request->validated(),
            $request->file('photo')
        );

        return redirect()->route('admin.content.legal.testimonials.index')->with('success', 'Testimoni berhasil diperbarui.');
    }

    public function testimonialsDestroy(Testimonial $testimonial): RedirectResponse
    {
        $this->legalContentWorkspaceService->destroyTestimonial($testimonial);

        return redirect()->route('admin.content.legal.testimonials.index')->with('success', 'Testimoni berhasil dihapus.');
    }

    public function termsDocumentsIndex(SimpleStatusIndexRequest $request): Response
    {
        return inertia('Admin/LegalDocuments/Index', $this->legalContentWorkspaceService
            ->legalDocumentsIndexPayload($request->filters(), TermsDocument::class, 'admin.content.legal.terms', 'terms'));
    }

    public function termsDocumentsCreate(): Response
    {
        return inertia('Admin/LegalDocuments/Form', $this->legalContentWorkspaceService
            ->legalDocumentFormPagePayload(
                new TermsDocument(),
                'create',
                route('admin.content.legal.terms.index'),
                route('admin.content.legal.terms.store'),
            ));
    }

    public function termsDocumentsStore(StoreLegalDocumentRequest $request): RedirectResponse
    {
        TermsDocument::query()->create($request->validated());

        return redirect()->route('admin.content.legal.terms.index')->with('success', 'Dokumen terms berhasil ditambahkan.');
    }

    public function termsDocumentsEdit(TermsDocument $termsDocument): Response
    {
        return inertia('Admin/LegalDocuments/Form', $this->legalContentWorkspaceService
            ->legalDocumentFormPagePayload(
                $termsDocument,
                'edit',
                route('admin.content.legal.terms.index'),
                route('admin.content.legal.terms.update', $termsDocument),
            ));
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

    public function privacyPoliciesIndex(SimpleStatusIndexRequest $request): Response
    {
        return inertia('Admin/LegalDocuments/Index', $this->legalContentWorkspaceService
            ->legalDocumentsIndexPayload($request->filters(), PrivacyPolicy::class, 'admin.content.legal.privacy', 'privacy'));
    }

    public function privacyPoliciesCreate(): Response
    {
        return inertia('Admin/LegalDocuments/Form', $this->legalContentWorkspaceService
            ->legalDocumentFormPagePayload(
                new PrivacyPolicy(),
                'create',
                route('admin.content.legal.privacy.index'),
                route('admin.content.legal.privacy.store'),
            ));
    }

    public function privacyPoliciesStore(StoreLegalDocumentRequest $request): RedirectResponse
    {
        PrivacyPolicy::query()->create($request->validated());

        return redirect()->route('admin.content.legal.privacy.index')->with('success', 'Dokumen privacy berhasil ditambahkan.');
    }

    public function privacyPoliciesEdit(PrivacyPolicy $privacyPolicy): Response
    {
        return inertia('Admin/LegalDocuments/Form', $this->legalContentWorkspaceService
            ->legalDocumentFormPagePayload(
                $privacyPolicy,
                'edit',
                route('admin.content.legal.privacy.index'),
                route('admin.content.legal.privacy.update', $privacyPolicy),
            ));
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

    public function consentDocumentsIndex(ConsentDocumentIndexRequest $request): Response
    {
        return inertia('Admin/ConsentDocuments/Index', $this->legalContentWorkspaceService
            ->consentDocumentsIndexPayload($request->filters()));
    }

    public function consentDocumentsCreate(): Response
    {
        return inertia('Admin/ConsentDocuments/Form', $this->legalContentWorkspaceService
            ->consentDocumentFormPagePayload(new ConsentDocument(), 'create', route('admin.content.legal.consent.store')));
    }

    public function consentDocumentsStore(StoreConsentDocumentRequest $request): RedirectResponse
    {
        $this->legalContentWorkspaceService->persistConsentDocument(new ConsentDocument(), $request->validated());

        return redirect()->route('admin.content.legal.consent.index')->with('success', 'Dokumen consent berhasil ditambahkan.');
    }

    public function consentDocumentsEdit(ConsentDocument $consentDocument): Response
    {
        return inertia('Admin/ConsentDocuments/Form', $this->legalContentWorkspaceService
            ->consentDocumentFormPagePayload($consentDocument, 'edit', route('admin.content.legal.consent.update', $consentDocument)));
    }

    public function consentDocumentsUpdate(
        StoreConsentDocumentRequest $request,
        ConsentDocument $consentDocument
    ): RedirectResponse {
        if ($consentDocument->status !== 'draft') {
            return redirect()->route('admin.content.legal.consent.index')->with('error', 'Dokumen published tidak bisa diedit.');
        }

        $this->legalContentWorkspaceService->persistConsentDocument($consentDocument, $request->validated());

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

        $this->legalContentWorkspaceService->publishConsentDocument($consentDocument);

        return redirect()->route('admin.content.legal.consent.index')->with('success', 'Dokumen consent berhasil dipublish.');
    }

    public function appraisalUserConsentsIndex(AppraisalUserConsentIndexRequest $request): Response
    {
        return inertia('Admin/AppraisalUserConsents/Index', $this->legalContentWorkspaceService
            ->appraisalUserConsentsIndexPayload($request->filters(), $request->perPage()));
    }

    public function appraisalUserConsentsShow(AppraisalUserConsent $appraisalUserConsent): Response
    {
        return inertia('Admin/AppraisalUserConsents/Show', $this->legalContentWorkspaceService
            ->appraisalUserConsentShowPayload($appraisalUserConsent));
    }
}
