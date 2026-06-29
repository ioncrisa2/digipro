<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class MobileTwoFactorChallengeService
{
    public function __construct(
        private readonly Repository $cache,
        private readonly TwoFactorAuthenticationProvider $provider,
    ) {}

    /**
     * @return array{challenge_token: string, expires_in: int}
     */
    public function issue(User $user, string $deviceName): array
    {
        $token = Str::random(64);
        $ttlSeconds = (int) config('mobile-api.two_factor.challenge_ttl_minutes', 5) * 60;

        $this->cache->put($this->cacheKey($token), [
            'user_id' => $user->getKey(),
            'device_name' => $deviceName,
            'attempts' => 0,
            'expires_at' => now()->addSeconds($ttlSeconds)->timestamp,
        ], $ttlSeconds);

        return [
            'challenge_token' => $token,
            'expires_in' => $ttlSeconds,
        ];
    }

    /**
     * @return array{status: 'verified', user: User, device_name: string}|array{status: 'invalid_challenge'|'invalid_code'}
     */
    public function verify(string $token, ?string $code, ?string $recoveryCode): array
    {
        $cacheKey = $this->cacheKey($token);
        $challenge = $this->cache->pull($cacheKey);

        if (! is_array($challenge) || ! isset($challenge['user_id'], $challenge['expires_at'])) {
            return ['status' => 'invalid_challenge'];
        }

        if ((int) $challenge['expires_at'] <= now()->timestamp) {
            return ['status' => 'invalid_challenge'];
        }

        $user = User::query()->find($challenge['user_id']);

        if (! $user || ! $user->hasEnabledTwoFactorAuthentication()) {
            return ['status' => 'invalid_challenge'];
        }

        if (! $this->hasValidCode($user, $code, $recoveryCode)) {
            return $this->restoreFailedChallenge($cacheKey, $challenge);
        }

        return [
            'status' => 'verified',
            'user' => $user,
            'device_name' => (string) ($challenge['device_name'] ?? 'mobile'),
        ];
    }

    private function hasValidCode(User $user, ?string $code, ?string $recoveryCode): bool
    {
        if (filled($recoveryCode)) {
            $matchedCode = collect($user->recoveryCodes())->first(
                fn (mixed $storedCode): bool => hash_equals((string) $storedCode, (string) $recoveryCode),
            );

            if (! is_string($matchedCode)) {
                return false;
            }

            $user->replaceRecoveryCode($matchedCode);

            return true;
        }

        if (! filled($code)) {
            return false;
        }

        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        return $this->provider->verify($secret, (string) $code);
    }

    /**
     * @param  array<string, mixed>  $challenge
     * @return array{status: 'invalid_challenge'|'invalid_code'}
     */
    private function restoreFailedChallenge(string $cacheKey, array $challenge): array
    {
        $challenge['attempts'] = (int) ($challenge['attempts'] ?? 0) + 1;
        $maxAttempts = (int) config('mobile-api.two_factor.max_attempts', 5);
        $remainingSeconds = (int) $challenge['expires_at'] - now()->timestamp;

        if ($challenge['attempts'] >= $maxAttempts || $remainingSeconds <= 0) {
            return ['status' => 'invalid_challenge'];
        }

        $this->cache->put($cacheKey, $challenge, $remainingSeconds);

        return ['status' => 'invalid_code'];
    }

    private function cacheKey(string $token): string
    {
        return 'mobile-auth:two-factor:'.hash('sha256', $token);
    }
}
