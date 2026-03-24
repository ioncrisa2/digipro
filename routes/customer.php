<?php

use App\Http\Controllers\AppraisalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'customer.role'])
    ->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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

        Route::get('/laporan-penilaian', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan-penilaian/{id}', [ReportController::class, 'show'])->name('reports.show');

        Route::get('/pembayaran', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/pembayaran/{id}', [PaymentController::class, 'show'])->name('payments.show');
    });
