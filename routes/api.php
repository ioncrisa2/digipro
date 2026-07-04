<?php

use App\Http\Controllers\Api\V1\Auth\MobileAuthController;
use App\Http\Controllers\Api\V1\Auth\MobileEmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\MobilePasswordController;
use App\Http\Controllers\Api\V1\Auth\MobileTwoFactorController;
use App\Http\Controllers\Api\V1\Customer\MobileApiStatusController;
use App\Http\Controllers\Api\V1\Customer\MobileAppraisalConsentController;
use App\Http\Controllers\Api\V1\Customer\MobileAppraisalController;
use App\Http\Controllers\Api\V1\Customer\MobileAppraisalDraftController;
use App\Http\Controllers\Api\V1\Customer\MobileDashboardController;
use App\Http\Controllers\Api\V1\Customer\MobileNotificationController;
use App\Http\Controllers\Api\V1\Customer\MobileProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                Route::post('/register', [MobileAuthController::class, 'register'])
                    ->middleware('throttle:mobile-auth-register')
                    ->name('register');
                Route::post('/login', [MobileAuthController::class, 'login'])
                    ->middleware('throttle:mobile-auth-login')
                    ->name('login');
                Route::post('/two-factor/verify', MobileTwoFactorController::class)
                    ->middleware('throttle:mobile-auth-two-factor')
                    ->name('two-factor.verify');
                Route::post('/forgot-password', [MobilePasswordController::class, 'forgot'])
                    ->middleware('throttle:mobile-auth-password')
                    ->name('password.forgot');
                Route::post('/reset-password', [MobilePasswordController::class, 'reset'])
                    ->middleware('throttle:mobile-auth-password')
                    ->name('password.reset');
                Route::get('/email/verify/{id}/{hash}', [MobileEmailVerificationController::class, 'verify'])
                    ->middleware(['signed', 'throttle:mobile-auth-verification'])
                    ->name('email.verify');

                Route::middleware(['auth:sanctum', 'abilities:mobile:customer', 'customer.role'])
                    ->group(function (): void {
                        Route::post('/email/verification-notification', [MobileEmailVerificationController::class, 'resend'])
                            ->middleware('throttle:mobile-auth-verification')
                            ->name('email.resend');
                        Route::get('/me', [MobileAuthController::class, 'me'])->name('me');
                        Route::post('/logout', [MobileAuthController::class, 'logout'])->name('logout');
                        Route::post('/logout-all', [MobileAuthController::class, 'logoutAll'])->name('logout-all');
                    });
            });

        Route::middleware(['auth:sanctum', 'abilities:mobile:customer', 'verified', 'customer.role'])
            ->group(function (): void {
                Route::get('/dashboard', MobileDashboardController::class)->name('dashboard');
                Route::get('/appraisals', [MobileAppraisalController::class, 'index'])->name('appraisals.index');
                Route::get('/appraisals/options', [MobileAppraisalController::class, 'options'])->name('appraisals.options');
                Route::post('/appraisals/consent/accept', MobileAppraisalConsentController::class)
                    ->name('appraisals.consent.accept');
                Route::prefix('appraisals/drafts/{draft}')
                    ->whereNumber('draft')
                    ->name('appraisals.drafts.')
                    ->group(function (): void {
                        Route::get('/', [MobileAppraisalDraftController::class, 'show'])->name('show');
                        Route::put('/', [MobileAppraisalDraftController::class, 'update'])->name('update');
                        Route::post('/assets', [MobileAppraisalDraftController::class, 'storeAsset'])->name('assets.store');
                        Route::put('/assets/{asset}', [MobileAppraisalDraftController::class, 'updateAsset'])
                            ->whereNumber('asset')
                            ->name('assets.update');
                        Route::delete('/assets/{asset}', [MobileAppraisalDraftController::class, 'destroyAsset'])
                            ->whereNumber('asset')
                            ->name('assets.destroy');
                        Route::post('/assets/{asset}/files', [MobileAppraisalDraftController::class, 'storeFiles'])
                            ->whereNumber('asset')
                            ->name('files.store');
                        Route::delete('/files/{file}', [MobileAppraisalDraftController::class, 'destroyFile'])
                            ->whereNumber('file')
                            ->name('files.destroy');
                        Route::post('/submit', [MobileAppraisalDraftController::class, 'submit'])->name('submit');
                    });
                Route::post('/appraisals/drafts', [MobileAppraisalDraftController::class, 'store'])
                    ->name('appraisals.drafts.store');
                Route::get('/appraisals/{appraisal}', [MobileAppraisalController::class, 'show'])
                    ->whereNumber('appraisal')
                    ->name('appraisals.show');
                Route::get('/appraisals/{appraisal}/tracking', [MobileAppraisalController::class, 'tracking'])
                    ->whereNumber('appraisal')
                    ->name('appraisals.tracking');
                Route::get('/profile', [MobileProfileController::class, 'show'])->name('profile.show');
                Route::put('/profile', [MobileProfileController::class, 'update'])->name('profile.update');
                Route::get('/profile/location-options', [MobileProfileController::class, 'locationOptions'])
                    ->name('profile.location-options');
                Route::put('/profile/password', [MobileProfileController::class, 'updatePassword'])
                    ->name('profile.password.update');
                Route::post('/profile/password/verify', [MobileProfileController::class, 'verifyPassword'])
                    ->name('profile.password.verify');
                Route::post('/profile/avatar', [MobileProfileController::class, 'updateAvatar'])
                    ->name('profile.avatar.update');
                Route::delete('/profile/avatar', [MobileProfileController::class, 'removeAvatar'])
                    ->name('profile.avatar.destroy');
                Route::get('/notifications', [MobileNotificationController::class, 'index'])
                    ->name('notifications.index');
                Route::post('/notifications/read-all', [MobileNotificationController::class, 'readAll'])
                    ->name('notifications.read-all');
                Route::post('/notifications/{notification}/read', [MobileNotificationController::class, 'read'])
                    ->name('notifications.read');
            });

        Route::middleware(['auth:sanctum', 'abilities:mobile:customer', 'verified', 'customer.role'])
            ->prefix('customer')
            ->name('customer.')
            ->group(function (): void {
                Route::get('/status', MobileApiStatusController::class)->name('status');
            });
    });
