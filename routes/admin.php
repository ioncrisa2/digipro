<?php

use App\Http\Controllers\Admin\AccessControlController;
use App\Http\Controllers\Admin\AppraisalRequestController;
use App\Http\Controllers\Admin\AppraisalRequestWorkflowController;
use App\Http\Controllers\Admin\BuildingEconomicLifeController;
use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\ContentLegalController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\IkkByProvinceController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\ReferenceGuideDataController;
use App\Http\Controllers\Admin\ReferenceGuideSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin.role'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'dashboard'])->name('dashboard');

        Route::prefix('permohonan-penilaian')
            ->name('appraisal-requests.')
            ->group(function (): void {
                Route::get('/', [AppraisalRequestController::class, 'appraisalRequestsIndex'])->name('index');
                Route::get('/{appraisalRequest}', [AppraisalRequestController::class, 'appraisalRequestsShow'])->name('show');
                Route::get('/{appraisalRequest}/edit', [AppraisalRequestController::class, 'appraisalRequestsEdit'])->name('edit');
                Route::put('/{appraisalRequest}', [AppraisalRequestController::class, 'appraisalRequestsUpdate'])->name('update');
                Route::post('/{appraisalRequest}/verify-docs', [AppraisalRequestWorkflowController::class, 'verifyDocs'])->name('actions.verify-docs');
                Route::post('/{appraisalRequest}/docs-incomplete', [AppraisalRequestWorkflowController::class, 'markDocsIncomplete'])->name('actions.docs-incomplete');
                Route::post('/{appraisalRequest}/revision-batches', [AppraisalRequestWorkflowController::class, 'storeRevisionBatch'])->name('revision-batches.store');
                Route::post('/{appraisalRequest}/contract-signed', [AppraisalRequestWorkflowController::class, 'markContractSigned'])->name('actions.contract-signed');
                Route::post('/{appraisalRequest}/verify-payment', [AppraisalRequestWorkflowController::class, 'verifyPayment'])->name('actions.verify-payment');
                Route::post('/{appraisalRequest}/send-offer', [AppraisalRequestWorkflowController::class, 'sendOffer'])->name('actions.send-offer');
                Route::post('/{appraisalRequest}/approve-latest-negotiation', [AppraisalRequestWorkflowController::class, 'approveLatestNegotiation'])->name('actions.approve-latest-negotiation');
            });

        Route::prefix('keuangan')
            ->name('finance.')
            ->group(function (): void {
                Route::get('/pembayaran', [FinanceController::class, 'paymentsIndex'])->name('payments.index');
                Route::get('/pembayaran/{payment}', [FinanceController::class, 'paymentsShow'])->name('payments.show');
                Route::get('/pembayaran/{payment}/edit', [FinanceController::class, 'paymentsEdit'])->name('payments.edit');
                Route::put('/pembayaran/{payment}', [FinanceController::class, 'paymentsUpdate'])->name('payments.update');
                Route::get('/rekening-kantor', [FinanceController::class, 'officeBankAccountsIndex'])->name('office-bank-accounts.index');
                Route::get('/rekening-kantor/buat', [FinanceController::class, 'officeBankAccountsCreate'])->name('office-bank-accounts.create');
                Route::post('/rekening-kantor', [FinanceController::class, 'officeBankAccountsStore'])->name('office-bank-accounts.store');
                Route::get('/rekening-kantor/{officeBankAccount}/edit', [FinanceController::class, 'officeBankAccountsEdit'])->name('office-bank-accounts.edit');
                Route::put('/rekening-kantor/{officeBankAccount}', [FinanceController::class, 'officeBankAccountsUpdate'])->name('office-bank-accounts.update');
                Route::delete('/rekening-kantor/{officeBankAccount}', [FinanceController::class, 'officeBankAccountsDestroy'])->name('office-bank-accounts.destroy');
            });

        Route::prefix('konten')
            ->name('content.')
            ->group(function (): void {
                Route::get('/artikel', [ContentController::class, 'articlesIndex'])->name('articles.index');
                Route::get('/artikel/buat', [ContentController::class, 'articlesCreate'])->name('articles.create');
                Route::post('/artikel/upload-image', [ContentController::class, 'articlesUploadImage'])->name('articles.images.store');
                Route::post('/artikel', [ContentController::class, 'articlesStore'])->name('articles.store');
                Route::get('/artikel/{article}/edit', [ContentController::class, 'articlesEdit'])->name('articles.edit');
                Route::put('/artikel/{article}', [ContentController::class, 'articlesUpdate'])->name('articles.update');
                Route::delete('/artikel/{article}', [ContentController::class, 'articlesDestroy'])->name('articles.destroy');

                Route::get('/kategori-artikel', [ContentController::class, 'articleCategoriesIndex'])->name('categories.index');
                Route::get('/kategori-artikel/buat', [ContentController::class, 'articleCategoriesCreate'])->name('categories.create');
                Route::post('/kategori-artikel', [ContentController::class, 'articleCategoriesStore'])->name('categories.store');
                Route::get('/kategori-artikel/{articleCategory}/edit', [ContentController::class, 'articleCategoriesEdit'])->name('categories.edit');
                Route::put('/kategori-artikel/{articleCategory}', [ContentController::class, 'articleCategoriesUpdate'])->name('categories.update');
                Route::delete('/kategori-artikel/{articleCategory}', [ContentController::class, 'articleCategoriesDestroy'])->name('categories.destroy');

                Route::get('/tag', [ContentController::class, 'tagsIndex'])->name('tags.index');
                Route::get('/tag/buat', [ContentController::class, 'tagsCreate'])->name('tags.create');
                Route::post('/tag', [ContentController::class, 'tagsStore'])->name('tags.store');
                Route::get('/tag/{tag}/edit', [ContentController::class, 'tagsEdit'])->name('tags.edit');
                Route::put('/tag/{tag}', [ContentController::class, 'tagsUpdate'])->name('tags.update');
                Route::delete('/tag/{tag}', [ContentController::class, 'tagsDestroy'])->name('tags.destroy');

                Route::prefix('legal')
                    ->name('legal.')
                    ->group(function (): void {
                        Route::get('/faq', [ContentLegalController::class, 'faqsIndex'])->name('faqs.index');
                        Route::get('/faq/buat', [ContentLegalController::class, 'faqsCreate'])->name('faqs.create');
                        Route::post('/faq', [ContentLegalController::class, 'faqsStore'])->name('faqs.store');
                        Route::get('/faq/{faq}/edit', [ContentLegalController::class, 'faqsEdit'])->name('faqs.edit');
                        Route::put('/faq/{faq}', [ContentLegalController::class, 'faqsUpdate'])->name('faqs.update');
                        Route::delete('/faq/{faq}', [ContentLegalController::class, 'faqsDestroy'])->name('faqs.destroy');

                        Route::get('/fitur', [ContentLegalController::class, 'featuresIndex'])->name('features.index');
                        Route::get('/fitur/buat', [ContentLegalController::class, 'featuresCreate'])->name('features.create');
                        Route::post('/fitur', [ContentLegalController::class, 'featuresStore'])->name('features.store');
                        Route::get('/fitur/{feature}/edit', [ContentLegalController::class, 'featuresEdit'])->name('features.edit');
                        Route::put('/fitur/{feature}', [ContentLegalController::class, 'featuresUpdate'])->name('features.update');
                        Route::delete('/fitur/{feature}', [ContentLegalController::class, 'featuresDestroy'])->name('features.destroy');

                        Route::get('/testimoni', [ContentLegalController::class, 'testimonialsIndex'])->name('testimonials.index');
                        Route::get('/testimoni/buat', [ContentLegalController::class, 'testimonialsCreate'])->name('testimonials.create');
                        Route::post('/testimoni', [ContentLegalController::class, 'testimonialsStore'])->name('testimonials.store');
                        Route::get('/testimoni/{testimonial}/edit', [ContentLegalController::class, 'testimonialsEdit'])->name('testimonials.edit');
                        Route::put('/testimoni/{testimonial}', [ContentLegalController::class, 'testimonialsUpdate'])->name('testimonials.update');
                        Route::delete('/testimoni/{testimonial}', [ContentLegalController::class, 'testimonialsDestroy'])->name('testimonials.destroy');

                        Route::get('/terms', [ContentLegalController::class, 'termsDocumentsIndex'])->name('terms.index');
                        Route::get('/terms/buat', [ContentLegalController::class, 'termsDocumentsCreate'])->name('terms.create');
                        Route::post('/terms', [ContentLegalController::class, 'termsDocumentsStore'])->name('terms.store');
                        Route::get('/terms/{termsDocument}/edit', [ContentLegalController::class, 'termsDocumentsEdit'])->name('terms.edit');
                        Route::put('/terms/{termsDocument}', [ContentLegalController::class, 'termsDocumentsUpdate'])->name('terms.update');
                        Route::delete('/terms/{termsDocument}', [ContentLegalController::class, 'termsDocumentsDestroy'])->name('terms.destroy');

                        Route::get('/privacy', [ContentLegalController::class, 'privacyPoliciesIndex'])->name('privacy.index');
                        Route::get('/privacy/buat', [ContentLegalController::class, 'privacyPoliciesCreate'])->name('privacy.create');
                        Route::post('/privacy', [ContentLegalController::class, 'privacyPoliciesStore'])->name('privacy.store');
                        Route::get('/privacy/{privacyPolicy}/edit', [ContentLegalController::class, 'privacyPoliciesEdit'])->name('privacy.edit');
                        Route::put('/privacy/{privacyPolicy}', [ContentLegalController::class, 'privacyPoliciesUpdate'])->name('privacy.update');
                        Route::delete('/privacy/{privacyPolicy}', [ContentLegalController::class, 'privacyPoliciesDestroy'])->name('privacy.destroy');

                        Route::get('/consent', [ContentLegalController::class, 'consentDocumentsIndex'])->name('consent.index');
                        Route::get('/consent/buat', [ContentLegalController::class, 'consentDocumentsCreate'])->name('consent.create');
                        Route::post('/consent', [ContentLegalController::class, 'consentDocumentsStore'])->name('consent.store');
                        Route::get('/consent/{consentDocument}/edit', [ContentLegalController::class, 'consentDocumentsEdit'])->name('consent.edit');
                        Route::put('/consent/{consentDocument}', [ContentLegalController::class, 'consentDocumentsUpdate'])->name('consent.update');
                        Route::delete('/consent/{consentDocument}', [ContentLegalController::class, 'consentDocumentsDestroy'])->name('consent.destroy');
                        Route::post('/consent/{consentDocument}/publish', [ContentLegalController::class, 'consentDocumentsPublish'])->name('consent.publish');

                        Route::get('/persetujuan-pengguna', [ContentLegalController::class, 'appraisalUserConsentsIndex'])->name('user-consents.index');
                        Route::get('/persetujuan-pengguna/{appraisalUserConsent}', [ContentLegalController::class, 'appraisalUserConsentsShow'])->name('user-consents.show');
                    });
            });

        Route::prefix('master-data')
            ->name('master-data.')
            ->group(function (): void {
                Route::get('/location-id-preview', [MasterDataController::class, 'locationIdPreview'])->name('locations.id-preview');
                Route::get('/location-options', [MasterDataController::class, 'locationOptions'])->name('locations.options');
                Route::get('/users', [MasterDataController::class, 'usersIndex'])->name('users.index');
                Route::get('/users/buat', [MasterDataController::class, 'usersCreate'])->name('users.create');
                Route::post('/users', [MasterDataController::class, 'usersStore'])->name('users.store');
                Route::get('/users/{user}', [MasterDataController::class, 'usersShow'])->name('users.show');
                Route::get('/users/{user}/edit', [MasterDataController::class, 'usersEdit'])->name('users.edit');
                Route::put('/users/{user}', [MasterDataController::class, 'usersUpdate'])->name('users.update');

                Route::get('/provinsi', [MasterDataController::class, 'provincesIndex'])->name('provinces.index');
                Route::get('/provinsi/buat', [MasterDataController::class, 'provincesCreate'])->name('provinces.create');
                Route::post('/provinsi', [MasterDataController::class, 'provincesStore'])->name('provinces.store');
                Route::get('/provinsi/{province}/edit', [MasterDataController::class, 'provincesEdit'])->name('provinces.edit');
                Route::put('/provinsi/{province}', [MasterDataController::class, 'provincesUpdate'])->name('provinces.update');
                Route::delete('/provinsi/{province}', [MasterDataController::class, 'provincesDestroy'])->name('provinces.destroy');

                Route::get('/kabupaten-kota', [MasterDataController::class, 'regenciesIndex'])->name('regencies.index');
                Route::get('/kabupaten-kota/buat', [MasterDataController::class, 'regenciesCreate'])->name('regencies.create');
                Route::post('/kabupaten-kota', [MasterDataController::class, 'regenciesStore'])->name('regencies.store');
                Route::get('/kabupaten-kota/{regency}/edit', [MasterDataController::class, 'regenciesEdit'])->name('regencies.edit');
                Route::put('/kabupaten-kota/{regency}', [MasterDataController::class, 'regenciesUpdate'])->name('regencies.update');
                Route::delete('/kabupaten-kota/{regency}', [MasterDataController::class, 'regenciesDestroy'])->name('regencies.destroy');

                Route::get('/kecamatan', [MasterDataController::class, 'districtsIndex'])->name('districts.index');
                Route::get('/kecamatan/buat', [MasterDataController::class, 'districtsCreate'])->name('districts.create');
                Route::post('/kecamatan', [MasterDataController::class, 'districtsStore'])->name('districts.store');
                Route::get('/kecamatan/{district}/edit', [MasterDataController::class, 'districtsEdit'])->name('districts.edit');
                Route::put('/kecamatan/{district}', [MasterDataController::class, 'districtsUpdate'])->name('districts.update');
                Route::delete('/kecamatan/{district}', [MasterDataController::class, 'districtsDestroy'])->name('districts.destroy');

                Route::get('/kelurahan-desa', [MasterDataController::class, 'villagesIndex'])->name('villages.index');
                Route::get('/kelurahan-desa/buat', [MasterDataController::class, 'villagesCreate'])->name('villages.create');
                Route::post('/kelurahan-desa', [MasterDataController::class, 'villagesStore'])->name('villages.store');
                Route::get('/kelurahan-desa/{village}/edit', [MasterDataController::class, 'villagesEdit'])->name('villages.edit');
                Route::put('/kelurahan-desa/{village}', [MasterDataController::class, 'villagesUpdate'])->name('villages.update');
                Route::delete('/kelurahan-desa/{village}', [MasterDataController::class, 'villagesDestroy'])->name('villages.destroy');
            });

        Route::prefix('ref-guidelines')
            ->name('ref-guidelines.')
            ->group(function (): void {
                Route::get('/guideline-sets', [ReferenceGuideSettingsController::class, 'guidelineSetsIndex'])->name('guideline-sets.index');
                Route::get('/guideline-sets/buat', [ReferenceGuideSettingsController::class, 'guidelineSetsCreate'])->name('guideline-sets.create');
                Route::post('/guideline-sets', [ReferenceGuideSettingsController::class, 'guidelineSetsStore'])->name('guideline-sets.store');
                Route::get('/guideline-sets/{guidelineSet}/edit', [ReferenceGuideSettingsController::class, 'guidelineSetsEdit'])->name('guideline-sets.edit');
                Route::put('/guideline-sets/{guidelineSet}', [ReferenceGuideSettingsController::class, 'guidelineSetsUpdate'])->name('guideline-sets.update');
                Route::delete('/guideline-sets/{guidelineSet}', [ReferenceGuideSettingsController::class, 'guidelineSetsDestroy'])->name('guideline-sets.destroy');

                Route::get('/ikk', [ReferenceGuideDataController::class, 'constructionCostIndicesIndex'])->name('construction-cost-indices.index');
                Route::get('/ikk/buat', [ReferenceGuideDataController::class, 'constructionCostIndicesCreate'])->name('construction-cost-indices.create');
                Route::post('/ikk', [ReferenceGuideDataController::class, 'constructionCostIndicesStore'])->name('construction-cost-indices.store');
                Route::get('/ikk/{constructionCostIndex}/edit', [ReferenceGuideDataController::class, 'constructionCostIndicesEdit'])->name('construction-cost-indices.edit');
                Route::put('/ikk/{constructionCostIndex}', [ReferenceGuideDataController::class, 'constructionCostIndicesUpdate'])->name('construction-cost-indices.update');
                Route::delete('/ikk/{constructionCostIndex}', [ReferenceGuideDataController::class, 'constructionCostIndicesDestroy'])->name('construction-cost-indices.destroy');

                Route::get('/cost-elements', [ReferenceGuideDataController::class, 'costElementsIndex'])->name('cost-elements.index');
                Route::get('/cost-elements/buat', [ReferenceGuideDataController::class, 'costElementsCreate'])->name('cost-elements.create');
                Route::post('/cost-elements', [ReferenceGuideDataController::class, 'costElementsStore'])->name('cost-elements.store');
                Route::get('/cost-elements/{costElement}/edit', [ReferenceGuideDataController::class, 'costElementsEdit'])->name('cost-elements.edit');
                Route::put('/cost-elements/{costElement}', [ReferenceGuideDataController::class, 'costElementsUpdate'])->name('cost-elements.update');
                Route::delete('/cost-elements/{costElement}', [ReferenceGuideDataController::class, 'costElementsDestroy'])->name('cost-elements.destroy');

                Route::get('/floor-indices', [ReferenceGuideDataController::class, 'floorIndicesIndex'])->name('floor-indices.index');
                Route::get('/floor-indices/buat', [ReferenceGuideDataController::class, 'floorIndicesCreate'])->name('floor-indices.create');
                Route::post('/floor-indices', [ReferenceGuideDataController::class, 'floorIndicesStore'])->name('floor-indices.store');
                Route::get('/floor-indices/{floorIndex}/edit', [ReferenceGuideDataController::class, 'floorIndicesEdit'])->name('floor-indices.edit');
                Route::put('/floor-indices/{floorIndex}', [ReferenceGuideDataController::class, 'floorIndicesUpdate'])->name('floor-indices.update');
                Route::delete('/floor-indices/{floorIndex}', [ReferenceGuideDataController::class, 'floorIndicesDestroy'])->name('floor-indices.destroy');

                Route::get('/mappi-rcn-standards', [ReferenceGuideDataController::class, 'mappiRcnStandardsIndex'])->name('mappi-rcn-standards.index');
                Route::get('/mappi-rcn-standards/buat', [ReferenceGuideDataController::class, 'mappiRcnStandardsCreate'])->name('mappi-rcn-standards.create');
                Route::post('/mappi-rcn-standards', [ReferenceGuideDataController::class, 'mappiRcnStandardsStore'])->name('mappi-rcn-standards.store');
                Route::get('/mappi-rcn-standards/{mappiRcnStandard}/edit', [ReferenceGuideDataController::class, 'mappiRcnStandardsEdit'])->name('mappi-rcn-standards.edit');
                Route::put('/mappi-rcn-standards/{mappiRcnStandard}', [ReferenceGuideDataController::class, 'mappiRcnStandardsUpdate'])->name('mappi-rcn-standards.update');
                Route::delete('/mappi-rcn-standards/{mappiRcnStandard}', [ReferenceGuideDataController::class, 'mappiRcnStandardsDestroy'])->name('mappi-rcn-standards.destroy');

                Route::get('/building-economic-lives', [BuildingEconomicLifeController::class, 'index'])->name('building-economic-lives.index');
                Route::get('/building-economic-lives/buat', [BuildingEconomicLifeController::class, 'create'])->name('building-economic-lives.create');
                Route::post('/building-economic-lives', [BuildingEconomicLifeController::class, 'store'])->name('building-economic-lives.store');
                Route::get('/building-economic-lives/{buildingEconomicLife}/edit', [BuildingEconomicLifeController::class, 'edit'])->name('building-economic-lives.edit');
                Route::put('/building-economic-lives/{buildingEconomicLife}', [BuildingEconomicLifeController::class, 'update'])->name('building-economic-lives.update');
                Route::delete('/building-economic-lives/{buildingEconomicLife}', [BuildingEconomicLifeController::class, 'destroy'])->name('building-economic-lives.destroy');

                Route::get('/ikk-by-province', [IkkByProvinceController::class, 'index'])->name('ikk-by-province.index');
                Route::post('/ikk-by-province', [IkkByProvinceController::class, 'save'])->name('ikk-by-province.save');

                Route::get('/valuation-settings', [ReferenceGuideSettingsController::class, 'valuationSettingsIndex'])->name('valuation-settings.index');
                Route::get('/valuation-settings/buat', [ReferenceGuideSettingsController::class, 'valuationSettingsCreate'])->name('valuation-settings.create');
                Route::post('/valuation-settings', [ReferenceGuideSettingsController::class, 'valuationSettingsStore'])->name('valuation-settings.store');
                Route::get('/valuation-settings/{valuationSetting}/edit', [ReferenceGuideSettingsController::class, 'valuationSettingsEdit'])->name('valuation-settings.edit');
                Route::put('/valuation-settings/{valuationSetting}', [ReferenceGuideSettingsController::class, 'valuationSettingsUpdate'])->name('valuation-settings.update');
                Route::delete('/valuation-settings/{valuationSetting}', [ReferenceGuideSettingsController::class, 'valuationSettingsDestroy'])->name('valuation-settings.destroy');
            });

        Route::prefix('hak-akses')
            ->name('access-control.')
            ->group(function (): void {
                Route::get('/roles', [AccessControlController::class, 'rolesIndex'])->name('roles.index');
                Route::get('/roles/buat', [AccessControlController::class, 'rolesCreate'])->name('roles.create');
                Route::post('/roles', [AccessControlController::class, 'rolesStore'])->name('roles.store');
                Route::get('/roles/{role}', [AccessControlController::class, 'rolesShow'])->name('roles.show');
                Route::get('/roles/{role}/edit', [AccessControlController::class, 'rolesEdit'])->name('roles.edit');
                Route::put('/roles/{role}', [AccessControlController::class, 'rolesUpdate'])->name('roles.update');
                Route::delete('/roles/{role}', [AccessControlController::class, 'rolesDestroy'])->name('roles.destroy');
            });

        Route::prefix('komunikasi')
            ->name('communications.')
            ->group(function (): void {
                Route::get('/contact-messages', [CommunicationController::class, 'contactMessagesIndex'])->name('contact-messages.index');
                Route::get('/contact-messages/{contactMessage}', [CommunicationController::class, 'contactMessagesShow'])->name('contact-messages.show');
                Route::post('/contact-messages/{contactMessage}/in-progress', [CommunicationController::class, 'contactMessagesMarkInProgress'])->name('contact-messages.in-progress');
                Route::post('/contact-messages/{contactMessage}/done', [CommunicationController::class, 'contactMessagesMarkDone'])->name('contact-messages.done');
                Route::post('/contact-messages/{contactMessage}/archive', [CommunicationController::class, 'contactMessagesArchive'])->name('contact-messages.archive');
                Route::delete('/contact-messages/{contactMessage}', [CommunicationController::class, 'contactMessagesDestroy'])->name('contact-messages.destroy');
            });
    });
