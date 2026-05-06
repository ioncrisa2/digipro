<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestFile;
use App\Models\AppraisalUserConsent;
use App\Models\ConsentDocument;
use App\Models\GuidelineSet;
use App\Models\Payment;
use App\Models\ReportSigner;
use App\Models\User;
use App\Services\Customer\AppraisalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('requires the on-hand certificate statement before submitting an appraisal request', function () {
    Storage::fake('public');
    createActiveGuidelineSetForCustomerAppraisal();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $payload = appraisalRequestPayload([
        'sertifikat_on_hand_confirmed' => '0',
    ]);

    $this->actingAs($user)
        ->post(route('appraisal.store'), $payload)
        ->assertSessionHasErrors('sertifikat_on_hand_confirmed');
});

it('requires the not-encumbered certificate statement before submitting an appraisal request', function () {
    Storage::fake('public');
    createActiveGuidelineSetForCustomerAppraisal();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $payload = appraisalRequestPayload([
        'certificate_not_encumbered_confirmed' => '0',
    ]);

    $this->actingAs($user)
        ->post(route('appraisal.store'), $payload)
        ->assertSessionHasErrors('certificate_not_encumbered_confirmed');
});

it('stores legal gate statements on the appraisal request and exposes them on the customer detail page', function () {
    Storage::fake('public');
    createActiveGuidelineSetForCustomerAppraisal();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('appraisal.store'), appraisalRequestPayload())
        ->assertRedirect(route('appraisal.list'));

    $record = AppraisalRequest::query()->with('assets.files')->sole();

    expect($record->sertifikat_on_hand_confirmed)->toBeTrue();
    expect($record->certificate_not_encumbered_confirmed)->toBeTrue();
    expect($record->certificate_statements_accepted_at)->not->toBeNull();
    expect($record->certificate_statement_ip)->not->toBeNull();
    expect($record->certificate_statement_user_agent)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('appraisal.show', ['id' => $record->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Penilaian/Show')
            ->where('request.valuation_objective_label', 'Kajian Nilai Pasar dalam Bentuk Range')
            ->where('request.sertifikat_on_hand_confirmed', true)
            ->where('request.certificate_not_encumbered_confirmed', true)
        );
});

it('stores the consent snapshot on the appraisal request when the customer submits the form', function () {
    Storage::fake('public');
    createActiveGuidelineSetForCustomerAppraisal();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $document = createPublishedConsentDocument();

    $acceptedConsent = AppraisalUserConsent::create([
        'user_id' => $user->id,
        'consent_document_id' => $document->id,
        'code' => $document->code,
        'version' => $document->version,
        'hash' => $document->hash,
        'accepted_at' => now()->subMinute(),
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest Test',
    ]);

    $this->actingAs($user)
        ->withSession([
            'appraisal_consent.version' => $document->version,
            'appraisal_consent.hash' => $document->hash,
            'appraisal_consent.document_id' => $document->id,
        ])
        ->post(route('appraisal.store'), appraisalRequestPayload())
        ->assertRedirect(route('appraisal.list'));

    $record = AppraisalRequest::query()->latest('id')->firstOrFail();

    expect($record->consent_version)->toBe($document->version);
    expect($record->consent_hash)->toBe($document->hash);
    expect($record->consent_accepted_at)->not->toBeNull();
    expect($record->consent_ip)->not->toBeNull();
    expect($record->consent_user_agent)->not->toBeNull();
    expect($record->consent_accepted_at?->timestamp)->toBe($acceptedConsent->accepted_at?->timestamp);
});

it('generates final legal documents only after payment is verified', function () {
    Storage::fake('public');
    createActiveGuidelineSetForCustomerAppraisal();
    config()->set('payment.midtrans.server_key', 'SB-Mid-server-test');
    config()->set('peruri.base_url', 'https://peruri.test');
    config()->set('peruri.api_version', 'v1');
    config()->set('peruri.corporate_id', 'CORP-TEST');
    config()->set('peruri.client_id', 'client-id');
    config()->set('peruri.client_secret', 'client-secret');
    config()->set('peruri.uploader_email', 'uploader@digipro.test');
    config()->set('peruri.extra_headers', []);
    config()->set('peruri.coordinates.contract.customer', [
        'page' => 1,
        'lower_left_x' => 360,
        'lower_left_y' => 120,
        'upper_right_x' => 540,
        'upper_right_y' => 200,
    ]);
    config()->set('peruri.coordinates.contract.public_appraiser', [
        'page' => 1,
        'lower_left_x' => 40,
        'lower_left_y' => 120,
        'upper_right_x' => 220,
        'upper_right_y' => 200,
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'name' => 'Nadia Customer',
        'email' => 'nadia@example.test',
    ]);

    $publicSigner = ReportSigner::query()->create([
        'user_id' => null,
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    $document = createPublishedConsentDocument();

    $record = AppraisalRequest::create([
        'user_id' => $user->id,
        'request_number' => 'REQ-REP-'.Str::upper(Str::random(6)),
        'purpose' => 'jual_beli',
        'client_name' => 'PT Uji Representatif',
        'status' => AppraisalStatusEnum::WaitingSignature,
        'contract_status' => ContractStatusEnum::WaitingSignature,
        'requested_at' => now(),
        'contract_number' => '00021/AGR/DP/03/2026',
        'contract_date' => now()->toDateString(),
        'fee_total' => 1800000,
        'report_type' => 'terinci',
        'report_format' => 'digital',
        'consent_accepted_at' => now()->subMinutes(5),
        'consent_version' => $document->version,
        'consent_hash' => $document->hash,
        'consent_ip' => '127.0.0.1',
        'consent_user_agent' => 'Pest Test',
        'sertifikat_on_hand_confirmed' => true,
        'certificate_not_encumbered_confirmed' => true,
        'certificate_statements_accepted_at' => now(),
        'contract_public_appraiser_signer_id' => $publicSigner->id,
    ]);

    AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'title_document' => 'SHM',
        'land_area' => 150,
        'building_area' => 95,
        'address' => 'Jl. Representatif DigiPro No. 7',
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

    $this->actingAs($user)
        ->post(route('appraisal.contract.sign', ['id' => $record->id]), [
            'agree_contract' => '1',
            'keyla_token' => 'KEYLA123',
        ])
        ->assertRedirect(route('appraisal.payment.page', ['id' => $record->id]));

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::ContractSigned);
    expect($record->contract_status)->toBe(ContractStatusEnum::ContractSigned);
    expect($record->valuation_objective?->value ?? $record->valuation_objective)->toBe('kajian_nilai_pasar_dalam_bentuk_range');

    expect(AppraisalRequestFile::query()
        ->where('appraisal_request_id', $record->id)
        ->whereIn('type', ['representative_letter_pdf', 'agreement_pdf', 'disclaimer_pdf'])
        ->count())->toBe(0);

    $payment = Payment::create([
        'appraisal_request_id' => $record->id,
        'amount' => 1800000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'DIGIPRO-LEGAL-'.Str::upper(Str::random(6)),
        'status' => 'pending',
        'proof_type' => 'gateway_id',
        'metadata' => [
            'invoice_number' => 'INV-2026-00077',
        ],
    ]);

    $grossAmount = '1800000.00';
    $signature = hash('sha512', $payment->external_payment_id.'200'.$grossAmount.config('payment.midtrans.server_key'));

    $this->postJson(route('payments.midtrans.notification'), [
        'order_id' => $payment->external_payment_id,
        'status_code' => '200',
        'gross_amount' => $grossAmount,
        'transaction_status' => 'settlement',
        'payment_type' => 'bank_transfer',
        'transaction_id' => 'trx-midtrans-legal-001',
        'va_numbers' => [
            ['bank' => 'bca', 'va_number' => '1234567890'],
        ],
        'signature_key' => $signature,
    ])->assertOk();

    $payment->refresh();
    $record->refresh();

    expect($payment->status)->toBe('paid');
    expect($payment->paid_at)->not->toBeNull();
    expect($record->status)->toBe(AppraisalStatusEnum::ValuationOnProgress);

    $types = AppraisalRequestFile::query()
        ->where('appraisal_request_id', $record->id)
        ->pluck('type')
        ->all();

    expect($types)->toContain('agreement_pdf');
    expect($types)->toContain('disclaimer_pdf');
    expect($types)->toContain('representative_letter_pdf');

    $agreement = AppraisalRequestFile::query()
        ->where('appraisal_request_id', $record->id)
        ->where('type', 'agreement_pdf')
        ->sole();

    $disclaimer = AppraisalRequestFile::query()
        ->where('appraisal_request_id', $record->id)
        ->where('type', 'disclaimer_pdf')
        ->sole();

    $representativeLetter = AppraisalRequestFile::query()
        ->where('appraisal_request_id', $record->id)
        ->where('type', 'representative_letter_pdf')
        ->sole();

    Storage::disk('public')->assertExists($agreement->path);
    Storage::disk('public')->assertExists($disclaimer->path);
    Storage::disk('public')->assertExists($representativeLetter->path);

    $payload = app(AppraisalService::class)->buildRepresentativeLetterPayload($record->fresh('user', 'assets'));
    $encodedPayload = json_encode($payload);

    expect($encodedPayload)->toContain('DigiPro');
    expect($encodedPayload)->not->toContain('KJPP');
    expect($encodedPayload)->toContain('Kajian Nilai Pasar dalam Bentuk Range');

    $this->actingAs($user)
        ->get(route('appraisal.show', ['id' => $record->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Penilaian/Show')
            ->where('request.request_files', fn ($files) => collect($files)->pluck('type')->contains('agreement_pdf')
                && collect($files)->pluck('type')->contains('disclaimer_pdf')
                && collect($files)->pluck('type')->contains('representative_letter_pdf'))
        );
});

function createActiveGuidelineSetForCustomerAppraisal(): GuidelineSet
{
    return GuidelineSet::query()->create([
        'name' => 'Pedoman DigiPro 2026',
        'year' => 2026,
        'description' => 'Guideline aktif untuk test customer appraisal.',
        'is_active' => true,
    ]);
}

function createPublishedConsentDocument(): ConsentDocument
{
    $sections = [
        [
            'heading' => 'Persetujuan Layanan',
            'body' => 'Customer memahami bahwa layanan DigiPro bersifat kajian tanpa inspeksi lapangan.',
        ],
        [
            'heading' => 'Pembatasan Penggunaan',
            'body' => 'Dokumen hanya digunakan sesuai ruang lingkup layanan DigiPro.',
        ],
    ];

    return ConsentDocument::query()->create([
        'code' => 'appraisal_request_consent',
        'version' => '2026.03',
        'title' => 'Consent DigiPro 2026',
        'sections' => $sections,
        'checkbox_label' => 'Saya menyetujui dokumen ini.',
        'hash' => ConsentDocument::computeHash([
            'code' => 'appraisal_request_consent',
            'version' => '2026.03',
            'title' => 'Consent DigiPro 2026',
            'sections' => $sections,
            'checkbox_label' => 'Saya menyetujui dokumen ini.',
        ]),
        'status' => 'published',
        'published_at' => now(),
    ]);
}

function appraisalRequestPayload(array $overrides = []): array
{
    $payload = [
        'client_name' => 'PT Customer Test',
        'purpose' => 'jual_beli',
        'sertifikat_on_hand_confirmed' => '1',
        'certificate_not_encumbered_confirmed' => '1',
        'assets' => [
            [
                'type' => 'tanah',
                'land_area' => 120,
                'peruntukan' => 'rumah_tinggal',
                'title_document' => 'SHM',
                'land_shape' => 'Persegi',
                'land_position' => 'Tengah',
                'land_condition' => 'Siap bangun',
                'topography' => 'Datar',
                'frontage_width' => 8,
                'access_road_width' => 6,
                'address' => 'Jl. Permohonan Test No. 1',
                'coordinates' => [
                    'lat' => '-6.200000',
                    'lng' => '106.816666',
                ],
                'doc_pbb' => UploadedFile::fake()->create('pbb.pdf', 200, 'application/pdf'),
                'doc_certs' => [
                    UploadedFile::fake()->create('sertifikat.pdf', 240, 'application/pdf'),
                ],
                'photos_access_road' => [
                    UploadedFile::fake()->image('akses-jalan.jpg'),
                ],
                'photos_front' => [
                    UploadedFile::fake()->image('depan.jpg'),
                ],
                'photos_interior' => [
                    UploadedFile::fake()->image('dalam.jpg'),
                ],
            ],
        ],
    ];

    return array_replace_recursive($payload, $overrides);
}
