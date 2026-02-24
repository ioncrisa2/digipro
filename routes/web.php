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
    // user dashboard route
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    //notification route
    Route::post('/notifications/{id}/read', [UserNotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [UserNotificationController::class, 'readAll'])->name('notifications.readAll');

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
    Route::post('/permohonan-penilaian/{id}/pembayaran/proof', [PaymentController::class, 'uploadProof'])->name('appraisal.payment.upload');

    Route::post('/buat-permohonan/consent', [AppraisalController::class, 'acceptConsent'])->name('appraisal.consent.accept');
    Route::post('/buat-permohonan/consent/decline', [AppraisalController::class, 'declineConsent'])->name('appraisal.consent.decline');

    // laporan penilaian (mock)
    Route::get('/laporan-penilaian', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/laporan-penilaian/{id}', [ReportController::class, 'show'])->name('reports.show');

    // pembayaran (mock)
    Route::get('/pembayaran', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/pembayaran/{id}', [PaymentController::class, 'show'])->name('payments.show');

    //profile route
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/password/verify', [ProfileController::class, 'verifyCurrentPassword'])->name('profile.password.verify');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
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
