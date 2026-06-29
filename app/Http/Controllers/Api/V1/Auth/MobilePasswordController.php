<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\MobileForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\MobileResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobilePasswordController extends Controller
{
    public function forgot(MobileForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user = User::query()->where('email', $email)->first();

        if ($user?->hasRole('customer')) {
            Password::broker()->sendResetLink(['email' => $email]);
        }

        return response()->json([
            'message' => 'Jika email terdaftar, tautan reset password akan dikirim.',
        ]);
    }

    public function reset(MobileResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user?->hasRole('customer')) {
            throw ValidationException::withMessages([
                'token' => ['Token reset password tidak valid.'],
            ]);
        }

        $status = Password::broker()->reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Password berhasil direset. Silakan login kembali.',
        ]);
    }
}
