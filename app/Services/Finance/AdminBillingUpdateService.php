<?php

namespace App\Services\Finance;

use App\Models\AppraisalRequest;
use Illuminate\Http\UploadedFile;

class AdminBillingUpdateService
{
    public function __construct(
        private readonly AppraisalBillingService $billingService,
    ) {
    }

    public function update(AppraisalRequest $appraisalRequest, array $validated, array $files = []): void
    {
        $billingAttributes = $this->billingService->appraisalAttributesFromDpp(
            (int) $validated['billing_dpp_amount'],
            $appraisalRequest->user,
            [
                'billing_withholding_tax_type' => $validated['billing_withholding_tax_type'],
            ]
        );

        $payload = array_merge($billingAttributes, [
            'finance_billing_name' => $validated['finance_billing_name'],
            'finance_billing_address' => $validated['finance_billing_address'],
            'finance_tax_identity_type' => $validated['finance_tax_identity_type'] ?? null,
            'finance_tax_identity_number' => $validated['finance_tax_identity_number'] ?? null,
            'finance_billing_email' => $validated['finance_billing_email'] ?? null,
            'billing_invoice_number' => $validated['billing_invoice_number'] ?? null,
            'billing_invoice_date' => $validated['billing_invoice_date'] ?? null,
            'tax_invoice_number' => $validated['tax_invoice_number'] ?? null,
            'tax_invoice_date' => $validated['tax_invoice_date'] ?? null,
            'withholding_receipt_number' => $validated['withholding_receipt_number'] ?? null,
            'withholding_receipt_date' => $validated['withholding_receipt_date'] ?? null,
            'finance_document_status' => $validated['finance_document_status'],
        ]);

        if (($files['billing_invoice_file'] ?? null) instanceof UploadedFile) {
            $payload['billing_invoice_file_path'] = $files['billing_invoice_file']
                ->store("finance/{$appraisalRequest->id}/invoice", 'public');
        }

        if (($files['tax_invoice_file'] ?? null) instanceof UploadedFile) {
            $payload['tax_invoice_file_path'] = $files['tax_invoice_file']
                ->store("finance/{$appraisalRequest->id}/faktur-pajak", 'public');
        }

        if (($files['withholding_receipt_file'] ?? null) instanceof UploadedFile) {
            $payload['withholding_receipt_file_path'] = $files['withholding_receipt_file']
                ->store("finance/{$appraisalRequest->id}/bukti-potong", 'public');
        }

        $appraisalRequest->update($payload);
    }
}
