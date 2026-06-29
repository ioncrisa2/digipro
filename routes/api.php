<?php

use App\Http\Controllers\Api\V1\Auth\MobileAuthController;
use App\Http\Controllers\Api\V1\Auth\MobileEmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\MobilePasswordController;
use App\Http\Controllers\Api\V1\Auth\MobileTwoFactorController;
use App\Http\Controllers\Api\V1\Customer\MobileApiStatusController;
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
            ->prefix('customer')
            ->name('customer.')
            ->group(function (): void {
                Route::get('/status', MobileApiStatusController::class)->name('status');
            });
    });
