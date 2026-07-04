<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\ReportSigner;
use App\Models\SignatureEnvelope;
use App\Models\User;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    AdminWorkspaceAccessSynchronizer::sync();

    config([
        'signatures.contract_mode' => 'canvas_demo',
        'signatures.canvas_demo.provider' => 'canvas_demo',
        'signatures.canvas_demo.signature_disk' => 'local',
        'signatures.canvas_demo.document_disk' => 'public',
    ]);

    Storage::fake('local');
    Storage::fake('public');
});

it('lets an admin store and preview a public appraiser demo specimen', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $signer = ReportSigner::query()->create([
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik Demo',
        'email' => 'signer@example.test',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.master-data.report-signers.demo-signature.store', $signer), [
            'signature_image' => UploadedFile::fake()->image('signature.png', 600, 200),
        ])
        ->assertRedirect(route('admin.master-data.report-signers.edit', $signer));

    $signer->refresh();

    expect($signer->demo_signature_path)->not()->toBeNull()
        ->and($signer->demo_signature_hash)->toStartWith('sha256:')
        ->and($signer->demo_signature_updated_by)->toBe($admin->id);

    Storage::disk('local')->assertExists($signer->demo_signature_path);

    $this->actingAs($admin)
        ->get(route('admin.master-data.report-signers.demo-signature.show', $signer))
        ->assertSuccessful()
        ->assertHeader('content-type', 'image/png');
});

it('keeps the canvas page open when the assigned public appraiser has no demo specimen', function () {
    $customer = User::factory()->create();
    $signer = createCanvasDemoSigner();
    $request = createCanvasDemoAppraisal($customer, $signer);

    $this->actingAs($customer)
        ->get(route('appraisal.contract.page', $request->id))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Penilaian/ContractSign')
            ->where('signatureMode', 'canvas_demo')
            ->where('signingReadiness.can_customer_sign', false)
            ->where(
                'signingReadiness.public_appraiser.readiness.overall.message',
                'Tanda tangan demo penilai publik belum disetel oleh admin.',
            ));
});

it('stores both signatures and generates the final contract pdf without calling Peruri', function () {
    Http::fake();

    $customer = User::factory()->create();
    $signer = createCanvasDemoSigner(withSpecimen: true);
    $request = createCanvasDemoAppraisal($customer, $signer);

    $this->actingAs($customer)
        ->post(route('appraisal.contract.sign', $request->id), [
            'agree_contract' => true,
            'signature_image' => UploadedFile::fake()->image('customer-signature.png', 600, 200),
        ])
        ->assertRedirect(route('appraisal.payment.page', $request->id));

    Http::assertNothingSent();

    $request->refresh();
    expect($request->status)->toBe(AppraisalStatusEnum::ContractSigned)
        ->and($request->contract_status)->toBe(ContractStatusEnum::ContractSigned);

    $envelope = SignatureEnvelope::query()
        ->where('subject_type', AppraisalRequest::class)
        ->where('subject_id', $request->id)
        ->where('provider', 'canvas_demo')
        ->with('participants')
        ->firstOrFail();

    $customerParticipant = $envelope->participants->firstWhere('role', 'customer');
    $publicParticipant = $envelope->participants->firstWhere('role', 'public_appraiser');

    expect($envelope->status)->toBe('completed')
        ->and($envelope->document_hash)->toStartWith('sha256:')
        ->and(data_get($envelope->meta, 'final_document_hash'))->toStartWith('sha256:')
        ->and($customerParticipant?->status)->toBe('signed')
        ->and(data_get($customerParticipant?->meta, 'method'))->toBe('canvas')
        ->and(data_get($customerParticipant?->meta, 'automatic'))->toBeFalse()
        ->and($publicParticipant?->status)->toBe('signed')
        ->and(data_get($publicParticipant?->meta, 'method'))->toBe('admin_specimen')
        ->and(data_get($publicParticipant?->meta, 'automatic'))->toBeTrue();

    Storage::disk('public')->assertExists($envelope->original_pdf_path);
    Storage::disk('public')->assertExists($envelope->signed_pdf_path);
    Storage::disk('local')->assertExists(data_get($customerParticipant?->meta, 'signature_path'));
    Storage::disk('local')->assertExists(data_get($publicParticipant?->meta, 'signature_path'));

    expect($request->files()->where('type', 'contract_signed_pdf')->exists())->toBeTrue()
        ->and($request->offerNegotiations()->where('action', 'contract_sign_canvas_demo')->exists())->toBeTrue();

    $this->actingAs($customer)
        ->get(route('appraisal.contract.pdf', $request->id))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

function createCanvasDemoSigner(bool $withSpecimen = false): ReportSigner
{
    $signer = ReportSigner::query()->create([
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik Demo',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    if (! $withSpecimen) {
        return $signer;
    }

    $specimen = UploadedFile::fake()->image('public-signature.png', 600, 200);
    $binary = file_get_contents($specimen->getRealPath());
    $path = "demo-signatures/report-signers/{$signer->id}/public.png";
    Storage::disk('local')->put($path, $binary);

    $signer->update([
        'demo_signature_path' => $path,
        'demo_signature_mime' => 'image/png',
        'demo_signature_hash' => 'sha256:'.hash('sha256', $binary),
        'demo_signature_updated_at' => now(),
    ]);

    return $signer;
}

function createCanvasDemoAppraisal(User $customer, ReportSigner $signer): AppraisalRequest
{
    return AppraisalRequest::query()->create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-'.Str::upper(Str::random(8)),
        'purpose' => 'jual_beli',
        'status' => AppraisalStatusEnum::WaitingSignature,
        'requested_at' => now(),
        'client_name' => 'PT Demo DigiPro',
        'contract_number' => 'AGR-DEMO-'.Str::upper(Str::random(8)),
        'contract_date' => now()->toDateString(),
        'contract_status' => ContractStatusEnum::WaitingSignature,
        'fee_total' => 1400000,
        'report_format' => 'digital',
        'contract_public_appraiser_signer_id' => $signer->id,
        'contract_signer_snapshot' => [
            'public_appraiser' => [
                'id' => $signer->id,
                'name' => $signer->name,
                'email' => $signer->email,
            ],
        ],
    ]);
}
