<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reviewer\ReviewerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'reviewer.role'])
    ->prefix('reviewer')
    ->name('reviewer.')
    ->group(function (): void {
        Route::get('/', [ReviewerController::class, 'dashboard'])->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('/profile/password/verify', [ProfileController::class, 'verifyCurrentPassword'])->name('profile.password.verify');
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');

        Route::get('/reviews', [ReviewerController::class, 'reviewsIndex'])->name('reviews.index');
        Route::get('/reviews/{review}', [ReviewerController::class, 'reviewsShow'])->name('reviews.show');

        Route::get('/assets', [ReviewerController::class, 'assetsIndex'])->name('assets.index');
        Route::get('/assets/{asset}', [ReviewerController::class, 'assetsShow'])->name('assets.show');
        Route::get('/assets/{asset}/adjustment', [ReviewerController::class, 'assetsAdjustment'])->name('assets.adjustment');
        Route::get('/assets/{asset}/btb', [ReviewerController::class, 'assetsBtb'])->name('assets.btb');

        Route::get('/comparables', [ReviewerController::class, 'comparablesIndex'])->name('comparables.index');
        Route::get('/comparables/{comparable}', [ReviewerController::class, 'comparablesShow'])->name('comparables.show');

        Route::prefix('api')->name('api.')->group(function (): void {
            Route::post('/reviews/{review}/start', [ReviewerController::class, 'startReview'])->name('reviews.start');
            Route::post('/reviews/{review}/finish', [ReviewerController::class, 'finishReview'])->name('reviews.finish');

            Route::post('/assets/{asset}/general-data', [ReviewerController::class, 'updateGeneralData'])->name('assets.general-data');
            Route::post('/assets/{asset}/comparables/search', [ReviewerController::class, 'searchComparables'])->name('assets.comparables.search');
            Route::post('/assets/{asset}/comparables/sync', [ReviewerController::class, 'syncComparables'])->name('assets.comparables.sync');
            Route::post('/assets/{asset}/adjustment/preview', [ReviewerController::class, 'previewAdjustment'])->name('assets.adjustment.preview');
            Route::post('/assets/{asset}/adjustment/save', [ReviewerController::class, 'saveAdjustment'])->name('assets.adjustment.save');
            Route::post('/assets/{asset}/btb/preview', [ReviewerController::class, 'previewBtb'])->name('assets.btb.preview');
            Route::post('/assets/{asset}/btb/save', [ReviewerController::class, 'saveBtb'])->name('assets.btb.save');

            Route::post('/comparables/{comparable}', [ReviewerController::class, 'updateComparable'])->name('comparables.update');
        });
    });
