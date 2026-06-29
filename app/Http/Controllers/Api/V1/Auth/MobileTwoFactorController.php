<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\MobileTwoFactorVerifyRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\MobileAuthService;
use App\Services\Auth\MobileTwoFactorChallengeService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MobileTwoFactorController extends Controller
{
    public function __invoke(
        MobileTwoFactorVerifyRequest $request,
        MobileTwoFactorChallengeService $challengeService,
        MobileAuthService $authService,
    ): JsonResponse {
        $data = $request->validated();
        $result = $challengeService->verify(
            $data['challenge_token'],
            $data['code'] ?? null,
            $data['recovery_code'] ?? null,
        );

        if ($result['status'] === 'invalid_challenge') {
            return response()->json([
                'message' => 'Challenge autentikasi tidak valid atau sudah kedaluwarsa.',
                'code' => 'invalid_two_factor_challenge',
                'errors' => [
                    'challenge_token' => ['Ulangi login untuk mendapatkan challenge baru.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($result['status'] === 'invalid_code') {
            $field = filled($data['recovery_code'] ?? null) ? 'recovery_code' : 'code';

            return response()->json([
                'message' => 'Kode autentikasi tidak valid.',
                'code' => 'invalid_two_factor_code',
                'errors' => [
                    $field => ['Kode autentikasi tidak valid.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $result['user'];

        if (! $user->hasRole('customer')) {
            return response()->json([
                'message' => 'Akun ini tidak memiliki akses ke aplikasi customer.',
                'code' => 'customer_access_required',
            ], Response::HTTP_FORBIDDEN);
        }

        $token = $authService->issueAccessToken($user, $result['device_name']);

        return response()->json([
            'data' => [
                'access_token' => $token['token']->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token['expires_at']->toIso8601String(),
                'user' => UserResource::make($user),
            ],
            'message' => 'Verifikasi dua faktor berhasil.',
        ]);
    }
}
