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
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('Reviewer', 'web');
    AdminWorkspaceAccessSynchronizer::sync();

    config([
        'peruri.base_url' => 'https://peruri.test',
        'peruri.api_version' => 'v1',
        'peruri.corporate_id' => 'CORP-TEST',
        'peruri.client_id' => 'client-id',
        'peruri.client_secret' => 'client-secret',
        'peruri.uploader_email' => 'uploader@digipro.test',
        'peruri.extra_headers' => [],
        'peruri.coordinates.contract.public_appraiser' => [
            'page' => 1,
            'lower_left_x' => 40,
            'lower_left_y' => 120,
            'upper_right_x' => 220,
            'upper_right_y' => 200,
        ],
    ]);
});

function createAssignedContractRequest(ReportSigner $signer, string $envelopeStatus = 'awaiting_internal'): AppraisalRequest
{
    $customer = User::factory()->create();

    $request = AppraisalRequest::query()->create([
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
        'contract_public_appraiser_signer_id' => $signer->id,
    ]);

    $envelope = SignatureEnvelope::query()->create([
        'subject_type' => AppraisalRequest::class,
        'subject_id' => $request->id,
        'document_type' => 'contract',
        'provider' => 'peruri_signit',
        'model' => 'tier',
        'external_envelope_id' => 'TIER-' . Str::upper(Str::random(6)),
        'uploader_email' => config('peruri.uploader_email'),
        'status' => $envelopeStatus,
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
        'email' => $signer->email,
        'name' => $signer->name,
        'status' => $envelopeStatus === 'completed' ? 'signed' : 'pending',
        'signed_at' => $envelopeStatus === 'completed' ? now() : null,
        'meta' => [],
    ]);

    return $request;
}

it('shows only assigned active requests in the public appraiser queue', function (): void {
    $reviewer = User::factory()->create();
    $reviewer->assignRole('Reviewer');

    $otherReviewer = User::factory()->create();
    $otherReviewer->assignRole('Reviewer');

    $signer = ReportSigner::query()->create([
        'user_id' => $reviewer->id,
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    $otherSigner = ReportSigner::query()->create([
        'user_id' => $otherReviewer->id,
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik B',
        'email' => 'other@appraiser.test',
        'is_active' => true,
    ]);

    $visibleRequest = createAssignedContractRequest($signer, 'awaiting_internal');
    createAssignedContractRequest($otherSigner, 'awaiting_internal');
    createAssignedContractRequest($signer, 'completed');

    $expiredDate = now()->endOfDay()->toIso8601String();

    Http::fakeSequence()
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['accessToken' => 'access-token-test', 'expiredDate' => $expiredDate]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200);

    $this->actingAs($reviewer)
        ->get(route('reviewer.contract-signatures.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reviewer/ContractSignatures/Index')
            ->where('summary.siap_sign', 1)
            ->where('summary.selesai', 1)
            ->has('items', 1)
            ->where('items.0.id', $visibleRequest->id));
});

it('allows the assigned public appraiser to sign from the reviewer workspace', function (): void {
    Storage::fake('public');

    $reviewer = User::factory()->create();
    $reviewer->assignRole('Reviewer');

    $signer = ReportSigner::query()->create([
        'user_id' => $reviewer->id,
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    $request = createAssignedContractRequest($signer, 'awaiting_internal');
    $envelope = SignatureEnvelope::query()->where('subject_id', $request->id)->firstOrFail();

    $expiredDate = now()->endOfDay()->toIso8601String();
    $fakeSignedPdf = '%PDF-1.4 fake signed pdf';

    Http::fakeSequence()
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['accessToken' => 'access-token-test', 'expiredDate' => $expiredDate]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['verified' => true]], 200)
        ->push([
            'status' => '00',
            'message' => 'OK',
            'data' => [
                'signer' => [
                    ['email' => $request->user->email, 'orderId' => 'ORDER-CUST-1', 'sequence' => 1],
                    ['email' => $signer->email, 'orderId' => 'ORDER-PA-2', 'sequence' => 2],
                ],
            ],
        ], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => []], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => []], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['base64Document' => base64_encode($fakeSignedPdf)]], 200);

    $this->actingAs($reviewer)
        ->from(route('reviewer.contract-signatures.show', $request))
        ->post(route('reviewer.contract-signatures.sign', $request), [
            'keyla_token' => 'KEYLA123',
        ])
        ->assertRedirect(route('reviewer.contract-signatures.show', $request));

    $envelope->refresh();

    expect($envelope->status)->toBe('completed');
    expect($envelope->signed_pdf_path)->not()->toBeNull();
    Storage::disk('public')->assertExists($envelope->signed_pdf_path);
});

it('blocks signing when signer readiness is not ready', function (): void {
    $reviewer = User::factory()->create();
    $reviewer->assignRole('Reviewer');

    $signer = ReportSigner::query()->create([
        'user_id' => $reviewer->id,
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    $request = createAssignedContractRequest($signer, 'awaiting_internal');

    $expiredDate = now()->endOfDay()->toIso8601String();

    Http::fakeSequence()
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['accessToken' => 'access-token-test', 'expiredDate' => $expiredDate]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => false, 'active' => false]], 200);

    $this->actingAs($reviewer)
        ->from(route('reviewer.contract-signatures.show', $request))
        ->post(route('reviewer.contract-signatures.sign', $request), [
            'keyla_token' => 'KEYLA123',
        ])
        ->assertRedirect(route('reviewer.contract-signatures.show', $request))
        ->assertSessionHas('error', 'Akun KEYLA belum terhubung atau belum aktif.');
});

it('processes bulk sign per contract and reports partial failures', function (): void {
    Storage::fake('public');

    $reviewer = User::factory()->create();
    $reviewer->assignRole('Reviewer');

    $otherReviewer = User::factory()->create();
    $otherReviewer->assignRole('Reviewer');

    $signer = ReportSigner::query()->create([
        'user_id' => $reviewer->id,
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    $otherSigner = ReportSigner::query()->create([
        'user_id' => $otherReviewer->id,
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik B',
        'email' => 'other@appraiser.test',
        'is_active' => true,
    ]);

    $assignedRequest = createAssignedContractRequest($signer, 'awaiting_internal');
    $unassignedRequest = createAssignedContractRequest($otherSigner, 'awaiting_internal');
    $assignedEnvelope = SignatureEnvelope::query()->where('subject_id', $assignedRequest->id)->firstOrFail();

    $expiredDate = now()->endOfDay()->toIso8601String();
    $fakeSignedPdf = '%PDF-1.4 fake signed pdf';

    Http::fakeSequence()
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['accessToken' => 'access-token-test', 'expiredDate' => $expiredDate]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200) // canSign readiness cert
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200) // canSign readiness keyla
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isExpired' => false]], 200) // service cert
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200) // service keyla
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['verified' => true]], 200) // verify
        ->push([
            'status' => '00',
            'message' => 'OK',
            'data' => [
                'signer' => [
                    ['email' => $assignedRequest->user->email, 'orderId' => 'ORDER-CUST-1', 'sequence' => 1],
                    ['email' => $signer->email, 'orderId' => 'ORDER-PA-2', 'sequence' => 2],
                ],
            ],
        ], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => []], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => []], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['base64Document' => base64_encode($fakeSignedPdf)]], 200);

    $this->actingAs($reviewer)
        ->post(route('reviewer.contract-signatures.bulk-sign'), [
            'keyla_token' => 'KEYLA123',
            'appraisal_request_ids' => [$assignedRequest->id, $unassignedRequest->id],
        ])
        ->assertRedirect(route('reviewer.contract-signatures.index'))
        ->assertSessionHas('bulk_sign_result', function (array $result) use ($assignedRequest, $unassignedRequest): bool {
            expect($result['selected_count'])->toBe(2);
            expect($result['success_count'])->toBe(1);
            expect($result['failed_count'])->toBe(1);

            $results = collect($result['results']);

            return $results->contains(fn (array $item) => $item['request_id'] === $assignedRequest->id && $item['success'] === true)
                && $results->contains(fn (array $item) => $item['request_id'] === $unassignedRequest->id && $item['success'] === false);
        });

    $assignedEnvelope->refresh();
    expect($assignedEnvelope->status)->toBe('completed');
    Storage::disk('public')->assertExists($assignedEnvelope->signed_pdf_path);
});
