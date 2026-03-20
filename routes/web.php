<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AppraisalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserNotificationController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Reviewer\ReviewerController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\EmailVerificationController;

// landing route
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/policy', [LandingController::class, 'policy'])->name('policy');
Route::get('/terms', [LandingController::class, 'terms'])->name('terms');
Route::get('/contact', [LandingController::class, 'contact'])->name('contact')->middleware('throttle:10,1');
Route::post('/contact', [LandingController::class, 'storeContact'])->name('contact.store');
Route::get('/artikel', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/artikel/{slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::post('/payments/midtrans/notification', [PaymentController::class, 'midtransNotification'])
    ->name('payments.midtrans.notification');

Route::middleware('guest')->group(function () {
    //auth route
    Route::get('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'processRegister'])->name('register.proccess')->middleware('throttle:10,1');
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'processLogin'])->name('login.proccess');
    Route::get('/fortify/two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])->name('two-factor.login');

    //reset and forgot password
    Route::get('/forgot-password',[ForgotPasswordController::class,'showForgotForm'])->name('password.request');
    Route::post('/forgot-password',[ForgotPasswordController::class,'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}',[ResetPasswordController::class,'showResetForm'])->name('password.reset');
    Route::post('/reset-password',[ResetPasswordController::class,'resetPassword'])->name('password.update');
});


Route::middleware(['auth', 'verified'])->group(function () {
    //notification route
    Route::post('/notifications/{id}/read', [UserNotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [UserNotificationController::class, 'readAll'])->name('notifications.readAll');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('not.reviewer')->group(function (): void {
        // user dashboard route
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // appraisal route
        Route::get('/permohonan-penilaian', [AppraisalController::class, 'index'])->name('appraisal.list');
        Route::get('/buat-permohonan', [AppraisalController::class, 'create'])->name('appraisal.create');
        Route::post('/buat-permohonan', [AppraisalController::class, 'store'])->name('appraisal.store');
        Route::get('/permohonan-penilaian/{id}', [AppraisalController::class, 'show'])->name('appraisal.show');
        Route::get('/permohonan-penilaian/{id}/penawaran', [AppraisalController::class, 'offerPage'])->name('appraisal.offer.page');
        Route::post('/permohonan-penilaian/{id}/offer/accept', [AppraisalController::class, 'acceptOffer'])->name('appraisal.offer.accept');
        Route::post('/permohonan-penilaian/{id}/offer/negotiate', [AppraisalController::class, 'submitOfferNegotiation'])->name('appraisal.offer.negotiate');
        Route::post('/permohonan-penilaian/{id}/offer/select', [AppraisalController::class, 'selectOffer'])->name('appraisal.offer.select');
        Route::post('/permohonan-penilaian/{id}/offer/cancel', [AppraisalController::class, 'cancelOffer'])->name('appraisal.offer.cancel');
        Route::get('/permohonan-penilaian/{id}/kontrak', [AppraisalController::class, 'contractSignaturePage'])->name('appraisal.contract.page');
        Route::get('/permohonan-penilaian/{id}/kontrak/pdf', [AppraisalController::class, 'downloadContractPdf'])->name('appraisal.contract.pdf');
        Route::post('/permohonan-penilaian/{id}/kontrak/sign', [AppraisalController::class, 'signContract'])->name('appraisal.contract.sign');
        Route::get('/permohonan-penilaian/{id}/pembayaran', [PaymentController::class, 'appraisalPage'])->name('appraisal.payment.page');
        Route::get('/permohonan-penilaian/{id}/invoice', [PaymentController::class, 'invoicePage'])->name('appraisal.invoice.page');
        Route::get('/permohonan-penilaian/{id}/invoice/pdf', [PaymentController::class, 'downloadInvoicePdf'])->name('appraisal.invoice.pdf');
        Route::post('/permohonan-penilaian/{id}/pembayaran/session', [PaymentController::class, 'createMidtransSession'])->name('appraisal.payment.session');

        Route::post('/buat-permohonan/consent', [AppraisalController::class, 'acceptConsent'])->name('appraisal.consent.accept');
        Route::post('/buat-permohonan/consent/decline', [AppraisalController::class, 'declineConsent'])->name('appraisal.consent.decline');

        // laporan penilaian (mock)
        Route::get('/laporan-penilaian', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan-penilaian/{id}', [ReportController::class, 'show'])->name('reports.show');

        // pembayaran (mock)
        Route::get('/pembayaran', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/pembayaran/{id}', [PaymentController::class, 'show'])->name('payments.show');

        // profile route
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('/profile/password/verify', [ProfileController::class, 'verifyCurrentPassword'])->name('profile.password.verify');
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    });

    Route::prefix('reviewer')
        ->name('reviewer.')
        ->middleware('reviewer.role')
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
});

//email verification
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::get('/email/verified-success', [EmailVerificationController::class, 'success'])
        ->middleware('verified')
        ->name('verification.success');

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});
