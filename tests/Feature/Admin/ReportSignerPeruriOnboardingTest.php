<?php

use App\Models\ReportSigner;
use App\Models\User;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    AdminWorkspaceAccessSynchronizer::sync();

    config([
        'peruri.base_url' => 'https://peruri.test',
        'peruri.api_version' => 'v1',
        'peruri.corporate_id' => 'CORP-TEST',
        'peruri.client_id' => 'client-id',
        'peruri.client_secret' => 'client-secret',
        'peruri.uploader_email' => 'uploader@digipro.test',
        'peruri.extra_headers' => [],
    ]);
});

it('allows admin to register a report signer to peruri user registry', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $signer = ReportSigner::query()->create([
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'phone_number' => '081234567890',
        'is_active' => true,
    ]);

    $capturedPayload = null;
    $expiredDate = now()->endOfDay()->toIso8601String();

    Http::fake(function (Request $request) use (&$capturedPayload, $expiredDate) {
        if (str_contains($request->url(), '/auth/v1/token/generate')) {
            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => [
                    'accessToken' => 'access-token-test',
                    'expiredDate' => $expiredDate,
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/registration/v1/CORP-TEST/user')) {
            $capturedPayload = $request->data();

            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => ['registered' => true],
            ], 200);
        }

        return Http::response(['status' => '00', 'message' => 'OK', 'data' => []], 200);
    });

    $this->actingAs($admin)
        ->post(route('admin.master-data.report-signers.peruri.register-user', $signer), [
            'payload_json' => json_encode([
                'nik' => '3173000000000001',
                'email' => $signer->email,
            ]),
        ])
        ->assertRedirect(route('admin.master-data.report-signers.edit', $signer))
        ->assertSessionHas('success', 'Registrasi user Peruri berhasil dikirim.');

    expect($capturedPayload)->toMatchArray([
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'phone' => '081234567890',
        'nik' => '3173000000000001',
    ]);
});

it('sends signature specimen to peruri in base64 format through the adapter', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $signer = ReportSigner::query()->create([
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    $capturedPayload = null;
    $expiredDate = now()->endOfDay()->toIso8601String();
    $pngBinary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0uoAAAAASUVORK5CYII=', true);

    Http::fake(function (Request $request) use (&$capturedPayload, $expiredDate) {
        if (str_contains($request->url(), '/auth/v1/token/generate')) {
            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => [
                    'accessToken' => 'access-token-test',
                    'expiredDate' => $expiredDate,
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/specimen/v1/CORP-TEST/set/signature')) {
            $capturedPayload = $request->data();

            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => ['stored' => true],
            ], 200);
        }

        return Http::response(['status' => '00', 'message' => 'OK', 'data' => []], 200);
    });

    $file = UploadedFile::fake()->createWithContent('signature.png', $pngBinary ?: '');

    $this->actingAs($admin)
        ->post(route('admin.master-data.report-signers.peruri.set-specimen', $signer), [
            'signature_image' => $file,
            'payload_json' => json_encode(['note' => 'specimen-test']),
        ])
        ->assertRedirect(route('admin.master-data.report-signers.edit', $signer))
        ->assertSessionHas('success', 'Specimen tanda tangan berhasil dikirim ke Peruri.');

    expect($capturedPayload)->not->toBeNull();
    expect($capturedPayload['email'] ?? null)->toBe('public@appraiser.test');
    expect($capturedPayload['note'] ?? null)->toBe('specimen-test');
    expect(base64_decode((string) ($capturedPayload['base64Image'] ?? ''), true))->toBe($pngBinary);
});

it('stores keyla qr image in session after registration', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $signer = ReportSigner::query()->create([
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    $expiredDate = now()->endOfDay()->toIso8601String();

    Http::fake(function (Request $request) use ($expiredDate) {
        if (str_contains($request->url(), '/auth/v1/token/generate')) {
            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => [
                    'accessToken' => 'access-token-test',
                    'expiredDate' => $expiredDate,
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/keyla/v1/CORP-TEST/sign/register')) {
            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => ['qrImage' => 'data:image/png;base64,AAAABBBB'],
            ], 200);
        }

        return Http::response(['status' => '00', 'message' => 'OK', 'data' => []], 200);
    });

    $this->actingAs($admin)
        ->post(route('admin.master-data.report-signers.peruri.register-keyla', $signer))
        ->assertRedirect(route('admin.master-data.report-signers.edit', $signer))
        ->assertSessionHas('peruri_onboarding', [
            'action' => 'register_keyla',
            'email' => 'public@appraiser.test',
            'qr_image' => 'data:image/png;base64,AAAABBBB',
        ]);
});
