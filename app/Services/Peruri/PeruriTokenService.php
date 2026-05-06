<?php

namespace App\Services\Peruri;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PeruriTokenService
{
    private const CACHE_KEY = 'peruri:signit:access_token';
    private const LOCK_KEY = 'peruri:signit:access_token:lock';

    public function __construct(
        private readonly PeruriErrorMapper $errorMapper,
    ) {
    }

    public function accessToken(): string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && isset($cached['token'], $cached['expires_at'])) {
            $expiresAt = CarbonImmutable::parse((string) $cached['expires_at']);
            if (now()->lt($expiresAt->subMinutes(2))) {
                return (string) $cached['token'];
            }
        }

        $lock = Cache::lock(self::LOCK_KEY, 15);

        return (string) $lock->block(5, function (): string {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached) && isset($cached['token'], $cached['expires_at'])) {
                $expiresAt = CarbonImmutable::parse((string) $cached['expires_at']);
                if (now()->lt($expiresAt->subMinutes(2))) {
                    return (string) $cached['token'];
                }
            }

            return $this->generateAndCacheToken();
        });
    }

    private function generateAndCacheToken(): string
    {
        $baseUrl = rtrim((string) config('peruri.base_url'), '/');
        $version = (string) config('peruri.api_version', 'v1');
        $clientId = (string) config('peruri.client_id');
        $clientSecret = (string) config('peruri.client_secret');

        if ($baseUrl === '' || $clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Konfigurasi Peruri belum lengkap (base url / client id / client secret).');
        }

        $url = "{$baseUrl}/auth/{$version}/token/generate";
        $basic = base64_encode($clientId . ':' . $clientSecret);

        $response = Http::withHeaders(array_merge([
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . $basic,
        ], $this->extraHeaders()))
            ->timeout(20)
            ->get($url);

        if (! $response->ok()) {
            $json = $response->json();

            if (is_array($json)) {
                $status = (string) ($json['status'] ?? '');
                $message = $this->errorMapper->messageForStatus($status, (string) ($json['message'] ?? null));

                throw new RuntimeException("Peruri: {$message}");
            }

            throw new RuntimeException('Gagal menghubungi Peruri (generate token).');
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Response Peruri tidak valid (generate token).');
        }

        $status = (string) ($json['status'] ?? '');
        if ($status !== '00') {
            $message = $this->errorMapper->messageForStatus($status, (string) ($json['message'] ?? null));
            throw new RuntimeException("Peruri: {$message}");
        }

        $token = (string) data_get($json, 'data.accessToken', '');
        $expiredDate = (string) data_get($json, 'data.expiredDate', '');

        if ($token === '' || $expiredDate === '') {
            throw new RuntimeException('Response Peruri tidak lengkap (accessToken/expiredDate).');
        }

        $expiresAt = CarbonImmutable::parse($expiredDate);
        Cache::put(self::CACHE_KEY, [
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'generated_at' => now()->toIso8601String(),
            'token_hint' => Str::mask($token, '*', 6),
        ], $expiresAt);

        return $token;
    }

    /**
     * @return array<string, string>
     */
    private function extraHeaders(): array
    {
        $headers = config('peruri.extra_headers', []);

        return is_array($headers)
            ? array_filter($headers, fn ($value, $key) => is_string($key) && is_string($value), ARRAY_FILTER_USE_BOTH)
            : [];
    }
}
