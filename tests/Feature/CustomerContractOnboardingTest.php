<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\Province;
use App\Models\Regency;
use App\Models\ReportSigner;
use App\Models\SignatureEnvelope;
use App\Models\User;
use App\Models\UserSignatureProfile;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

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
        'peruri.coordinates.contract.customer' => [
            'page' => 1,
            'lower_left_x' => 360,
            'lower_left_y' => 120,
            'upper_right_x' => 540,
            'upper_right_y' => 200,
        ],
        'peruri.coordinates.contract.public_appraiser' => [
            'page' => 1,
            'lower_left_x' => 40,
            'lower_left_y' => 120,
            'upper_right_x' => 220,
            'upper_right_y' => 200,
        ],
    ]);
});

it('redirects waiting-signature customer to onboarding when pds readiness is not ready', function (): void {
    $customer = User::factory()->create();
    $request = createWaitingSignatureAppraisalRequest($customer);

    fakePublicSignerReadiness();

    $this->actingAs($customer)
        ->get(route('appraisal.contract.page', ['id' => $request->id]))
        ->assertRedirect(route('appraisal.contract.onboarding.page', ['id' => $request->id]));
});

it('stores customer onboarding identity on signature profile and request snapshot', function (): void {
    Storage::fake('local');

    $customer = User::factory()->create([
        'email' => 'portal@example.test',
        'phone_number' => '081200000001',
        'billing_nik' => '3173000000000001',
    ]);
    $request = createWaitingSignatureAppraisalRequest($customer);

    $this->actingAs($customer)
        ->post(route('appraisal.contract.onboarding.identity', ['id' => $request->id]), [
            'peruri_email' => 'signature@example.test',
            'peruri_phone' => '081255566677',
            'nik' => '3173000000000002',
            'is_wna' => false,
            'reference_province_id' => 31,
            'reference_city_id' => 3171,
            'gender' => 'M',
            'place_of_birth' => 'Jakarta',
            'date_of_birth' => '1990-01-15',
            'address' => 'Jakarta Selatan',
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ])
        ->assertRedirect(route('appraisal.contract.onboarding.page', ['id' => $request->id]));

    $profile = $customer->fresh()->signatureProfile;

    expect($profile)->not()->toBeNull();
    expect($profile->peruri_email)->toBe('signature@example.test');
    expect($profile->peruri_phone)->toBe('081255566677');
    expect($profile->nik)->toBe('3173000000000002');
    expect($profile->is_wna)->toBeFalse();
    expect($profile->reference_province_id)->toBe(31);
    expect($profile->reference_city_id)->toBe(3171);
    expect($profile->gender)->toBe('M');
    expect($profile->place_of_birth)->toBe('Jakarta');
    expect($profile->date_of_birth?->toDateString())->toBe('1990-01-15');
    expect($profile->ktp_photo_path)->not->toBeNull();
    expect(data_get($profile->identity_payload, 'address'))->toBe('Jakarta Selatan');
    Storage::disk('local')->assertExists((string) $profile->ktp_photo_path);

    $snapshot = $request->fresh()->contract_signer_snapshot;
    expect(data_get($snapshot, 'customer.profile_id'))->toBe($profile->id);
    expect(data_get($snapshot, 'customer.email'))->toBe('signature@example.test');
});

it('does not probe peruri readiness when customer only saves onboarding identity', function (): void {
    Storage::fake('local');

    $customer = User::factory()->create([
        'email' => 'portal@example.test',
        'phone_number' => '081200000001',
        'billing_nik' => '3173000000000001',
    ]);
    $request = createWaitingSignatureAppraisalRequest($customer);

    Http::fake(function (ClientRequest $request) {
        if (str_contains($request->url(), '/auth/v1/token/generate')
            || str_contains($request->url(), '/certificate/v1/CORP-TEST/check')
            || str_contains($request->url(), '/Keyla/v1/CORP-TEST/sign/check')) {
            throw new RuntimeException('Peruri readiness should not be probed while saving identity.');
        }

        return Http::response(['status' => '00', 'message' => 'OK', 'data' => []], 200);
    });

    $this->actingAs($customer)
        ->post(route('appraisal.contract.onboarding.identity', ['id' => $request->id]), [
            'peruri_email' => 'signature@example.test',
            'peruri_phone' => '081255566677',
            'nik' => '3173000000000002',
            'is_wna' => false,
            'reference_province_id' => 31,
            'reference_city_id' => 3171,
            'gender' => 'F',
            'place_of_birth' => 'Bandung',
            'date_of_birth' => '1992-02-20',
            'address' => 'Jakarta Selatan',
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ])
        ->assertRedirect(route('appraisal.contract.onboarding.page', ['id' => $request->id]));

    $profile = $customer->fresh()->signatureProfile;
    expect($profile)->not->toBeNull();
    expect($profile->certificate_status)->toBeNull();
    expect($profile->keyla_status)->toBeNull();
    expect($profile->last_checked_at)->toBeNull();
});

it('rejects invalid nik when saving customer onboarding identity', function (): void {
    $customer = User::factory()->create();
    $request = createWaitingSignatureAppraisalRequest($customer);

    $this->from(route('appraisal.contract.onboarding.page', ['id' => $request->id]))
        ->actingAs($customer)
        ->post(route('appraisal.contract.onboarding.identity', ['id' => $request->id]), [
            'peruri_email' => 'signature@example.test',
            'peruri_phone' => '081255566677',
            'nik' => 'NIK-TIDAK-VALID',
            'is_wna' => false,
            'reference_province_id' => 31,
            'reference_city_id' => 3171,
            'gender' => 'M',
            'place_of_birth' => 'Jakarta',
            'date_of_birth' => '1990-01-15',
            'address' => 'Jakarta Selatan',
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ])
        ->assertRedirect(route('appraisal.contract.onboarding.page', ['id' => $request->id]))
        ->assertSessionHasErrors(['nik']);

    expect($customer->fresh()->signatureProfile)->toBeNull();
});

it('requires ktp photo when saving customer onboarding identity for the first time', function (): void {
    $customer = User::factory()->create();
    $request = createWaitingSignatureAppraisalRequest($customer);

    $this->from(route('appraisal.contract.onboarding.page', ['id' => $request->id]))
        ->actingAs($customer)
        ->post(route('appraisal.contract.onboarding.identity', ['id' => $request->id]), [
            'peruri_email' => 'signature@example.test',
            'peruri_phone' => '081255566677',
            'nik' => '3173000000000002',
            'is_wna' => false,
            'reference_province_id' => 31,
            'reference_city_id' => 3171,
            'gender' => 'M',
            'place_of_birth' => 'Jakarta',
            'date_of_birth' => '1990-01-15',
            'address' => 'Jakarta Selatan',
        ])
        ->assertRedirect(route('appraisal.contract.onboarding.page', ['id' => $request->id]))
        ->assertSessionHasErrors(['ktp_photo']);
});

it('sends structured register-user payload for customer onboarding', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('signature-profiles/1/identity/ktp.jpg', 'ktp-binary');

    $customer = User::factory()->create([
        'name' => 'Customer Test',
    ]);
    $request = createWaitingSignatureAppraisalRequest($customer);
    UserSignatureProfile::query()->create([
        'user_id' => $customer->id,
        'provider' => 'peruri_signit',
        'peruri_email' => 'customer-sign@example.test',
        'peruri_phone' => '081211122233',
        'nik' => '3173000000000010',
        'is_wna' => false,
        'reference_province_id' => 31,
        'reference_city_id' => 3171,
        'gender' => 'M',
        'place_of_birth' => 'Jakarta',
        'date_of_birth' => '1990-01-15',
        'ktp_photo_path' => 'signature-profiles/1/identity/ktp.jpg',
        'identity_payload' => [
            'address' => 'Jl. Testing',
        ],
    ]);

    $capturedPayload = null;
    fakePeruri(function (ClientRequest $request) use (&$capturedPayload) {
        if (str_contains($request->url(), '/registration/v1/CORP-TEST/user')) {
            $capturedPayload = $request->data();
        }
    });

    $this->actingAs($customer)
        ->post(route('appraisal.contract.onboarding.register-user', ['id' => $request->id]))
        ->assertRedirect(route('appraisal.contract.onboarding.page', ['id' => $request->id]));

    expect($capturedPayload)->toMatchArray([
        'isWNA' => false,
        'name' => 'Customer Test',
        'email' => 'customer-sign@example.test',
        'phone' => '081211122233',
        'type' => 'INDIVIDUAL',
        'ktp' => '3173000000000010',
        'province' => '31',
        'city' => '3171',
        'gender' => 'M',
        'placeOfBirth' => 'Jakarta',
        'dateOfBirth' => '15/01/1990',
        'address' => 'Jl. Testing',
    ]);
    expect(base64_decode((string) ($capturedPayload['ktpPhoto'] ?? ''), true))->toBe('ktp-binary');
    expect((string) ($capturedPayload['ktpPhoto'] ?? ''))->not->toContain('data:image');
});

it('uploads kyc and specimen as base64 and stores keyla qr image', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Storage::disk('local')->put('signature-profiles/1/identity/ktp.jpg', 'ktp-binary');

    $customer = User::factory()->create();
    $request = createWaitingSignatureAppraisalRequest($customer);
    UserSignatureProfile::query()->create([
        'user_id' => $customer->id,
        'provider' => 'peruri_signit',
        'peruri_email' => 'customer-sign@example.test',
        'peruri_phone' => '081211122233',
        'nik' => '3173000000000010',
        'is_wna' => false,
        'reference_province_id' => 31,
        'reference_city_id' => 3171,
        'gender' => 'M',
        'place_of_birth' => 'Jakarta',
        'date_of_birth' => '1990-01-15',
        'ktp_photo_path' => 'signature-profiles/1/identity/ktp.jpg',
        'identity_payload' => [
            'address' => 'Jl. Testing',
        ],
    ]);

    $capturedKyc = null;
    $capturedSpecimen = null;
    fakePeruri(function (ClientRequest $request) use (&$capturedKyc, &$capturedSpecimen) {
        if (str_contains($request->url(), '/registration/v1/CORP-TEST/kyc')) {
            $capturedKyc = $request->data();
        }

        if (str_contains($request->url(), '/specimen/v1/CORP-TEST/set/signature')) {
            $capturedSpecimen = $request->data();
        }
    });

    $kycBinary = 'video-binary';
    $specimenBinary = 'image-binary';

    $this->actingAs($customer)
        ->post(route('appraisal.contract.onboarding.submit-kyc', ['id' => $request->id]), [
            'kyc_video' => UploadedFile::fake()->createWithContent('kyc.mp4', $kycBinary),
        ])
        ->assertRedirect(route('appraisal.contract.onboarding.page', ['id' => $request->id]));

    $this->actingAs($customer)
        ->post(route('appraisal.contract.onboarding.set-specimen', ['id' => $request->id]), [
            'signature_image' => UploadedFile::fake()->createWithContent('signature.png', $specimenBinary),
        ])
        ->assertRedirect(route('appraisal.contract.onboarding.page', ['id' => $request->id]));

    $this->actingAs($customer)
        ->post(route('appraisal.contract.onboarding.register-keyla', ['id' => $request->id]))
        ->assertRedirect(route('appraisal.contract.onboarding.page', ['id' => $request->id]));

    expect(base64_decode((string) ($capturedKyc['videoStream'] ?? ''), true))->toBe($kycBinary);
    expect(base64_decode((string) ($capturedSpecimen['specimen'] ?? ''), true))->toBe($specimenBinary);
    expect($customer->fresh()->signatureProfile?->keyla_qr_image)->toBe('data:image/png;base64,AAAABBBB');
});

it('refreshes readiness and reuses customer profile across requests', function (): void {
    $customer = User::factory()->create();
    $firstRequest = createWaitingSignatureAppraisalRequest($customer);
    $secondRequest = createWaitingSignatureAppraisalRequest($customer);

    UserSignatureProfile::query()->create([
        'user_id' => $customer->id,
        'provider' => 'peruri_signit',
        'peruri_email' => 'customer-sign@example.test',
        'peruri_phone' => '081211122233',
        'nik' => '3173000000000010',
        'reference_province_id' => 31,
        'reference_city_id' => 3171,
        'registration_status' => 'submitted',
        'kyc_status' => 'submitted',
        'specimen_status' => 'submitted',
        'identity_payload' => ['address' => 'Jl. Testing'],
    ]);

    fakePeruriForContractPage();

    $this->actingAs($customer)
        ->post(route('appraisal.contract.onboarding.refresh', ['id' => $firstRequest->id]))
        ->assertRedirect(route('appraisal.contract.onboarding.page', ['id' => $firstRequest->id]));

    fakePeruriForContractPage();

    $this->actingAs($customer)
        ->get(route('appraisal.contract.page', ['id' => $secondRequest->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Penilaian/ContractSign')
            ->where('signingReadiness.customer.overall.is_ready', true));
});

it('surfaces specific peruri token error during readiness refresh', function (): void {
    $customer = User::factory()->create();
    $request = createWaitingSignatureAppraisalRequest($customer);

    UserSignatureProfile::query()->create([
        'user_id' => $customer->id,
        'provider' => 'peruri_signit',
        'peruri_email' => 'customer-sign@example.test',
        'peruri_phone' => '081211122233',
        'nik' => '3173000000000010',
        'reference_province_id' => 31,
        'reference_city_id' => 3171,
        'registration_status' => 'submitted',
        'kyc_status' => 'submitted',
        'specimen_status' => 'submitted',
        'identity_payload' => ['address' => 'Jl. Testing'],
    ]);

    Http::fake(function (ClientRequest $request) {
        if (str_contains($request->url(), '/auth/v1/token/generate')) {
            return Http::response([
                'status' => '84',
                'message' => 'Client is inactive',
            ], 401);
        }

        return Http::response(['status' => '00', 'message' => 'OK', 'data' => []], 200);
    });

    $this->from(route('appraisal.contract.onboarding.page', ['id' => $request->id]))
        ->actingAs($customer)
        ->post(route('appraisal.contract.onboarding.refresh', ['id' => $request->id]))
        ->assertRedirect(route('appraisal.contract.onboarding.page', ['id' => $request->id]))
        ->assertSessionHas('error', 'Peruri: Client Peruri tidak aktif.');
});

it('uses customer peruri email from signature profile when signing contract', function (): void {
    Storage::fake('public');

    $customer = User::factory()->create([
        'email' => 'portal@example.test',
    ]);
    $request = createWaitingSignatureAppraisalRequest($customer);

    UserSignatureProfile::query()->create([
        'user_id' => $customer->id,
        'provider' => 'peruri_signit',
        'peruri_email' => 'signature@example.test',
        'peruri_phone' => '081211122233',
        'nik' => '3173000000000010',
        'reference_province_id' => 31,
        'reference_city_id' => 3171,
        'registration_status' => 'submitted',
        'kyc_status' => 'submitted',
        'specimen_status' => 'submitted',
        'identity_payload' => ['address' => 'Jl. Testing'],
    ]);

    $sendPayload = null;

    Http::fake(function (ClientRequest $request) use (&$sendPayload) {
        if (str_contains($request->url(), '/auth/v1/token/generate')) {
            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => [
                    'accessToken' => 'access-token-test',
                    'expiredDate' => now()->endOfDay()->toIso8601String(),
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/certificate/v1/CORP-TEST/check')) {
            $email = data_get($request->data(), 'email');
            expect(in_array($email, ['signature@example.test', 'public@appraiser.test'], true))->toBeTrue();

            return Http::response(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200);
        }

        if (str_contains($request->url(), '/Keyla/v1/CORP-TEST/sign/check')) {
            $email = data_get($request->data(), 'email');
            expect(in_array($email, ['signature@example.test', 'public@appraiser.test'], true))->toBeTrue();

            return Http::response(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200);
        }

        if (str_contains($request->url(), '/keyla/v1/CORP-TEST/sign/verify')) {
            expect(data_get($request->data(), 'email'))->toBe('signature@example.test');

            return Http::response(['status' => '00', 'message' => 'OK', 'data' => ['verified' => true]], 200);
        }

        if (str_contains($request->url(), '/sign/v1/CORP-TEST/model/tier/send')) {
            $sendPayload = $request->data();

            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => ['orderIdTier' => 'TIER-123', 'orderId' => 'ORDER-CUST-1'],
            ], 200);
        }

        if (str_contains($request->url(), '/specimen/v1/CORP-TEST/coordinate/signature')) {
            return Http::response(['status' => '00', 'message' => 'OK', 'data' => []], 200);
        }

        if (str_contains($request->url(), '/sign/v1/CORP-TEST/model/tier/signing')) {
            return Http::response(['status' => '00', 'message' => 'OK', 'data' => []], 200);
        }

        return Http::response(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200);
    });

    $this->actingAs($customer)
        ->post(route('appraisal.contract.sign', ['id' => $request->id]), [
            'agree_contract' => true,
            'keyla_token' => 'KEYLA123',
        ])
        ->assertRedirect(route('appraisal.payment.page', ['id' => $request->id]));

    expect(data_get($sendPayload, 'signer.0.email'))->toBe('signature@example.test');
    expect(SignatureEnvelope::query()->where('subject_id', $request->id)->first()?->status)->toBe('awaiting_internal');
});

it('loads province and city references from nested pds payloads on onboarding page', function (): void {
    $customer = User::factory()->create();
    $request = createWaitingSignatureAppraisalRequest($customer);

    Http::fake(function (ClientRequest $request) {
        if (str_contains($request->url(), '/auth/v1/token/generate')) {
            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => [
                    'accessToken' => 'access-token-test',
                    'expiredDate' => now()->endOfDay()->toIso8601String(),
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/registration/v1/CORP-TEST/reference/province')) {
            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => [
                    'province' => [
                        ['idProvince' => 31, 'provinceName' => 'DKI Jakarta'],
                    ],
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/registration/v1/CORP-TEST/reference/city')) {
            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => [
                    'city' => [
                        ['idCity' => 3171, 'cityName' => 'Jakarta Selatan'],
                    ],
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/certificate/v1/CORP-TEST/check')) {
            return Http::response(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200);
        }

        if (str_contains($request->url(), '/Keyla/v1/CORP-TEST/sign/check')) {
            return Http::response(['status' => '00', 'message' => 'OK', 'data' => ['registered' => false, 'active' => false]], 200);
        }

        return Http::response(['status' => '00', 'message' => 'OK', 'data' => []], 200);
    });

    $this->actingAs($customer)
        ->get(route('appraisal.contract.onboarding.page', ['id' => $request->id, 'province_id' => 31]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Penilaian/ContractOnboarding')
            ->where('references.provinces.0.value', '31')
            ->where('references.provinces.0.label', 'DKI Jakarta')
            ->where('references.cities.0.value', '3171')
            ->where('references.cities.0.label', 'Jakarta Selatan'));
});

it('falls back to internal province and city master data when pds references fail', function (): void {
    Province::query()->create(['id' => '31', 'name' => 'DKI Jakarta']);
    Regency::query()->create(['id' => '3171', 'province_id' => '31', 'name' => 'Jakarta Selatan']);

    $customer = User::factory()->create();
    $request = createWaitingSignatureAppraisalRequest($customer);

    Http::fake(function (ClientRequest $request) {
        if (str_contains($request->url(), '/auth/v1/token/generate')) {
            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => [
                    'accessToken' => 'access-token-test',
                    'expiredDate' => now()->endOfDay()->toIso8601String(),
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/registration/v1/CORP-TEST/reference/province')) {
            return Http::response(['status' => '99', 'message' => 'Reference unavailable'], 500);
        }

        if (str_contains($request->url(), '/certificate/v1/CORP-TEST/check')) {
            return Http::response(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200);
        }

        if (str_contains($request->url(), '/Keyla/v1/CORP-TEST/sign/check')) {
            return Http::response(['status' => '00', 'message' => 'OK', 'data' => ['registered' => false, 'active' => false]], 200);
        }

        return Http::response(['status' => '00', 'message' => 'OK', 'data' => []], 200);
    });

    $this->actingAs($customer)
        ->get(route('appraisal.contract.onboarding.page', ['id' => $request->id, 'province_id' => 31]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Penilaian/ContractOnboarding')
            ->where('references.provinces.0.value', '31')
            ->where('references.provinces.0.label', 'DKI Jakarta')
            ->where('references.cities.0.value', '3171')
            ->where('references.cities.0.label', 'Jakarta Selatan'));
});

function createWaitingSignatureAppraisalRequest(User $customer): AppraisalRequest
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $publicSigner = ReportSigner::query()->create([
        'user_id' => $admin->id,
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    return AppraisalRequest::query()->create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-'.Str::upper(Str::random(8)),
        'purpose' => 'jual_beli',
        'status' => AppraisalStatusEnum::WaitingSignature,
        'requested_at' => now(),
        'client_name' => 'PT Test DigiPro',
        'contract_number' => '00015/AGR/DP/04/2026-'.Str::upper(Str::random(4)),
        'contract_date' => now()->toDateString(),
        'contract_status' => ContractStatusEnum::WaitingSignature,
        'fee_total' => 1400000,
        'report_format' => 'digital',
        'contract_public_appraiser_signer_id' => $publicSigner->id,
    ]);
}

function fakePublicSignerReadiness(): void
{
    Http::fakeSequence()
        ->push([
            'status' => '00',
            'message' => 'OK',
            'data' => [
                'accessToken' => 'access-token-test',
                'expiredDate' => now()->endOfDay()->toIso8601String(),
            ],
        ], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200);
}

function fakePeruri(?callable $inspect = null): void
{
    Http::fake(function (ClientRequest $request) use ($inspect) {
        if ($inspect) {
            $inspect($request);
        }

        if (str_contains($request->url(), '/auth/v1/token/generate')) {
            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => [
                    'accessToken' => 'access-token-test',
                    'expiredDate' => now()->endOfDay()->toIso8601String(),
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

        if (str_contains($request->url(), '/registration/v1/CORP-TEST/reference/province')) {
            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => [
                    ['id' => 31, 'name' => 'DKI Jakarta'],
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/registration/v1/CORP-TEST/reference/city')) {
            return Http::response([
                'status' => '00',
                'message' => 'OK',
                'data' => [
                    ['id' => 3171, 'name' => 'Jakarta Selatan'],
                ],
            ], 200);
        }

        return Http::response(['status' => '00', 'message' => 'OK', 'data' => []], 200);
    });
}

function fakePeruriForContractPage(): void
{
    Http::fakeSequence()
        ->push([
            'status' => '00',
            'message' => 'OK',
            'data' => [
                'accessToken' => 'access-token-test',
                'expiredDate' => now()->endOfDay()->toIso8601String(),
            ],
        ], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200);
}
