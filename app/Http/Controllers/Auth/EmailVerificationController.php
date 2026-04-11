<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SystemNavigation;
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
    public function notice(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->to($this->resolveVerifiedRedirect($request));
        }

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
            'redirectTo' => $this->resolveVerifiedRedirect($request),
            'countdownSeconds' => 5,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->to($this->resolveVerifiedRedirect($request));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }

    private function resolveVerifiedRedirect(Request $request): string
    {
        $user = $request->user();

        if (! $user) {
            return route('login');
        }

        if ($user->isReviewer()) {
            return route(SystemNavigation::firstAccessibleRouteName($user, 'reviewer') ?? 'reviewer.dashboard');
        }

        if ($user->hasAdminNavigationAccess()) {
            return route('admin.dashboard');
        }

        return route('dashboard');
    }
}
