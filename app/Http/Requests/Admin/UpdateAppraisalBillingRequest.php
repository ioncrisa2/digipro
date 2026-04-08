<?php

namespace App\Http\Requests\Admin;

use App\Enums\FinanceDocumentStatusEnum;
use App\Enums\TaxIdentityTypeEnum;
use App\Enums\WithholdingTaxTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAppraisalBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAdminAccess();
    }

    protected function prepareForValidation(): void
    {
        $normalize = static fn (mixed $value): mixed => blank($value) ? null : $value;

        $this->merge([
            'billing_dpp_amount' => $normalize($this->input('billing_dpp_amount')),
            'billing_withholding_tax_type' => $normalize($this->input('billing_withholding_tax_type')),
            'finance_billing_name' => $normalize($this->input('finance_billing_name')),
            'finance_billing_address' => $normalize($this->input('finance_billing_address')),
            'finance_tax_identity_type' => $normalize($this->input('finance_tax_identity_type')),
            'finance_tax_identity_number' => $normalize($this->input('finance_tax_identity_number')),
            'finance_billing_email' => $normalize($this->input('finance_billing_email')),
            'billing_invoice_number' => $normalize($this->input('billing_invoice_number')),
            'billing_invoice_date' => $normalize($this->input('billing_invoice_date')),
            'tax_invoice_number' => $normalize($this->input('tax_invoice_number')),
            'tax_invoice_date' => $normalize($this->input('tax_invoice_date')),
            'withholding_receipt_number' => $normalize($this->input('withholding_receipt_number')),
            'withholding_receipt_date' => $normalize($this->input('withholding_receipt_date')),
            'finance_document_status' => $normalize($this->input('finance_document_status')),
        ]);
    }

    public function rules(): array
    {
        return [
            'billing_dpp_amount' => ['required', 'integer', 'min:1'],
            'billing_withholding_tax_type' => ['required', Rule::enum(WithholdingTaxTypeEnum::class)],
            'finance_billing_name' => ['required', 'string', 'max:255'],
            'finance_billing_address' => ['required', 'string'],
            'finance_tax_identity_type' => ['nullable', Rule::enum(TaxIdentityTypeEnum::class)],
            'finance_tax_identity_number' => ['nullable', 'string', 'max:100'],
            'finance_billing_email' => ['nullable', 'email', 'max:255'],
            'billing_invoice_number' => ['nullable', 'string', 'max:100'],
            'billing_invoice_date' => ['nullable', 'date'],
            'tax_invoice_number' => ['nullable', 'string', 'max:100'],
            'tax_invoice_date' => ['nullable', 'date'],
            'withholding_receipt_number' => ['nullable', 'string', 'max:100'],
            'withholding_receipt_date' => ['nullable', 'date'],
            'finance_document_status' => ['required', Rule::enum(FinanceDocumentStatusEnum::class)],
            'billing_invoice_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'tax_invoice_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'withholding_receipt_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'billing_dpp_amount.required' => 'Nilai Jasa wajib diisi.',
            'billing_dpp_amount.integer' => 'Nilai Jasa harus berupa angka bulat.',
            'billing_dpp_amount.min' => 'Nilai Jasa minimal 1 rupiah.',
            'finance_billing_name.required' => 'Nama tagihan wajib diisi.',
            'finance_billing_address.required' => 'Alamat tagihan wajib diisi.',
            'finance_billing_email.email' => 'Email tagihan harus berupa alamat email yang valid.',
            'billing_invoice_file.mimes' => 'File invoice harus berformat PDF.',
            'tax_invoice_file.mimes' => 'File faktur pajak harus berformat PDF.',
            'withholding_receipt_file.mimes' => 'File bukti potong harus berformat PDF.',
            'billing_invoice_file.max' => 'Ukuran file invoice maksimal 10 MB.',
            'tax_invoice_file.max' => 'Ukuran file faktur pajak maksimal 10 MB.',
            'withholding_receipt_file.max' => 'Ukuran file bukti potong maksimal 10 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = (string) ($this->input('finance_document_status') ?? '');
            $hasTaxInvoiceInput = filled($this->input('tax_invoice_number'))
                || filled($this->input('tax_invoice_date'))
                || $this->hasFile('tax_invoice_file');
            $hasWithholdingInput = filled($this->input('withholding_receipt_number'))
                || filled($this->input('withholding_receipt_date'))
                || $this->hasFile('withholding_receipt_file');
            $hasInvoiceInput = filled($this->input('billing_invoice_number'))
                || filled($this->input('billing_invoice_date'))
                || $this->hasFile('billing_invoice_file');

            if (
                in_array($status, [
                    FinanceDocumentStatusEnum::InvoiceIssued->value,
                    FinanceDocumentStatusEnum::TaxInvoiceRecorded->value,
                    FinanceDocumentStatusEnum::WithholdingRecorded->value,
                    FinanceDocumentStatusEnum::Complete->value,
                ], true)
                || $hasInvoiceInput
            ) {
                if (! filled($this->input('billing_invoice_number'))) {
                    $validator->errors()->add('billing_invoice_number', 'Nomor Invoice wajib diisi jika dokumen invoice sudah diterbitkan.');
                }

                if (! filled($this->input('billing_invoice_date'))) {
                    $validator->errors()->add('billing_invoice_date', 'Tanggal Invoice wajib diisi jika dokumen invoice sudah diterbitkan.');
                }
            }

            if (
                in_array($status, [
                    FinanceDocumentStatusEnum::TaxInvoiceRecorded->value,
                    FinanceDocumentStatusEnum::WithholdingRecorded->value,
                    FinanceDocumentStatusEnum::Complete->value,
                ], true)
                || $hasTaxInvoiceInput
            ) {
                if (! filled($this->input('tax_invoice_number'))) {
                    $validator->errors()->add('tax_invoice_number', 'Nomor Faktur Pajak wajib diisi jika faktur pajak sudah diterbitkan.');
                }

                if (! filled($this->input('tax_invoice_date'))) {
                    $validator->errors()->add('tax_invoice_date', 'Tanggal Faktur Pajak wajib diisi jika faktur pajak sudah diterbitkan.');
                }

                if (! filled($this->input('finance_tax_identity_type')) || ! filled($this->input('finance_tax_identity_number'))) {
                    $validator->errors()->add('finance_tax_identity_number', 'NPWP atau NIK lawan transaksi perlu dilengkapi untuk input faktur pajak.');
                }
            }

            if (
                in_array($status, [
                    FinanceDocumentStatusEnum::WithholdingRecorded->value,
                    FinanceDocumentStatusEnum::Complete->value,
                ], true)
                || $hasWithholdingInput
            ) {
                if (! filled($this->input('withholding_receipt_number'))) {
                    $validator->errors()->add('withholding_receipt_number', 'Nomor Bukti Potong wajib diisi jika bukti potong sudah tersedia.');
                }

                if (! filled($this->input('withholding_receipt_date'))) {
                    $validator->errors()->add('withholding_receipt_date', 'Tanggal Bukti Potong wajib diisi jika bukti potong sudah tersedia.');
                }
            }

            if (filled($this->input('finance_tax_identity_number')) && ! filled($this->input('finance_tax_identity_type'))) {
                $validator->errors()->add('finance_tax_identity_type', 'Pilih jenis identitas pajak sebelum mengisi nomor identitas pajak.');
            }
        });
    }
}
