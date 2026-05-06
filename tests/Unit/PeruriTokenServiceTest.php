<?php

use App\Services\Peruri\PeruriTokenService;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    AdminWorkspaceAccessSynchronizer::sync();

    config([
        'peruri.base_url' => 'https://peruri.test',
        'peruri.api_version' => 'v1',
        'peruri.client_id' => 'client-id',
        'peruri.client_secret' => 'client-secret',
        'peruri.extra_headers' => [],
    ]);
});

it('caches peruri token for the day', function () {
    $expiredDate = now()->endOfDay()->toIso8601String();

    Http::fakeSequence()
        ->push([
            'status' => '00',
            'message' => 'OK',
            'data' => [
                'accessToken' => 'access-token-test',
                'expiredDate' => $expiredDate,
            ],
        ], 200);

    /** @var PeruriTokenService $service */
    $service = app(PeruriTokenService::class);

    $token1 = $service->accessToken();
    $token2 = $service->accessToken();

    expect($token1)->toBe('access-token-test');
    expect($token2)->toBe('access-token-test');

    Http::assertSentCount(1);
});
