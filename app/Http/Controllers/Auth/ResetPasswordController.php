<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Displays the reset-password form and processes password resets.
 */
class ResetPasswordController extends Controller
{
    public function showResetForm(string $token)
    {
        $email = request('email');

        return inertia('Auth/ResetPasswordPage', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $data = $request->validated();

        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()
                ->route('login', ['email' => $data['email']])
                ->with('success', 'Password berhasil direset. Silakan login.')
            : back()
                ->withErrors(['email' => __($status)])
                ->onlyInput('email');
    }
}
