<?php

use App\Http\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin.role'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

        Route::prefix('permohonan-penilaian')
            ->name('appraisal-requests.')
            ->group(function (): void {
                Route::get('/', [AdminController::class, 'appraisalRequestsIndex'])->name('index');
                Route::get('/{appraisalRequest}', [AdminController::class, 'appraisalRequestsShow'])->name('show');
                Route::get('/{appraisalRequest}/edit', [AdminController::class, 'appraisalRequestsEdit'])->name('edit');
                Route::put('/{appraisalRequest}', [AdminController::class, 'appraisalRequestsUpdate'])->name('update');
                Route::get('/{appraisalRequest}/assets/create', [AdminController::class, 'appraisalRequestAssetCreate'])->name('assets.create');
                Route::post('/{appraisalRequest}/assets', [AdminController::class, 'storeAppraisalRequestAsset'])->name('assets.store');
                Route::get('/{appraisalRequest}/assets/{asset}/edit', [AdminController::class, 'appraisalRequestAssetEdit'])->name('assets.edit');
                Route::put('/{appraisalRequest}/assets/{asset}', [AdminController::class, 'updateAppraisalRequestAsset'])->name('assets.update');
                Route::delete('/{appraisalRequest}/assets/{asset}', [AdminController::class, 'destroyAppraisalRequestAsset'])->name('assets.destroy');
                Route::post('/{appraisalRequest}/assets/{asset}/files', [AdminController::class, 'storeAppraisalAssetFile'])->name('assets.files.store');
                Route::delete('/{appraisalRequest}/assets/{asset}/files/{file}', [AdminController::class, 'destroyAppraisalAssetFile'])->name('assets.files.destroy');
                Route::post('/{appraisalRequest}/verify-docs', [AdminController::class, 'verifyDocs'])->name('actions.verify-docs');
                Route::post('/{appraisalRequest}/docs-incomplete', [AdminController::class, 'markDocsIncomplete'])->name('actions.docs-incomplete');
                Route::post('/{appraisalRequest}/contract-signed', [AdminController::class, 'markContractSigned'])->name('actions.contract-signed');
                Route::post('/{appraisalRequest}/verify-payment', [AdminController::class, 'verifyPayment'])->name('actions.verify-payment');
                Route::post('/{appraisalRequest}/send-offer', [AdminController::class, 'sendOffer'])->name('actions.send-offer');
                Route::post('/{appraisalRequest}/approve-latest-negotiation', [AdminController::class, 'approveLatestNegotiation'])->name('actions.approve-latest-negotiation');
                Route::post('/{appraisalRequest}/files', [AdminController::class, 'storeRequestFile'])->name('files.store');
                Route::delete('/{appraisalRequest}/files/{file}', [AdminController::class, 'destroyRequestFile'])->name('files.destroy');
            });

        Route::prefix('keuangan')
            ->name('finance.')
            ->group(function (): void {
                Route::get('/pembayaran', [AdminController::class, 'paymentsIndex'])->name('payments.index');
                Route::get('/pembayaran/{payment}', [AdminController::class, 'paymentsShow'])->name('payments.show');
                Route::get('/pembayaran/{payment}/edit', [AdminController::class, 'paymentsEdit'])->name('payments.edit');
                Route::put('/pembayaran/{payment}', [AdminController::class, 'paymentsUpdate'])->name('payments.update');
                Route::get('/rekening-kantor', [AdminController::class, 'officeBankAccountsIndex'])->name('office-bank-accounts.index');
                Route::get('/rekening-kantor/buat', [AdminController::class, 'officeBankAccountsCreate'])->name('office-bank-accounts.create');
                Route::post('/rekening-kantor', [AdminController::class, 'officeBankAccountsStore'])->name('office-bank-accounts.store');
                Route::get('/rekening-kantor/{officeBankAccount}/edit', [AdminController::class, 'officeBankAccountsEdit'])->name('office-bank-accounts.edit');
                Route::put('/rekening-kantor/{officeBankAccount}', [AdminController::class, 'officeBankAccountsUpdate'])->name('office-bank-accounts.update');
                Route::delete('/rekening-kantor/{officeBankAccount}', [AdminController::class, 'officeBankAccountsDestroy'])->name('office-bank-accounts.destroy');
            });

        Route::prefix('konten')
            ->name('content.')
            ->group(function (): void {
                Route::get('/artikel', [AdminController::class, 'articlesIndex'])->name('articles.index');
                Route::get('/artikel/buat', [AdminController::class, 'articlesCreate'])->name('articles.create');
                Route::post('/artikel', [AdminController::class, 'articlesStore'])->name('articles.store');
                Route::get('/artikel/{article}/edit', [AdminController::class, 'articlesEdit'])->name('articles.edit');
                Route::put('/artikel/{article}', [AdminController::class, 'articlesUpdate'])->name('articles.update');
                Route::delete('/artikel/{article}', [AdminController::class, 'articlesDestroy'])->name('articles.destroy');

                Route::get('/kategori-artikel', [AdminController::class, 'articleCategoriesIndex'])->name('categories.index');
                Route::get('/kategori-artikel/buat', [AdminController::class, 'articleCategoriesCreate'])->name('categories.create');
                Route::post('/kategori-artikel', [AdminController::class, 'articleCategoriesStore'])->name('categories.store');
                Route::get('/kategori-artikel/{articleCategory}/edit', [AdminController::class, 'articleCategoriesEdit'])->name('categories.edit');
                Route::put('/kategori-artikel/{articleCategory}', [AdminController::class, 'articleCategoriesUpdate'])->name('categories.update');
                Route::delete('/kategori-artikel/{articleCategory}', [AdminController::class, 'articleCategoriesDestroy'])->name('categories.destroy');

                Route::get('/tag', [AdminController::class, 'tagsIndex'])->name('tags.index');
                Route::get('/tag/buat', [AdminController::class, 'tagsCreate'])->name('tags.create');
                Route::post('/tag', [AdminController::class, 'tagsStore'])->name('tags.store');
                Route::get('/tag/{tag}/edit', [AdminController::class, 'tagsEdit'])->name('tags.edit');
                Route::put('/tag/{tag}', [AdminController::class, 'tagsUpdate'])->name('tags.update');
                Route::delete('/tag/{tag}', [AdminController::class, 'tagsDestroy'])->name('tags.destroy');

                Route::prefix('legal')
                    ->name('legal.')
                    ->group(function (): void {
                        Route::get('/faq', [AdminController::class, 'faqsIndex'])->name('faqs.index');
                        Route::get('/faq/buat', [AdminController::class, 'faqsCreate'])->name('faqs.create');
                        Route::post('/faq', [AdminController::class, 'faqsStore'])->name('faqs.store');
                        Route::get('/faq/{faq}/edit', [AdminController::class, 'faqsEdit'])->name('faqs.edit');
                        Route::put('/faq/{faq}', [AdminController::class, 'faqsUpdate'])->name('faqs.update');
                        Route::delete('/faq/{faq}', [AdminController::class, 'faqsDestroy'])->name('faqs.destroy');

                        Route::get('/fitur', [AdminController::class, 'featuresIndex'])->name('features.index');
                        Route::get('/fitur/buat', [AdminController::class, 'featuresCreate'])->name('features.create');
                        Route::post('/fitur', [AdminController::class, 'featuresStore'])->name('features.store');
                        Route::get('/fitur/{feature}/edit', [AdminController::class, 'featuresEdit'])->name('features.edit');
                        Route::put('/fitur/{feature}', [AdminController::class, 'featuresUpdate'])->name('features.update');
                        Route::delete('/fitur/{feature}', [AdminController::class, 'featuresDestroy'])->name('features.destroy');

                        Route::get('/testimoni', [AdminController::class, 'testimonialsIndex'])->name('testimonials.index');
                        Route::get('/testimoni/buat', [AdminController::class, 'testimonialsCreate'])->name('testimonials.create');
                        Route::post('/testimoni', [AdminController::class, 'testimonialsStore'])->name('testimonials.store');
                        Route::get('/testimoni/{testimonial}/edit', [AdminController::class, 'testimonialsEdit'])->name('testimonials.edit');
                        Route::put('/testimoni/{testimonial}', [AdminController::class, 'testimonialsUpdate'])->name('testimonials.update');
                        Route::delete('/testimoni/{testimonial}', [AdminController::class, 'testimonialsDestroy'])->name('testimonials.destroy');

                        Route::get('/terms', [AdminController::class, 'termsDocumentsIndex'])->name('terms.index');
                        Route::get('/terms/buat', [AdminController::class, 'termsDocumentsCreate'])->name('terms.create');
                        Route::post('/terms', [AdminController::class, 'termsDocumentsStore'])->name('terms.store');
                        Route::get('/terms/{termsDocument}/edit', [AdminController::class, 'termsDocumentsEdit'])->name('terms.edit');
                        Route::put('/terms/{termsDocument}', [AdminController::class, 'termsDocumentsUpdate'])->name('terms.update');
                        Route::delete('/terms/{termsDocument}', [AdminController::class, 'termsDocumentsDestroy'])->name('terms.destroy');

                        Route::get('/privacy', [AdminController::class, 'privacyPoliciesIndex'])->name('privacy.index');
                        Route::get('/privacy/buat', [AdminController::class, 'privacyPoliciesCreate'])->name('privacy.create');
                        Route::post('/privacy', [AdminController::class, 'privacyPoliciesStore'])->name('privacy.store');
                        Route::get('/privacy/{privacyPolicy}/edit', [AdminController::class, 'privacyPoliciesEdit'])->name('privacy.edit');
                        Route::put('/privacy/{privacyPolicy}', [AdminController::class, 'privacyPoliciesUpdate'])->name('privacy.update');
                        Route::delete('/privacy/{privacyPolicy}', [AdminController::class, 'privacyPoliciesDestroy'])->name('privacy.destroy');

                        Route::get('/consent', [AdminController::class, 'consentDocumentsIndex'])->name('consent.index');
                        Route::get('/consent/buat', [AdminController::class, 'consentDocumentsCreate'])->name('consent.create');
                        Route::post('/consent', [AdminController::class, 'consentDocumentsStore'])->name('consent.store');
                        Route::get('/consent/{consentDocument}/edit', [AdminController::class, 'consentDocumentsEdit'])->name('consent.edit');
                        Route::put('/consent/{consentDocument}', [AdminController::class, 'consentDocumentsUpdate'])->name('consent.update');
                        Route::delete('/consent/{consentDocument}', [AdminController::class, 'consentDocumentsDestroy'])->name('consent.destroy');
                        Route::post('/consent/{consentDocument}/publish', [AdminController::class, 'consentDocumentsPublish'])->name('consent.publish');

                        Route::get('/persetujuan-pengguna', [AdminController::class, 'appraisalUserConsentsIndex'])->name('user-consents.index');
                        Route::get('/persetujuan-pengguna/{appraisalUserConsent}', [AdminController::class, 'appraisalUserConsentsShow'])->name('user-consents.show');
                    });
            });

        Route::prefix('master-data')
            ->name('master-data.')
            ->group(function (): void {
                Route::get('/location-id-preview', [AdminController::class, 'locationIdPreview'])->name('locations.id-preview');
                Route::get('/location-options', [AdminController::class, 'locationOptions'])->name('locations.options');
                Route::get('/users', [AdminController::class, 'usersIndex'])->name('users.index');
                Route::get('/users/buat', [AdminController::class, 'usersCreate'])->name('users.create');
                Route::post('/users', [AdminController::class, 'usersStore'])->name('users.store');
                Route::get('/users/{user}', [AdminController::class, 'usersShow'])->name('users.show');
                Route::get('/users/{user}/edit', [AdminController::class, 'usersEdit'])->name('users.edit');
                Route::put('/users/{user}', [AdminController::class, 'usersUpdate'])->name('users.update');

                Route::get('/provinsi', [AdminController::class, 'provincesIndex'])->name('provinces.index');
                Route::get('/provinsi/buat', [AdminController::class, 'provincesCreate'])->name('provinces.create');
                Route::post('/provinsi', [AdminController::class, 'provincesStore'])->name('provinces.store');
                Route::get('/provinsi/{province}/edit', [AdminController::class, 'provincesEdit'])->name('provinces.edit');
                Route::put('/provinsi/{province}', [AdminController::class, 'provincesUpdate'])->name('provinces.update');
                Route::delete('/provinsi/{province}', [AdminController::class, 'provincesDestroy'])->name('provinces.destroy');

                Route::get('/kabupaten-kota', [AdminController::class, 'regenciesIndex'])->name('regencies.index');
                Route::get('/kabupaten-kota/buat', [AdminController::class, 'regenciesCreate'])->name('regencies.create');
                Route::post('/kabupaten-kota', [AdminController::class, 'regenciesStore'])->name('regencies.store');
                Route::get('/kabupaten-kota/{regency}/edit', [AdminController::class, 'regenciesEdit'])->name('regencies.edit');
                Route::put('/kabupaten-kota/{regency}', [AdminController::class, 'regenciesUpdate'])->name('regencies.update');
                Route::delete('/kabupaten-kota/{regency}', [AdminController::class, 'regenciesDestroy'])->name('regencies.destroy');

                Route::get('/kecamatan', [AdminController::class, 'districtsIndex'])->name('districts.index');
                Route::get('/kecamatan/buat', [AdminController::class, 'districtsCreate'])->name('districts.create');
                Route::post('/kecamatan', [AdminController::class, 'districtsStore'])->name('districts.store');
                Route::get('/kecamatan/{district}/edit', [AdminController::class, 'districtsEdit'])->name('districts.edit');
                Route::put('/kecamatan/{district}', [AdminController::class, 'districtsUpdate'])->name('districts.update');
                Route::delete('/kecamatan/{district}', [AdminController::class, 'districtsDestroy'])->name('districts.destroy');

                Route::get('/kelurahan-desa', [AdminController::class, 'villagesIndex'])->name('villages.index');
                Route::get('/kelurahan-desa/buat', [AdminController::class, 'villagesCreate'])->name('villages.create');
                Route::post('/kelurahan-desa', [AdminController::class, 'villagesStore'])->name('villages.store');
                Route::get('/kelurahan-desa/{village}/edit', [AdminController::class, 'villagesEdit'])->name('villages.edit');
                Route::put('/kelurahan-desa/{village}', [AdminController::class, 'villagesUpdate'])->name('villages.update');
                Route::delete('/kelurahan-desa/{village}', [AdminController::class, 'villagesDestroy'])->name('villages.destroy');
            });

        Route::prefix('ref-guidelines')
            ->name('ref-guidelines.')
            ->group(function (): void {
                Route::get('/guideline-sets', [AdminController::class, 'guidelineSetsIndex'])->name('guideline-sets.index');
                Route::get('/guideline-sets/buat', [AdminController::class, 'guidelineSetsCreate'])->name('guideline-sets.create');
                Route::post('/guideline-sets', [AdminController::class, 'guidelineSetsStore'])->name('guideline-sets.store');
                Route::get('/guideline-sets/{guidelineSet}/edit', [AdminController::class, 'guidelineSetsEdit'])->name('guideline-sets.edit');
                Route::put('/guideline-sets/{guidelineSet}', [AdminController::class, 'guidelineSetsUpdate'])->name('guideline-sets.update');
                Route::delete('/guideline-sets/{guidelineSet}', [AdminController::class, 'guidelineSetsDestroy'])->name('guideline-sets.destroy');

                Route::get('/ikk', [AdminController::class, 'constructionCostIndicesIndex'])->name('construction-cost-indices.index');
                Route::get('/ikk/buat', [AdminController::class, 'constructionCostIndicesCreate'])->name('construction-cost-indices.create');
                Route::post('/ikk', [AdminController::class, 'constructionCostIndicesStore'])->name('construction-cost-indices.store');
                Route::get('/ikk/{constructionCostIndex}/edit', [AdminController::class, 'constructionCostIndicesEdit'])->name('construction-cost-indices.edit');
                Route::put('/ikk/{constructionCostIndex}', [AdminController::class, 'constructionCostIndicesUpdate'])->name('construction-cost-indices.update');
                Route::delete('/ikk/{constructionCostIndex}', [AdminController::class, 'constructionCostIndicesDestroy'])->name('construction-cost-indices.destroy');

                Route::get('/cost-elements', [AdminController::class, 'costElementsIndex'])->name('cost-elements.index');
                Route::get('/cost-elements/buat', [AdminController::class, 'costElementsCreate'])->name('cost-elements.create');
                Route::post('/cost-elements', [AdminController::class, 'costElementsStore'])->name('cost-elements.store');
                Route::get('/cost-elements/{costElement}/edit', [AdminController::class, 'costElementsEdit'])->name('cost-elements.edit');
                Route::put('/cost-elements/{costElement}', [AdminController::class, 'costElementsUpdate'])->name('cost-elements.update');
                Route::delete('/cost-elements/{costElement}', [AdminController::class, 'costElementsDestroy'])->name('cost-elements.destroy');

                Route::get('/floor-indices', [AdminController::class, 'floorIndicesIndex'])->name('floor-indices.index');
                Route::get('/floor-indices/buat', [AdminController::class, 'floorIndicesCreate'])->name('floor-indices.create');
                Route::post('/floor-indices', [AdminController::class, 'floorIndicesStore'])->name('floor-indices.store');
                Route::get('/floor-indices/{floorIndex}/edit', [AdminController::class, 'floorIndicesEdit'])->name('floor-indices.edit');
                Route::put('/floor-indices/{floorIndex}', [AdminController::class, 'floorIndicesUpdate'])->name('floor-indices.update');
                Route::delete('/floor-indices/{floorIndex}', [AdminController::class, 'floorIndicesDestroy'])->name('floor-indices.destroy');

                Route::get('/mappi-rcn-standards', [AdminController::class, 'mappiRcnStandardsIndex'])->name('mappi-rcn-standards.index');
                Route::get('/mappi-rcn-standards/buat', [AdminController::class, 'mappiRcnStandardsCreate'])->name('mappi-rcn-standards.create');
                Route::post('/mappi-rcn-standards', [AdminController::class, 'mappiRcnStandardsStore'])->name('mappi-rcn-standards.store');
                Route::get('/mappi-rcn-standards/{mappiRcnStandard}/edit', [AdminController::class, 'mappiRcnStandardsEdit'])->name('mappi-rcn-standards.edit');
                Route::put('/mappi-rcn-standards/{mappiRcnStandard}', [AdminController::class, 'mappiRcnStandardsUpdate'])->name('mappi-rcn-standards.update');
                Route::delete('/mappi-rcn-standards/{mappiRcnStandard}', [AdminController::class, 'mappiRcnStandardsDestroy'])->name('mappi-rcn-standards.destroy');

                Route::get('/valuation-settings', [AdminController::class, 'valuationSettingsIndex'])->name('valuation-settings.index');
                Route::get('/valuation-settings/buat', [AdminController::class, 'valuationSettingsCreate'])->name('valuation-settings.create');
                Route::post('/valuation-settings', [AdminController::class, 'valuationSettingsStore'])->name('valuation-settings.store');
                Route::get('/valuation-settings/{valuationSetting}/edit', [AdminController::class, 'valuationSettingsEdit'])->name('valuation-settings.edit');
                Route::put('/valuation-settings/{valuationSetting}', [AdminController::class, 'valuationSettingsUpdate'])->name('valuation-settings.update');
                Route::delete('/valuation-settings/{valuationSetting}', [AdminController::class, 'valuationSettingsDestroy'])->name('valuation-settings.destroy');
            });

        Route::prefix('hak-akses')
            ->name('access-control.')
            ->group(function (): void {
                Route::get('/roles', [AdminController::class, 'rolesIndex'])->name('roles.index');
                Route::get('/roles/buat', [AdminController::class, 'rolesCreate'])->name('roles.create');
                Route::post('/roles', [AdminController::class, 'rolesStore'])->name('roles.store');
                Route::get('/roles/{role}', [AdminController::class, 'rolesShow'])->name('roles.show');
                Route::get('/roles/{role}/edit', [AdminController::class, 'rolesEdit'])->name('roles.edit');
                Route::put('/roles/{role}', [AdminController::class, 'rolesUpdate'])->name('roles.update');
                Route::delete('/roles/{role}', [AdminController::class, 'rolesDestroy'])->name('roles.destroy');
            });

        Route::prefix('komunikasi')
            ->name('communications.')
            ->group(function (): void {
                Route::get('/contact-messages', [AdminController::class, 'contactMessagesIndex'])->name('contact-messages.index');
                Route::get('/contact-messages/{contactMessage}', [AdminController::class, 'contactMessagesShow'])->name('contact-messages.show');
                Route::post('/contact-messages/{contactMessage}/in-progress', [AdminController::class, 'contactMessagesMarkInProgress'])->name('contact-messages.in-progress');
                Route::post('/contact-messages/{contactMessage}/done', [AdminController::class, 'contactMessagesMarkDone'])->name('contact-messages.done');
                Route::post('/contact-messages/{contactMessage}/archive', [AdminController::class, 'contactMessagesArchive'])->name('contact-messages.archive');
                Route::delete('/contact-messages/{contactMessage}', [AdminController::class, 'contactMessagesDestroy'])->name('contact-messages.destroy');
            });

        Route::get('/modul/{module}', [AdminController::class, 'moduleShow'])->name('modules.show');
    });
