<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserNotificationController;
use Illuminate\Support\Facades\Route;

// landing route
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/policy', [LandingController::class, 'policy'])->name('policy');
Route::get('/terms', [LandingController::class, 'terms'])->name('terms');
Route::get('/contact', [LandingController::class, 'contact'])->name('contact')->middleware('throttle:10,1');
Route::post('/contact', [LandingController::class, 'storeContact'])->name('contact.store');
Route::get('/artikel', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/artikel/{slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::redirect('/legacy-admin', '/admin');
Route::redirect('/legacy-admin/{any}', '/admin')->where('any', '.*');
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
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'resetPassword'])->name('password.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    //notification route
    Route::post('/notifications/{id}/read', [UserNotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [UserNotificationController::class, 'readAll'])->name('notifications.readAll');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/password/verify', [ProfileController::class, 'verifyCurrentPassword'])->name('profile.password.verify');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

require __DIR__ . '/admin.php';
require __DIR__ . '/customer.php';
require __DIR__ . '/reviewer.php';

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
