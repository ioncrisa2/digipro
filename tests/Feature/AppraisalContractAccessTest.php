<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\ReportSigner;
use App\Models\SignatureEnvelope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'peruri.base_url' => 'https://peruri.test',
        'peruri.api_version' => 'v1',
        'peruri.corporate_id' => 'CORP-TEST',
        'peruri.client_id' => 'client-id',
        'peruri.client_secret' => 'client-secret',
        'peruri.extra_headers' => [],
    ]);
});

it('allows opening the contract page after payment moves request into valuation in progress', function () {
    $user = User::factory()->create();
    $request = createSignedAppraisalRequest($user, AppraisalStatusEnum::ValuationOnProgress);

    Http::fakeSequence()
        ->push([
            'status' => '00',
            'message' => 'OK',
            'data' => [
                'accessToken' => 'access-token-test',
                'expiredDate' => now()->endOfDay()->toIso8601String(),
            ],
        ], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isActive' => true]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['isActive' => true]], 200)
        ->push(['status' => '00', 'message' => 'OK', 'data' => ['registered' => true, 'active' => true]], 200);

    $this->actingAs($user)
        ->get(route('appraisal.contract.page', ['id' => $request->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Penilaian/ContractSign')
            ->where('signingReadiness.customer.overall.is_ready', true)
            ->where('signingReadiness.public_appraiser.readiness.overall.is_ready', true));
});

it('allows downloading the contract pdf after payment moves request into valuation in progress', function () {
    $user = User::factory()->create();
    $request = createSignedAppraisalRequest($user, AppraisalStatusEnum::ValuationOnProgress);

    $response = $this->actingAs($user)
        ->get(route('appraisal.contract.pdf', ['id' => $request->id]));

    $response->assertOk();
    expect((string) $response->headers->get('content-type'))->toContain('application/pdf');
});

it('renders the contract offer in office letter format with public appraiser left and customer right', function () {
    $html = view('pdfs.appraisal-contract-offer', [
        'doc' => contractOfferLayoutDoc(),
    ])->render();

    expect($html)->toContain('Kantor Jasa Penilai Publik');
    expect($html)->toContain('Perihal :');
    expect($html)->toContain('Hormat kami,');
    expect($html)->toContain('Persetujuan Customer,');
    expect($html)->toContain('Signature ID');
    expect(str_contains($html, 'class="asset-table"'))->toBeFalse();
    expect(strpos($html, 'Atasan Penandatangan'))->toBeLessThan(strpos($html, 'Persetujuan Customer,'));
    expect(strpos($html, 'Persetujuan Customer,'))->toBeLessThan(strpos($html, 'Customer Utama'));
});

it('downloads the stored original contract pdf while public appraiser signature is still pending', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $request = createSignedAppraisalRequest($user, AppraisalStatusEnum::ContractSigned);

    $originalPdfPath = "appraisal-requests/{$request->id}/contracts/contract-original-test.pdf";
    $pdfBinary = '%PDF-1.4 original contract';
    Storage::disk('public')->put($originalPdfPath, $pdfBinary);

    SignatureEnvelope::query()->create([
        'subject_type' => AppraisalRequest::class,
        'subject_id' => $request->id,
        'document_type' => 'contract',
        'provider' => 'peruri_signit',
        'model' => 'tier',
        'uploader_email' => 'uploader@digipro.test',
        'status' => 'awaiting_internal',
        'original_pdf_path' => $originalPdfPath,
        'meta' => [],
    ]);

    $response = $this->actingAs($user)
        ->get(route('appraisal.contract.pdf', ['id' => $request->id]));

    $response->assertOk();
    expect((string) $response->streamedContent())->toBe($pdfBinary);
});

function contractOfferLayoutDoc(): array
{
    return [
        'title' => 'PENAWARAN LAYANAN ESTIMASI RENTANG HARGA PROPERTI',
        'agr_no' => '00131/AGR/DP/04/2026',
        'request_id' => 'REQ-2026-000131',
        'city_date_line' => 'Jakarta, 09 April 2026',
        'recipient_lines' => ['CV Sindanglaya', 'Melalui portal DigiPro'],
        'subject' => 'Penawaran dan Lingkup Penugasan Layanan Kajian Nilai Pasar Properti Digital',
        'request_reference' => 'Menindaklanjuti permohonan penilaian digital melalui DigiPro.',
        'opening_paragraphs' => ['Isi penawaran tetap mengikuti payload DigiPro.'],
        'scope_items' => [
            [
                'no' => 1,
                'title' => 'Objek Penilaian',
                'lines' => ['Objek kajian mengikuti data aset DigiPro.'],
            ],
        ],
        'assets' => [
            [
                'label' => 'Tanah dan Bangunan',
                'address' => 'Palembang',
                'main_documents' => 'SHM',
                'area_basis' => 'DOC - LT 4.580,00 m2',
                'note' => 'Sesuai input customer',
            ],
        ],
        'support_contact' => ['phone' => '(0711) 5615793', 'whatsapp' => '+62 811 7101066', 'email' => 'support@digipro.test'],
        'sender' => [
            'organization' => 'DigiPro by KJPP HJAR',
            'division' => 'Layanan Kajian Nilai Pasar Properti Digital',
            'representative_title' => 'Penilai Publik / Penandatangan Atasan',
        ],
        'approval' => ['client_name' => 'Customer Utama', 'client_title' => 'Direktur'],
        'signatures' => [
            'public_appraiser' => ['name' => 'Atasan Penandatangan', 'email' => 'atasan@example.test', 'status' => null],
            'customer' => ['name' => 'Customer Utama', 'email' => 'customer@example.test', 'status' => null],
        ],
        'signature' => ['is_signed' => false],
        'envelope' => ['document_hash' => 'sha256:test'],
        'disclaimer_footer' => 'Dokumen ini bersifat informasi umum.',
    ];
}

function createSignedAppraisalRequest(User $user, AppraisalStatusEnum $status): AppraisalRequest
{
    $signer = ReportSigner::query()->create([
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik Kontrak',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    return AppraisalRequest::create([
        'user_id' => $user->id,
        'request_number' => 'REQ-'.Str::upper(Str::random(8)),
        'purpose' => 'jual_beli',
        'status' => $status,
        'requested_at' => now(),
        'client_name' => 'PT Test DigiPro',
        'contract_number' => '00015/AGR/DP/03/2026-'.Str::upper(Str::random(4)),
        'contract_date' => now()->toDateString(),
        'contract_status' => ContractStatusEnum::ContractSigned,
        'fee_total' => 1400000,
        'report_format' => 'digital',
        'contract_public_appraiser_signer_id' => $signer->id,
    ]);
}
