<?php

namespace App\Enums;

enum FinanceDocumentStatusEnum: string
{
    case Draft = 'draft';
    case ReadyToBill = 'ready_to_bill';
    case InvoiceIssued = 'invoice_issued';
    case TaxInvoiceRecorded = 'tax_invoice_recorded';
    case WithholdingRecorded = 'withholding_recorded';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::ReadyToBill => 'Siap Ditagihkan',
            self::InvoiceIssued => 'Invoice Terbit',
            self::TaxInvoiceRecorded => 'Faktur Pajak Terinput',
            self::WithholdingRecorded => 'Bukti Potong Terinput',
            self::Complete => 'Lengkap',
        };
    }
}
