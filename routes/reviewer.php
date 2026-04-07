<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Reviewer\AdjustmentController;
use App\Http\Controllers\Reviewer\AssetController;
use App\Http\Controllers\Reviewer\BtbController;
use App\Http\Controllers\Reviewer\ComparableController;
use App\Http\Controllers\Reviewer\DashboardController;
use App\Http\Controllers\Reviewer\ReviewController;
use App\Support\SystemNavigation;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'reviewer.role'])
    ->prefix('reviewer')
    ->name('reviewer.')
    ->group(function (): void {
        Route::middleware('system.section:' . SystemNavigation::ACCESS_REVIEWER_DASHBOARD)
            ->group(function (): void {
                Route::get('/', DashboardController::class)->name('dashboard');
            });

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/location-options', [ProfileController::class, 'locationOptions'])->name('profile.location-options');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('/profile/password/verify', [ProfileController::class, 'verifyCurrentPassword'])->name('profile.password.verify');
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');

        Route::middleware('system.section:' . SystemNavigation::MANAGE_REVIEWER_REVIEWS)
            ->group(function (): void {
                Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
                Route::get('/reviews/{review}', [ReviewController::class, 'show'])->name('reviews.show');

                Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
                Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
                Route::get('/assets/{asset}/adjustment', [AssetController::class, 'adjustment'])->name('assets.adjustment');
                Route::get('/assets/{asset}/btb', [AssetController::class, 'btb'])->name('assets.btb');
            });

        Route::middleware('system.section:' . SystemNavigation::MANAGE_REVIEWER_COMPARABLES)
            ->group(function (): void {
                Route::get('/comparables', [ComparableController::class, 'index'])->name('comparables.index');
                Route::get('/comparables/{comparable}', [ComparableController::class, 'show'])->name('comparables.show');
            });

        Route::middleware('system.section:' . SystemNavigation::MANAGE_ADMIN_MASTER_DATA)
            ->prefix('master-data')
            ->name('master-data.')
            ->group(function (): void {
                Route::get('/location-id-preview', [\App\Http\Controllers\Admin\MasterDataController::class, 'locationIdPreview'])->name('locations.id-preview');
                Route::get('/location-options', [\App\Http\Controllers\Admin\MasterDataController::class, 'locationOptions'])->name('locations.options');

                Route::get('/provinsi', [\App\Http\Controllers\Admin\MasterDataController::class, 'provincesIndex'])->name('provinces.index');
                Route::get('/provinsi/buat', [\App\Http\Controllers\Admin\MasterDataController::class, 'provincesCreate'])->name('provinces.create');
                Route::post('/provinsi', [\App\Http\Controllers\Admin\MasterDataController::class, 'provincesStore'])->name('provinces.store');
                Route::get('/provinsi/{province}/edit', [\App\Http\Controllers\Admin\MasterDataController::class, 'provincesEdit'])->name('provinces.edit');
                Route::put('/provinsi/{province}', [\App\Http\Controllers\Admin\MasterDataController::class, 'provincesUpdate'])->name('provinces.update');
                Route::delete('/provinsi/{province}', [\App\Http\Controllers\Admin\MasterDataController::class, 'provincesDestroy'])->name('provinces.destroy');

                Route::get('/kabupaten-kota', [\App\Http\Controllers\Admin\MasterDataController::class, 'regenciesIndex'])->name('regencies.index');
                Route::get('/kabupaten-kota/buat', [\App\Http\Controllers\Admin\MasterDataController::class, 'regenciesCreate'])->name('regencies.create');
                Route::post('/kabupaten-kota', [\App\Http\Controllers\Admin\MasterDataController::class, 'regenciesStore'])->name('regencies.store');
                Route::get('/kabupaten-kota/{regency}/edit', [\App\Http\Controllers\Admin\MasterDataController::class, 'regenciesEdit'])->name('regencies.edit');
                Route::put('/kabupaten-kota/{regency}', [\App\Http\Controllers\Admin\MasterDataController::class, 'regenciesUpdate'])->name('regencies.update');
                Route::delete('/kabupaten-kota/{regency}', [\App\Http\Controllers\Admin\MasterDataController::class, 'regenciesDestroy'])->name('regencies.destroy');

                Route::get('/kecamatan', [\App\Http\Controllers\Admin\MasterDataController::class, 'districtsIndex'])->name('districts.index');
                Route::get('/kecamatan/buat', [\App\Http\Controllers\Admin\MasterDataController::class, 'districtsCreate'])->name('districts.create');
                Route::post('/kecamatan', [\App\Http\Controllers\Admin\MasterDataController::class, 'districtsStore'])->name('districts.store');
                Route::get('/kecamatan/{district}/edit', [\App\Http\Controllers\Admin\MasterDataController::class, 'districtsEdit'])->name('districts.edit');
                Route::put('/kecamatan/{district}', [\App\Http\Controllers\Admin\MasterDataController::class, 'districtsUpdate'])->name('districts.update');
                Route::delete('/kecamatan/{district}', [\App\Http\Controllers\Admin\MasterDataController::class, 'districtsDestroy'])->name('districts.destroy');

                Route::get('/kelurahan-desa', [\App\Http\Controllers\Admin\MasterDataController::class, 'villagesIndex'])->name('villages.index');
                Route::get('/kelurahan-desa/buat', [\App\Http\Controllers\Admin\MasterDataController::class, 'villagesCreate'])->name('villages.create');
                Route::post('/kelurahan-desa', [\App\Http\Controllers\Admin\MasterDataController::class, 'villagesStore'])->name('villages.store');
                Route::get('/kelurahan-desa/{village}/edit', [\App\Http\Controllers\Admin\MasterDataController::class, 'villagesEdit'])->name('villages.edit');
                Route::put('/kelurahan-desa/{village}', [\App\Http\Controllers\Admin\MasterDataController::class, 'villagesUpdate'])->name('villages.update');
                Route::delete('/kelurahan-desa/{village}', [\App\Http\Controllers\Admin\MasterDataController::class, 'villagesDestroy'])->name('villages.destroy');
            });

        Route::middleware('system.section:' . SystemNavigation::MANAGE_ADMIN_REF_GUIDELINES)
            ->prefix('ref-guidelines')
            ->name('ref-guidelines.')
            ->group(function (): void {
                Route::get('/guideline-sets', [\App\Http\Controllers\Admin\ReferenceGuideSettingsController::class, 'guidelineSetsIndex'])->name('guideline-sets.index');
                Route::get('/guideline-sets/export', [\App\Http\Controllers\Admin\ReferenceGuideSettingsController::class, 'guidelineSetsExport'])->name('guideline-sets.export');
                Route::get('/guideline-sets/buat', [\App\Http\Controllers\Admin\ReferenceGuideSettingsController::class, 'guidelineSetsCreate'])->name('guideline-sets.create');
                Route::post('/guideline-sets', [\App\Http\Controllers\Admin\ReferenceGuideSettingsController::class, 'guidelineSetsStore'])->name('guideline-sets.store');
                Route::get('/guideline-sets/{guidelineSet}/edit', [\App\Http\Controllers\Admin\ReferenceGuideSettingsController::class, 'guidelineSetsEdit'])->name('guideline-sets.edit');
                Route::put('/guideline-sets/{guidelineSet}', [\App\Http\Controllers\Admin\ReferenceGuideSettingsController::class, 'guidelineSetsUpdate'])->name('guideline-sets.update');
                Route::delete('/guideline-sets/{guidelineSet}', [\App\Http\Controllers\Admin\ReferenceGuideSettingsController::class, 'guidelineSetsDestroy'])->name('guideline-sets.destroy');

                Route::get('/ikk', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'constructionCostIndicesIndex'])->name('construction-cost-indices.index');
                Route::get('/ikk/export', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'constructionCostIndicesExport'])->name('construction-cost-indices.export');
                Route::get('/ikk/buat', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'constructionCostIndicesCreate'])->name('construction-cost-indices.create');
                Route::post('/ikk', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'constructionCostIndicesStore'])->name('construction-cost-indices.store');
                Route::post('/ikk/import', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'constructionCostIndicesImport'])->name('construction-cost-indices.import');
                Route::get('/ikk/{constructionCostIndex}/edit', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'constructionCostIndicesEdit'])->name('construction-cost-indices.edit');
                Route::put('/ikk/{constructionCostIndex}', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'constructionCostIndicesUpdate'])->name('construction-cost-indices.update');
                Route::delete('/ikk/{constructionCostIndex}', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'constructionCostIndicesDestroy'])->name('construction-cost-indices.destroy');

                Route::get('/cost-elements', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'costElementsIndex'])->name('cost-elements.index');
                Route::get('/cost-elements/export', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'costElementsExport'])->name('cost-elements.export');
                Route::get('/cost-elements/buat', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'costElementsCreate'])->name('cost-elements.create');
                Route::post('/cost-elements', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'costElementsStore'])->name('cost-elements.store');
                Route::post('/cost-elements/import', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'costElementsImport'])->name('cost-elements.import');
                Route::get('/cost-elements/{costElement}/edit', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'costElementsEdit'])->name('cost-elements.edit');
                Route::put('/cost-elements/{costElement}', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'costElementsUpdate'])->name('cost-elements.update');
                Route::delete('/cost-elements/{costElement}', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'costElementsDestroy'])->name('cost-elements.destroy');

                Route::get('/floor-indices', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'floorIndicesIndex'])->name('floor-indices.index');
                Route::get('/floor-indices/export', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'floorIndicesExport'])->name('floor-indices.export');
                Route::get('/floor-indices/buat', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'floorIndicesCreate'])->name('floor-indices.create');
                Route::post('/floor-indices', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'floorIndicesStore'])->name('floor-indices.store');
                Route::post('/floor-indices/import', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'floorIndicesImport'])->name('floor-indices.import');
                Route::get('/floor-indices/{floorIndex}/edit', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'floorIndicesEdit'])->name('floor-indices.edit');
                Route::put('/floor-indices/{floorIndex}', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'floorIndicesUpdate'])->name('floor-indices.update');
                Route::delete('/floor-indices/{floorIndex}', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'floorIndicesDestroy'])->name('floor-indices.destroy');

                Route::get('/mappi-rcn-standards', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'mappiRcnStandardsIndex'])->name('mappi-rcn-standards.index');
                Route::get('/mappi-rcn-standards/export', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'mappiRcnStandardsExport'])->name('mappi-rcn-standards.export');
                Route::get('/mappi-rcn-standards/buat', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'mappiRcnStandardsCreate'])->name('mappi-rcn-standards.create');
                Route::post('/mappi-rcn-standards', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'mappiRcnStandardsStore'])->name('mappi-rcn-standards.store');
                Route::post('/mappi-rcn-standards/import', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'mappiRcnStandardsImport'])->name('mappi-rcn-standards.import');
                Route::get('/mappi-rcn-standards/{mappiRcnStandard}/edit', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'mappiRcnStandardsEdit'])->name('mappi-rcn-standards.edit');
                Route::put('/mappi-rcn-standards/{mappiRcnStandard}', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'mappiRcnStandardsUpdate'])->name('mappi-rcn-standards.update');
                Route::delete('/mappi-rcn-standards/{mappiRcnStandard}', [\App\Http\Controllers\Admin\ReferenceGuideDataController::class, 'mappiRcnStandardsDestroy'])->name('mappi-rcn-standards.destroy');

                Route::get('/building-economic-lives', [\App\Http\Controllers\Admin\BuildingEconomicLifeController::class, 'index'])->name('building-economic-lives.index');
                Route::get('/building-economic-lives/export', [\App\Http\Controllers\Admin\BuildingEconomicLifeController::class, 'export'])->name('building-economic-lives.export');
                Route::get('/building-economic-lives/buat', [\App\Http\Controllers\Admin\BuildingEconomicLifeController::class, 'create'])->name('building-economic-lives.create');
                Route::post('/building-economic-lives', [\App\Http\Controllers\Admin\BuildingEconomicLifeController::class, 'store'])->name('building-economic-lives.store');
                Route::post('/building-economic-lives/import', [\App\Http\Controllers\Admin\BuildingEconomicLifeController::class, 'import'])->name('building-economic-lives.import');
                Route::get('/building-economic-lives/{buildingEconomicLife}/edit', [\App\Http\Controllers\Admin\BuildingEconomicLifeController::class, 'edit'])->name('building-economic-lives.edit');
                Route::put('/building-economic-lives/{buildingEconomicLife}', [\App\Http\Controllers\Admin\BuildingEconomicLifeController::class, 'update'])->name('building-economic-lives.update');
                Route::delete('/building-economic-lives/{buildingEconomicLife}', [\App\Http\Controllers\Admin\BuildingEconomicLifeController::class, 'destroy'])->name('building-economic-lives.destroy');

                Route::get('/ikk-by-province', [\App\Http\Controllers\Admin\IkkByProvinceController::class, 'index'])->name('ikk-by-province.index');
                Route::post('/ikk-by-province', [\App\Http\Controllers\Admin\IkkByProvinceController::class, 'save'])->name('ikk-by-province.save');

                Route::get('/valuation-settings', [\App\Http\Controllers\Admin\ReferenceGuideSettingsController::class, 'valuationSettingsIndex'])->name('valuation-settings.index');
                Route::get('/valuation-settings/buat', [\App\Http\Controllers\Admin\ReferenceGuideSettingsController::class, 'valuationSettingsCreate'])->name('valuation-settings.create');
                Route::post('/valuation-settings', [\App\Http\Controllers\Admin\ReferenceGuideSettingsController::class, 'valuationSettingsStore'])->name('valuation-settings.store');
                Route::get('/valuation-settings/{valuationSetting}/edit', [\App\Http\Controllers\Admin\ReferenceGuideSettingsController::class, 'valuationSettingsEdit'])->name('valuation-settings.edit');
                Route::put('/valuation-settings/{valuationSetting}', [\App\Http\Controllers\Admin\ReferenceGuideSettingsController::class, 'valuationSettingsUpdate'])->name('valuation-settings.update');
                Route::delete('/valuation-settings/{valuationSetting}', [\App\Http\Controllers\Admin\ReferenceGuideSettingsController::class, 'valuationSettingsDestroy'])->name('valuation-settings.destroy');
            });

        Route::prefix('api')->name('api.')->group(function (): void {
            Route::middleware('system.section:' . SystemNavigation::MANAGE_REVIEWER_REVIEWS)
                ->group(function (): void {
                    Route::post('/reviews/{review}/start', [ReviewController::class, 'start'])->name('reviews.start');
                    Route::post('/reviews/{review}/finish', [ReviewController::class, 'finish'])->name('reviews.finish');

                    Route::post('/assets/{asset}/general-data', [AssetController::class, 'updateGeneralData'])->name('assets.general-data');
                    Route::post('/assets/{asset}/adjustment/preview', [AdjustmentController::class, 'preview'])->name('assets.adjustment.preview');
                    Route::post('/assets/{asset}/adjustment/save', [AdjustmentController::class, 'save'])->name('assets.adjustment.save');
                    Route::post('/assets/{asset}/btb/preview', [BtbController::class, 'preview'])->name('assets.btb.preview');
                    Route::post('/assets/{asset}/btb/save', [BtbController::class, 'save'])->name('assets.btb.save');
                });

            Route::middleware('system.section:' . SystemNavigation::MANAGE_REVIEWER_COMPARABLES)
                ->group(function (): void {
                    Route::post('/assets/{asset}/comparables/search', [ComparableController::class, 'search'])->name('assets.comparables.search');
                    Route::post('/assets/{asset}/comparables/sync', [ComparableController::class, 'sync'])->name('assets.comparables.sync');
                    Route::post('/comparables/{comparable}', [ComparableController::class, 'update'])->name('comparables.update');
                });
        });
    });
