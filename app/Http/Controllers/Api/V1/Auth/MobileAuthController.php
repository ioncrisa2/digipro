<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\MobileLoginRequest;
use App\Http\Requests\Api\V1\Auth\MobileRegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Auth\MobileAuthService;
use App\Services\Auth\MobileTwoFactorChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class MobileAuthController extends Controller
{
    public function register(MobileRegisterRequest $request, MobileAuthService $authService): JsonResponse
    {
        $data = $request->validated();
        $user = $authService->register($data);
        $token = $authService->issueAccessToken($user, $data['device_name'] ?? null);

        return $this->tokenResponse($user, $token, 'Registrasi berhasil. Silakan verifikasi email Anda.', Response::HTTP_CREATED);
    }

    public function login(
        MobileLoginRequest $request,
        MobileAuthService $authService,
        MobileTwoFactorChallengeService $challengeService,
    ): JsonResponse {
        $data = $request->validated();
        $user = $authService->authenticate($data['email'], $data['password']);

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password tidak valid.'],
            ]);
        }

        if (! $user->hasRole('customer')) {
            return response()->json([
                'message' => 'Akun ini tidak memiliki akses ke aplikasi customer.',
                'code' => 'customer_access_required',
            ], Response::HTTP_FORBIDDEN);
        }

        $deviceName = $authService->resolveDeviceName($data['device_name'] ?? null);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $challenge = $challengeService->issue($user, $deviceName);

            return response()->json([
                'data' => [
                    'requires_two_factor' => true,
                    'challenge_token' => $challenge['challenge_token'],
                    'expires_in' => $challenge['expires_in'],
                    'email' => $user->email,
                ],
                'message' => 'Masukkan kode autentikasi.',
            ]);
        }

        return $this->tokenResponse(
            $user,
            $authService->issueAccessToken($user, $deviceName),
            'Login berhasil.',
        );
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => UserResource::make($request->user()),
            'message' => 'OK',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()?->tokens()
            ->whereJsonContains('abilities', 'mobile:customer')
            ->delete();

        return response()->json(['message' => 'Semua sesi mobile berhasil diakhiri.']);
    }

    /**
     * @param  array{token: \Laravel\Sanctum\NewAccessToken, expires_at: \Illuminate\Support\Carbon}  $token
     */
    private function tokenResponse(User $user, array $token, string $message, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'data' => [
                'access_token' => $token['token']->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token['expires_at']->toIso8601String(),
                'user' => UserResource::make($user),
            ],
            'message' => $message,
        ], $status);
    }
}
