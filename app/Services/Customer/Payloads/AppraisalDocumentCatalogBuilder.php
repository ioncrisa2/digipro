<?php

namespace App\Services\Customer\Payloads;

use App\Models\AppraisalRequest;
use App\Services\Finance\AppraisalBillingService;
use App\Services\Revisions\AppraisalRevisionFileResolver;
use Illuminate\Support\Facades\Storage;

class AppraisalDocumentCatalogBuilder
{
    public function __construct(
        private readonly AppraisalPayloadFormatter $formatter,
        private readonly AppraisalRevisionFileResolver $fileResolver,
        private readonly AppraisalBillingService $billingService,
    ) {
    }

    public function resolveCollections(AppraisalRequest $record): array
    {
        $record->loadMissing([
            'user:id,name,email',
            'assets:id,appraisal_request_id,asset_type,address',
            'assets.files:id,appraisal_asset_id,type,path,original_name,mime,size,created_at',
            'files:id,appraisal_request_id,type,path,original_name,mime,size,created_at',
            'payments:id,appraisal_request_id,amount,method,gateway,external_payment_id,status,paid_at,metadata,updated_at,created_at',
        ]);

        $approvedRevisionItems = $this->fileResolver->approvedItemsForRequest($record);
        $activeAssetFiles = $this->fileResolver->activeAssetFilesByRequest($record, $approvedRevisionItems);
        $activeRequestFiles = $this->fileResolver->activeRequestFiles($record, $approvedRevisionItems);
        $latestPayment = $record->payments->sortByDesc('id')->first();

        $documents = $record->assets
            ->flatMap(function ($asset) use ($activeAssetFiles) {
                return collect($activeAssetFiles[$asset->id] ?? [])->map(function ($file) use ($asset) {
                    return $this->mapStoredFilePayload($file, (int) $asset->id, (string) $asset->asset_type);
                });
            })
            ->values()
            ->all();

        $requestFiles = $activeRequestFiles
            ->map(fn ($file) => $this->mapStoredFilePayload($file))
            ->values()
            ->all();

        $requestUploadDocuments = array_values(array_filter(
            $requestFiles,
            fn ($file) => in_array((string) $file['type'], $this->formatter->customerRequestFileTypes(), true)
        ));

        $assetSections = $record->assets
            ->sortBy('id')
            ->values()
            ->map(function ($asset, $index) use ($documents): array {
                $assetDocs = array_values(array_filter($documents, fn ($file) => (int) ($file['asset_id'] ?? 0) === (int) $asset->id));

                return [
                    'id' => $asset->id,
                    'title' => 'Aset #' . ($index + 1) . ' - ' . ($this->formatter->assetTypeLegacyLabel($this->formatter->enumBackedValue(\App\Enums\AssetTypeEnum::class, $asset->asset_type)) ?: 'Aset'),
                    'address' => $asset->address ?: '-',
                    'documents' => array_values(array_filter($assetDocs, fn ($file) => ! $this->formatter->isPhotoFileType($file['type'] ?? null))),
                    'photos' => array_values(array_filter($assetDocs, fn ($file) => $this->formatter->isPhotoFileType($file['type'] ?? null))),
                ];
            })
            ->all();

        $systemDocuments = $this->buildSystemDocumentEntries($record, $requestFiles);
        $legalDocuments = array_values(array_filter(
            $requestFiles,
            fn ($file) => in_array((string) $file['type'], $this->formatter->legalFinalRequestFileTypes(), true)
        ));
        $billingDocuments = $this->buildBillingDocumentEntries($record, $latestPayment);

        return [
            'documents' => $documents,
            'request_files' => $requestFiles,
            'request_upload_documents' => $requestUploadDocuments,
            'asset_sections' => $assetSections,
            'system_documents' => $systemDocuments,
            'legal_documents' => $legalDocuments,
            'billing_documents' => $billingDocuments,
            'first_asset_address' => $record->assets->sortBy('id')->first()?->address ?: '-',
            'latest_payment' => $latestPayment,
        ];
    }

    public function buildSummary(array $collections): array
    {
        $requestUploadsCount = count($collections['request_upload_documents'] ?? []);
        $assetSections = collect($collections['asset_sections'] ?? []);
        $assetDocumentsCount = $assetSections->sum(fn ($section) => count($section['documents'] ?? []));
        $assetPhotosCount = $assetSections->sum(fn ($section) => count($section['photos'] ?? []));
        $systemDocuments = $collections['system_documents'] ?? [];
        $legalDocuments = $collections['legal_documents'] ?? [];
        $billingDocuments = $collections['billing_documents'] ?? [];
        $systemDocumentsCount = count($systemDocuments) + count($legalDocuments) + count($billingDocuments);

        return [
            'customer_documents_count' => $requestUploadsCount + $assetDocumentsCount,
            'customer_photos_count' => $assetPhotosCount,
            'system_documents_count' => $systemDocumentsCount,
            'ready_contract' => collect($systemDocuments)->contains(fn ($item) => ($item['type'] ?? null) === 'contract_pdf'),
            'ready_report' => collect($systemDocuments)->contains(fn ($item) => ($item['type'] ?? null) === 'report_pdf'),
            'ready_invoice' => count($billingDocuments) > 0,
            'ready_legal_documents' => count($legalDocuments) === count($this->formatter->legalFinalRequestFileTypes()),
            'total_documents_count' => $requestUploadsCount + $assetDocumentsCount + $assetPhotosCount + $systemDocumentsCount,
        ];
    }

    private function mapStoredFilePayload(object $file, ?int $assetId = null, ?string $assetType = null): array
    {
        $url = null;

        if ($file->path && Storage::disk('public')->exists($file->path)) {
            $url = Storage::disk('public')->url($file->path);
        }

        return [
            'id' => $file->id,
            'type' => (string) $file->type,
            'label' => $this->formatter->contractDocumentTypeLabel($file->type),
            'original_name' => $file->original_name ?: basename((string) $file->path),
            'mime' => $file->mime,
            'size' => (int) ($file->size ?? 0),
            'created_at' => $file->created_at?->toDateTimeString(),
            'url' => $url,
            'path' => $file->path,
            'asset_id' => $assetId,
            'asset_type' => $assetType,
        ];
    }

    private function buildSystemDocumentEntries(AppraisalRequest $record, array $requestFiles): array
    {
        $status = $record->status?->value ?? (string) $record->status;
        $entries = [];
        $signedContract = collect($requestFiles)->firstWhere('type', 'contract_signed_pdf');

        if ($this->formatter->isContractAccessibleStatus($status)) {
            $entries[] = [
                'id' => 'contract-' . $record->id,
                'type' => 'contract_pdf',
                'label' => 'Kontrak',
                'original_name' => $signedContract['original_name'] ?? ('Kontrak-' . ($record->request_number ?? $record->id) . '.pdf'),
                'mime' => 'application/pdf',
                'size' => (int) ($signedContract['size'] ?? 0),
                'created_at' => $signedContract['created_at'] ?? optional($record->contract_date)->toDateString(),
                'url' => route('appraisal.contract.pdf', ['id' => $record->id]),
                'path' => $signedContract['path'] ?? null,
                'asset_id' => null,
                'asset_type' => null,
            ];
        }

        if (
            $record->report_generated_at
            && $record->report_pdf_path
            && Storage::disk('public')->exists($record->report_pdf_path)
        ) {
            $entries[] = [
                'id' => 'report-' . $record->id,
                'type' => 'report_pdf',
                'label' => 'Laporan Kajian Pasar',
                'original_name' => basename((string) $record->report_pdf_path),
                'mime' => 'application/pdf',
                'size' => (int) ($record->report_pdf_size ?? 0),
                'created_at' => optional($record->report_generated_at)->toDateTimeString(),
                'url' => Storage::disk('public')->url($record->report_pdf_path),
                'path' => $record->report_pdf_path,
                'asset_id' => null,
                'asset_type' => null,
            ];
        }

        return $entries;
    }

    private function buildBillingDocumentEntries(AppraisalRequest $record, mixed $latestPayment): array
    {
        $entries = [];

        if ($latestPayment && $latestPayment->status === 'paid') {
            $invoiceNumber = $this->billingService->invoiceNumber($record, $latestPayment);

            $entries[] = [
                'id' => 'invoice-' . $record->id,
                'type' => 'invoice_pdf',
                'label' => 'Invoice Tagihan',
                'original_name' => $invoiceNumber . '.pdf',
                'mime' => 'application/pdf',
                'size' => 0,
                'created_at' => optional($latestPayment->paid_at)->toDateTimeString(),
                'url' => route('appraisal.invoice.pdf', ['id' => $record->id]),
                'path' => null,
                'asset_id' => null,
                'asset_type' => null,
            ];
        }

        if (filled($record->tax_invoice_file_path) && Storage::disk('public')->exists($record->tax_invoice_file_path)) {
            $entries[] = [
                'id' => 'tax-invoice-' . $record->id,
                'type' => 'tax_invoice_pdf',
                'label' => 'Faktur Pajak',
                'original_name' => basename((string) $record->tax_invoice_file_path),
                'mime' => 'application/pdf',
                'size' => 0,
                'created_at' => optional($record->tax_invoice_date)->toDateString(),
                'url' => Storage::disk('public')->url($record->tax_invoice_file_path),
                'path' => $record->tax_invoice_file_path,
                'asset_id' => null,
                'asset_type' => null,
            ];
        }

        if (filled($record->withholding_receipt_file_path) && Storage::disk('public')->exists($record->withholding_receipt_file_path)) {
            $entries[] = [
                'id' => 'withholding-' . $record->id,
                'type' => 'withholding_receipt_pdf',
                'label' => 'Bukti Potong PPh 23',
                'original_name' => basename((string) $record->withholding_receipt_file_path),
                'mime' => 'application/pdf',
                'size' => 0,
                'created_at' => optional($record->withholding_receipt_date)->toDateString(),
                'url' => Storage::disk('public')->url($record->withholding_receipt_file_path),
                'path' => $record->withholding_receipt_file_path,
                'asset_id' => null,
                'asset_type' => null,
            ];
        }

        if (filled($record->billing_invoice_file_path) && Storage::disk('public')->exists($record->billing_invoice_file_path)) {
            $entries[] = [
                'id' => 'billing-upload-' . $record->id,
                'type' => 'billing_invoice_uploaded_pdf',
                'label' => 'Invoice Tagihan (Upload Admin)',
                'original_name' => basename((string) $record->billing_invoice_file_path),
                'mime' => 'application/pdf',
                'size' => 0,
                'created_at' => optional($record->billing_invoice_date)->toDateString(),
                'url' => Storage::disk('public')->url($record->billing_invoice_file_path),
                'path' => $record->billing_invoice_file_path,
                'asset_id' => null,
                'asset_type' => null,
            ];
        }

        return $entries;
    }
}
