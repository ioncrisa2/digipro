<?php

namespace App\Services\Peruri;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PeruriClient
{
    public function __construct(
        private readonly PeruriTokenService $tokenService,
        private readonly PeruriErrorMapper $errorMapper,
    ) {
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query, null);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public function post(string $path, array $json = []): array
    {
        return $this->request('POST', $path, [], $json);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public function put(string $path, array $json = []): array
    {
        return $this->request('PUT', $path, [], $json);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $query = [], ?array $json = null): array
    {
        $baseUrl = rtrim((string) config('peruri.base_url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('Konfigurasi Peruri base url belum diisi.');
        }

        $url = $baseUrl . '/' . ltrim($path, '/');

        $response = $this->http()
            ->withToken($this->tokenService->accessToken())
            ->send($method, $url, array_filter([
                'query' => $query,
                'json' => $json,
            ], fn ($value) => $value !== null));

        if (! $response->ok()) {
            Log::warning('Peruri HTTP request failed.', [
                'method' => $method,
                'path' => $path,
                'status_code' => $response->status(),
            ]);

            throw new RuntimeException('Gagal menghubungi layanan Peruri. Silakan coba lagi.');
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException('Response Peruri tidak valid.');
        }

        $status = (string) ($body['status'] ?? '');
        if ($status !== '00') {
            $message = $this->errorMapper->messageForStatus($status, (string) ($body['message'] ?? null));
            throw new RuntimeException($message);
        }

        return $body;
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders(array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $this->extraHeaders()))
            ->timeout(30)
            ->retry(2, 250, throw: false);
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

