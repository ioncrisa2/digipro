<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\MobileVerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MobileEmailVerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::query()->find($id);

        if (! $user?->hasRole('customer')) {
            return response()->json([
                'message' => 'Tautan verifikasi tidak valid.',
                'code' => 'invalid_verification_link',
            ], Response::HTTP_NOT_FOUND);
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json([
                'message' => 'Tautan verifikasi tidak valid.',
                'code' => 'invalid_verification_link',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'data' => ['email_verified' => true],
            'message' => 'Email berhasil diverifikasi.',
        ]);
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'data' => ['email_verified' => true],
                'message' => 'Email sudah terverifikasi.',
            ]);
        }

        $user->notify(new MobileVerifyEmailNotification);

        return response()->json([
            'data' => ['email_verified' => false],
            'message' => 'Tautan verifikasi telah dikirim.',
        ]);
    }
}
