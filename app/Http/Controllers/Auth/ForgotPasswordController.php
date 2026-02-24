<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Shows the forgot-password form and sends reset links.
 */
class ForgotPasswordController extends Controller
{
    public function showForgotForm()
    {
        return inertia('Auth/ForgotPasswordPage');
    }

    public function sendResetLink(ForgotPasswordRequest $request)
    {
        $request->validated();

        $status = Password::sendResetLink(
            $request->only('email')
        );

        // dd($status);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

}
