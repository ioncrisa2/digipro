<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\ReportSigner;
use App\Models\SignatureEnvelope;
use App\Models\SignatureParticipant;
use App\Models\User;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

it('customer can sign contract and continue to payment while internal signature is pending', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $publicSigner = ReportSigner::query()->create([
        'user_id' => $admin->id,
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    $customer = User::factory()->create();
    $request = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-' . Str::upper(Str::random(8)),
        'purpose' => 'jual_beli',
        'status' => AppraisalStatusEnum::WaitingSignature,
        'requested_at' => now(),
        'client_name' => 'PT Test DigiPro',
        'contract_number' => '00015/AGR/DP/04/2026-' . Str::upper(Str::random(4)),
        'contract_date' => now()->toDateString(),
        'contract_status' => ContractStatusEnum::WaitingSignature,
        'fee_total' => 1400000,
        'report_format' => 'digital',
        'contract_public_appraiser_signer_id' => $publicSigner->id,
        'contract_signer_snapshot' => [
            'public_appraiser' => [
                'id' => $publicSigner->id,
                'name' => $publicSigner->name,
                'email' => $publicSigner->email,
                'user_id' => $publicSigner->user_id,
            ],
        ],
    ]);

    $expiredDate = now()->endOfDay()->toIso8601String();

    Http::fakeSequence()
        ->push([
            'status' => '00',
            'message' => 'OK',
            'data' => [
                'accessToken' => 'access-token-test',
                'expiredDate' => $expiredDate,
            ],
        ], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200) // customer cert
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200) // customer keyla check
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200) // internal cert
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200) // internal keyla check
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['verified' => true]], 200) // customer keyla verify
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['orderIdTier' => 'TIER-123', 'orderId' => 'ORDER-CUST-1']], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => []], 200) // coordinate
        ->push(['status' => '00', 'message' => 'OK', 'data' => []], 200); // signing

    $this->actingAs($customer)
        ->post(route('appraisal.contract.sign', ['id' => $request->id]), [
            'agree_contract' => true,
            'keyla_token' => 'KEYLA123',
        ])
        ->assertRedirect(route('appraisal.payment.page', ['id' => $request->id]));

    $request->refresh();
    expect($request->status->value)->toBe(AppraisalStatusEnum::ContractSigned->value);

    $envelope = SignatureEnvelope::query()
        ->where('subject_type', AppraisalRequest::class)
        ->where('subject_id', $request->id)
        ->where('provider', 'peruri_signit')
        ->where('document_type', 'contract')
        ->first();

    expect($envelope)->not()->toBeNull();
    expect($envelope->status)->toBe('awaiting_internal');
    expect($envelope->external_envelope_id)->toBe('TIER-123');

    $customerParticipant = SignatureParticipant::query()
        ->where('signature_envelope_id', $envelope->id)
        ->where('role', 'customer')
        ->first();

    $publicParticipant = SignatureParticipant::query()
        ->where('signature_envelope_id', $envelope->id)
        ->where('role', 'public_appraiser')
        ->first();

    expect($customerParticipant?->status)->toBe('signed');
    expect($publicParticipant?->status)->toBe('pending');
});

it('public appraiser can sign the pending contract and store final PDF', function () {
    Storage::fake('public');

    $actor = User::factory()->create();
    $actor->assignRole('admin');

    $publicSigner = ReportSigner::query()->create([
        'user_id' => $actor->id,
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    $customer = User::factory()->create();
    $request = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-' . Str::upper(Str::random(8)),
        'purpose' => 'jual_beli',
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
        'client_name' => 'PT Test DigiPro',
        'contract_number' => '00015/AGR/DP/04/2026-' . Str::upper(Str::random(4)),
        'contract_date' => now()->toDateString(),
        'contract_status' => ContractStatusEnum::ContractSigned,
        'fee_total' => 1400000,
        'report_format' => 'digital',
        'contract_public_appraiser_signer_id' => $publicSigner->id,
    ]);

    $envelope = SignatureEnvelope::query()->create([
        'subject_type' => AppraisalRequest::class,
        'subject_id' => $request->id,
        'document_type' => 'contract',
        'provider' => 'peruri_signit',
        'model' => 'tier',
        'external_envelope_id' => 'TIER-123',
        'uploader_email' => config('peruri.uploader_email'),
        'status' => 'awaiting_internal',
        'document_hash' => 'sha256:test',
        'meta' => [],
    ]);

    SignatureParticipant::query()->create([
        'signature_envelope_id' => $envelope->id,
        'role' => 'customer',
        'sequence' => 1,
        'email' => $customer->email,
        'name' => $customer->name,
        'external_order_id' => 'ORDER-CUST-1',
        'status' => 'signed',
        'signed_at' => now(),
        'meta' => [],
    ]);

    SignatureParticipant::query()->create([
        'signature_envelope_id' => $envelope->id,
        'role' => 'public_appraiser',
        'sequence' => 2,
        'email' => $publicSigner->email,
        'name' => $publicSigner->name,
        'external_order_id' => null,
        'status' => 'pending',
        'meta' => [],
    ]);

    $expiredDate = now()->endOfDay()->toIso8601String();
    $fakeSignedPdf = '%PDF-1.4 fake signed pdf';

    Http::fakeSequence()
        ->push([
            'status' => '00',
            'message' => 'OK',
            'data' => [
                'accessToken' => 'access-token-test',
                'expiredDate' => $expiredDate,
            ],
        ], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200) // internal cert
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200) // internal keyla check
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['verified' => true]], 200) // internal keyla verify
        ->push([
            'status' => '00',
            'message' => 'OK',
            'data' => [
                'signer' => [
                    [
                        'email' => $customer->email,
                        'orderId' => 'ORDER-CUST-1',
                        'sequence' => 1,
                    ],
                    [
                        'email' => $publicSigner->email,
                        'orderId' => 'ORDER-PA-2',
                        'sequence' => 2,
                    ],
                ],
            ],
        ], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => []], 200) // coordinate
        ->push(['status' => '00', 'message' => 'OK', 'data' => []], 200) // signing
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['base64Document' => base64_encode($fakeSignedPdf)]], 200); // download

    $this->actingAs($actor)
        ->from(route('admin.signatures.contracts.index'))
        ->post(route('admin.signatures.contracts.sign', ['appraisalRequest' => $request->id]), [
            'keyla_token' => 'KEYLA123',
        ])
        ->assertRedirect(route('admin.signatures.contracts.index'));

    $envelope->refresh();
    expect($envelope->status)->toBe('completed');
    expect($envelope->signed_pdf_path)->not()->toBeNull();

    Storage::disk('public')->assertExists($envelope->signed_pdf_path);

    $signedFile = $request->files()
        ->where('type', 'contract_signed_pdf')
        ->first();

    expect($signedFile)->not()->toBeNull();
    expect($signedFile->path)->toBe($envelope->signed_pdf_path);

    $eventLog = $request->offerNegotiations()
        ->where('action', 'contract_sign_peruri_public_appraiser')
        ->first();

    expect($eventLog)->not()->toBeNull();
});

it('shows a friendly error when keyla token is invalid', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $publicSigner = ReportSigner::query()->create([
        'user_id' => $admin->id,
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    $customer = User::factory()->create();
    $request = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-' . Str::upper(Str::random(8)),
        'purpose' => 'jual_beli',
        'status' => AppraisalStatusEnum::WaitingSignature,
        'requested_at' => now(),
        'client_name' => 'PT Test DigiPro',
        'contract_number' => '00015/AGR/DP/04/2026-' . Str::upper(Str::random(4)),
        'contract_date' => now()->toDateString(),
        'contract_status' => ContractStatusEnum::WaitingSignature,
        'fee_total' => 1400000,
        'report_format' => 'digital',
        'contract_public_appraiser_signer_id' => $publicSigner->id,
    ]);

    $expiredDate = now()->endOfDay()->toIso8601String();

    Http::fakeSequence()
        ->push([
            'status' => '00',
            'message' => 'OK',
            'data' => [
                'accessToken' => 'access-token-test',
                'expiredDate' => $expiredDate,
            ],
        ], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200) // customer cert
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200) // customer keyla check
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200) // internal cert
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200) // internal keyla check
        ->push(['status' => '55', 'message' => 'invalid keyla', 'data' => null], 200); // customer keyla verify

    $this->actingAs($customer)
        ->post(route('appraisal.contract.sign', ['id' => $request->id]), [
            'agree_contract' => true,
            'keyla_token' => 'BADTOKEN',
        ])
        ->assertRedirect(route('appraisal.contract.page', ['id' => $request->id]))
        ->assertSessionHas('error', 'Token KEYLA tidak valid.');

    $envelope = SignatureEnvelope::query()
        ->where('subject_type', AppraisalRequest::class)
        ->where('subject_id', $request->id)
        ->where('provider', 'peruri_signit')
        ->where('document_type', 'contract')
        ->first();

    expect($envelope)->not()->toBeNull();
    expect($envelope->status)->toBe('failed');
    expect($envelope->last_error)->toBe('Token KEYLA tidak valid.');
});
