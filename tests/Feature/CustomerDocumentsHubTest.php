<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestFile;
use App\Models\GuidelineSet;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows only the authenticated customer requests in the documents hub index', function () {
    Storage::fake('public');
    createDocumentsHubGuidelineSet();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $otherUser = User::factory()->create(['email_verified_at' => now()]);

    $newRequest = createDocumentsHubRequest($user, [
        'request_number' => 'REQ-DOC-NEW',
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now()->subDays(4),
    ]);

    $signedRequest = createDocumentsHubRequest($user, [
        'request_number' => 'REQ-DOC-SIGNED',
        'status' => AppraisalStatusEnum::ContractSigned,
        'contract_status' => ContractStatusEnum::ContractSigned,
        'requested_at' => now()->subDays(3),
    ], [
        'contract_signed' => true,
        'payment_status' => 'pending',
    ]);

    $paidRequest = createDocumentsHubRequest($user, [
        'request_number' => 'REQ-DOC-PAID',
        'status' => AppraisalStatusEnum::ValuationOnProgress,
        'contract_status' => ContractStatusEnum::ContractSigned,
        'requested_at' => now()->subDays(2),
    ], [
        'contract_signed' => true,
        'payment_status' => 'paid',
        'legal_docs' => true,
    ]);

    $readyRequest = createDocumentsHubRequest($user, [
        'request_number' => 'REQ-DOC-REPORT',
        'status' => AppraisalStatusEnum::ReportReady,
        'contract_status' => ContractStatusEnum::ContractSigned,
        'requested_at' => now()->subDay(),
    ], [
        'contract_signed' => true,
        'payment_status' => 'paid',
        'legal_docs' => true,
        'report_ready' => true,
    ]);

    createDocumentsHubRequest($otherUser, [
        'request_number' => 'REQ-DOC-OTHER',
        'status' => AppraisalStatusEnum::Completed,
        'contract_status' => ContractStatusEnum::ContractSigned,
    ], [
        'contract_signed' => true,
        'payment_status' => 'paid',
        'legal_docs' => true,
        'report_ready' => true,
    ]);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('reports', 4)
            ->where('reports', function ($reports) use ($newRequest, $signedRequest, $paidRequest, $readyRequest) {
                $indexed = collect($reports)->keyBy('request_number');

                return ! $indexed->has('REQ-DOC-OTHER')
                    && $indexed->has($newRequest->request_number)
                    && $indexed->get($newRequest->request_number)['customer_documents_count'] >= 2
                    && $indexed->get($newRequest->request_number)['customer_photos_count'] >= 1
                    && $indexed->get($newRequest->request_number)['ready_contract'] === false
                    && $indexed->has($signedRequest->request_number)
                    && $indexed->get($signedRequest->request_number)['ready_contract'] === true
                    && $indexed->get($signedRequest->request_number)['ready_invoice'] === false
                    && $indexed->get($signedRequest->request_number)['ready_legal_documents'] === false
                    && $indexed->has($paidRequest->request_number)
                    && $indexed->get($paidRequest->request_number)['ready_invoice'] === true
                    && $indexed->get($paidRequest->request_number)['ready_legal_documents'] === true
                    && $indexed->get($paidRequest->request_number)['ready_report'] === false
                    && $indexed->has($readyRequest->request_number)
                    && $indexed->get($readyRequest->request_number)['ready_report'] === true;
            })
        );
});

it('renders grouped customer and system documents on the documents hub detail page', function () {
    Storage::fake('public');
    createDocumentsHubGuidelineSet();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $otherUser = User::factory()->create(['email_verified_at' => now()]);

    $record = createDocumentsHubRequest($user, [
        'request_number' => 'REQ-DOC-SHOW',
        'status' => AppraisalStatusEnum::ReportReady,
        'contract_status' => ContractStatusEnum::ContractSigned,
    ], [
        'contract_signed' => true,
        'payment_status' => 'paid',
        'legal_docs' => true,
        'report_ready' => true,
    ]);

    $this->actingAs($otherUser)
        ->get(route('reports.show', ['id' => $record->id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('reports.show', ['id' => $record->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Show')
            ->where('report.request_number', 'REQ-DOC-SHOW')
            ->where('report.summary.ready_contract', true)
            ->where('report.summary.ready_invoice', true)
            ->where('report.summary.ready_report', true)
            ->where('report.summary.ready_legal_documents', true)
            ->where('report.request_upload_documents', fn ($files) => collect($files)->pluck('type')->contains('npwp'))
            ->where('report.system_documents', fn ($files) => collect($files)->pluck('type')->contains('contract_pdf')
                && collect($files)->pluck('type')->contains('report_pdf'))
            ->where('report.legal_documents', fn ($files) => collect($files)->pluck('type')->contains('agreement_pdf')
                && collect($files)->pluck('type')->contains('disclaimer_pdf')
                && collect($files)->pluck('type')->contains('representative_letter_pdf'))
            ->where('report.billing_documents', fn ($files) => collect($files)->pluck('type')->contains('invoice_pdf'))
            ->where('report.asset_sections', function ($sections) {
                $first = collect($sections)->first();

                return count($sections) === 1
                    && collect($first['documents'] ?? [])->pluck('type')->contains('doc_pbb')
                    && collect($first['photos'] ?? [])->pluck('type')->contains('photo_front');
            })
        );
});

function createDocumentsHubGuidelineSet(): GuidelineSet
{
    return GuidelineSet::query()->create([
        'name' => 'Pedoman Dokumen Hub 2026',
        'year' => 2026,
        'description' => 'Guideline aktif untuk test documents hub.',
        'is_active' => true,
    ]);
}

function createDocumentsHubRequest(User $user, array $requestOverrides = [], array $options = []): AppraisalRequest
{
    $record = AppraisalRequest::create(array_merge([
        'user_id' => $user->id,
        'request_number' => 'REQ-' . Str::upper(Str::random(8)),
        'purpose' => 'jual_beli',
        'client_name' => 'PT Dokumen Hub',
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
        'contract_status' => ContractStatusEnum::WaitingSignature,
        'contract_number' => '00099/AGR/DP/03/2026-' . Str::upper(Str::random(4)),
        'contract_date' => now()->toDateString(),
        'fee_total' => 1800000,
        'report_type' => 'terinci',
        'report_format' => 'digital',
    ], $requestOverrides));

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'title_document' => 'SHM',
        'land_area' => 120,
        'building_area' => 80,
        'address' => 'Jl. Arsip Request No. 1',
    ]);

    storePublicFile("appraisal-requests/{$record->id}/request/npwp.pdf");
    AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'npwp',
        'path' => "appraisal-requests/{$record->id}/request/npwp.pdf",
        'original_name' => 'npwp.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    storePublicFile("appraisal-requests/{$record->id}/assets/{$asset->id}/documents/pbb.pdf");
    AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'doc_pbb',
        'path' => "appraisal-requests/{$record->id}/assets/{$asset->id}/documents/pbb.pdf",
        'original_name' => 'pbb.pdf',
        'mime' => 'application/pdf',
        'size' => 2048,
    ]);

    storePublicFile("appraisal-requests/{$record->id}/assets/{$asset->id}/photos/front.jpg", 'image-content');
    AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'photo_front',
        'path' => "appraisal-requests/{$record->id}/assets/{$asset->id}/photos/front.jpg",
        'original_name' => 'front.jpg',
        'mime' => 'image/jpeg',
        'size' => 4096,
    ]);

    if ($options['contract_signed'] ?? false) {
        storePublicFile("appraisal-requests/{$record->id}/contract/contract-signed.pdf");
        AppraisalRequestFile::create([
            'appraisal_request_id' => $record->id,
            'type' => 'contract_signed_pdf',
            'path' => "appraisal-requests/{$record->id}/contract/contract-signed.pdf",
            'original_name' => 'contract-signed.pdf',
            'mime' => 'application/pdf',
            'size' => 3200,
        ]);
    }

    if ($options['legal_docs'] ?? false) {
        foreach (['agreement_pdf', 'disclaimer_pdf', 'representative_letter_pdf'] as $type) {
            $filename = str_replace('_pdf', '', $type) . '.pdf';
            storePublicFile("appraisal-requests/{$record->id}/final/{$filename}");

            AppraisalRequestFile::create([
                'appraisal_request_id' => $record->id,
                'type' => $type,
                'path' => "appraisal-requests/{$record->id}/final/{$filename}",
                'original_name' => $filename,
                'mime' => 'application/pdf',
                'size' => 2500,
            ]);
        }
    }

    if (isset($options['payment_status'])) {
        Payment::create([
            'appraisal_request_id' => $record->id,
            'amount' => 1800000,
            'method' => 'gateway',
            'gateway' => 'midtrans',
            'external_payment_id' => 'PAY-' . Str::upper(Str::random(8)),
            'status' => $options['payment_status'],
            'paid_at' => $options['payment_status'] === 'paid' ? now() : null,
            'metadata' => [
                'invoice_number' => 'INV-' . Str::upper(Str::random(6)),
            ],
        ]);
    }

    if ($options['report_ready'] ?? false) {
        $path = "appraisal-requests/{$record->id}/report/final-report.pdf";
        storePublicFile($path);

        $record->update([
            'report_generated_at' => now(),
            'report_pdf_path' => $path,
            'report_pdf_size' => 5500,
        ]);
    }

    return $record->fresh(['assets.files', 'files', 'payments', 'user']);
}

function storePublicFile(string $path, string $contents = 'pdf-content'): void
{
    Storage::disk('public')->put($path, $contents);
}
