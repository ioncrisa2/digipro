<?php

use Barryvdh\DomPDF\Facade\Pdf;

it('renders the appraisal invoice in office format with digipro payment context', function (): void {
    $invoice = invoiceFormatPayload();
    $html = view('pdfs.appraisal-invoice', ['invoice' => $invoice])->render();

    expect($html)->toContain('INVOICE');
    expect($html)->toContain('To : YoyoNasi');
    expect($html)->toContain('INVOICE NUMBER');
    expect($html)->toContain('NO');
    expect($html)->toContain('DESCRIPTION');
    expect($html)->toContain('UNIT PRICE');
    expect($html)->toContain('AMOUNT');
    expect($html)->toContain('Pay this amount');
    expect($html)->toContain('Delapan Ratus Ribu Rupiah');
    expect($html)->toContain('Dipotong PPh 23: Rp 14.414');
    expect($html)->toContain('Catatan DigiPro');
    expect($html)->toContain('Penerima');
    expect($html)->not->toContain('status-badge');
    expect($html)->not->toContain('payment-box');
    expect($html)->not->toContain('No Rek');
    expect($html)->not->toContain('BNI GIRO');

    $pdf = Pdf::loadView('pdfs.appraisal-invoice', ['invoice' => $invoice])
        ->setPaper('a4', 'portrait');

    $pdf->output();

    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
});

function invoiceFormatPayload(): array
{
    return [
        'invoice_number' => 'INV-2026-00008',
        'request_number' => 'REQ-2026-VLRPFW',
        'contract_number' => '00111/AGR/DP/04/2026',
        'issued_at' => '2026-04-15 16:57:52',
        'client_name' => 'YoyoNasi',
        'amount' => 800000,
        'method' => 'BCA Virtual Account',
        'status_label' => 'LUNAS',
        'company_name' => 'DigiPro by KJPP HJAR',
        'external_payment_id' => 'DIGIPRO-REQ-2026-VLRPFW-PAY-8-20260415095729',
        'gateway_details' => [
            'label' => 'BCA Virtual Account',
            'reference' => '03034665863363510402052',
        ],
        'billing_summary' => [
            'nilai_jasa_dpp' => 720721,
            'tarif_ppn_persen' => 11,
            'nilai_ppn' => 79279,
            'total_tagihan' => 800000,
            'jenis_pph_dipotong_label' => 'PPh 23',
            'nilai_pph_dipotong' => 14414,
            'total_transfer_customer' => 785586,
            'nama_tagihan' => 'YoyoNasi',
            'alamat_tagihan' => 'Jakarta Selatan',
            'tanggal_invoice' => '2026-04-15',
        ],
    ];
}
