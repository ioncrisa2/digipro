<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Handles email verification notice, verify, and resend actions.
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): Response
    {
        return Inertia::render('Auth/VerifyEmail');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('verification.success')->with('status', 'verified');
    }

    public function success(Request $request): Response
    {
        return Inertia::render('Auth/VerifySuccess', [
            'redirectTo' => route('dashboard'),
            'countdownSeconds' => 5,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
