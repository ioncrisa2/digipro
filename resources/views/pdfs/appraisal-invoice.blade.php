<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice Pembayaran</title>
    <style>
        @page { margin: 22px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #0f172a;
            background: #f8fafc;
        }
        .page {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #ffffff;
            padding: 16px;
        }
        .top-accent {
            height: 6px;
            border-radius: 4px;
            background: #0ea5e9;
            margin-bottom: 12px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .header-left {
            width: 58%;
            vertical-align: top;
        }
        .header-right {
            width: 42%;
            vertical-align: top;
            text-align: right;
        }
        .company {
            font-size: 19px;
            font-weight: 700;
            letter-spacing: 0.3px;
            margin: 0;
            color: #0f172a;
        }
        .invoice-title {
            margin: 3px 0 0;
            font-size: 13px;
            color: #334155;
            font-weight: 600;
        }
        .subline {
            margin-top: 4px;
            font-size: 10px;
            color: #64748b;
        }
        .status-badge {
            display: inline-block;
            border: 1px solid #22c55e;
            background: #dcfce7;
            color: #166534;
            border-radius: 999px;
            padding: 3px 12px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .25px;
        }
        .meta-box {
            margin-top: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 9px 10px;
            background: #f8fafc;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .meta-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .meta-label {
            width: 145px;
            color: #64748b;
        }
        .meta-colon {
            width: 10px;
            color: #64748b;
        }
        .section-title {
            margin: 14px 0 6px;
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .detail-table th,
        .detail-table td {
            border: 1px solid #cbd5e1;
            padding: 7px;
            vertical-align: top;
        }
        .detail-table th {
            background: #f1f5f9;
            color: #334155;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .2px;
        }
        .amount-col {
            width: 175px;
            text-align: right;
        }
        .total-row td {
            background: #f8fafc;
            font-weight: 700;
        }
        .payment-box {
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #f0f9ff;
            padding: 8px 10px;
            margin-top: 6px;
        }
        .payment-box-title {
            font-size: 10px;
            font-weight: 700;
            color: #0369a1;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: .25px;
        }
        .payment-table {
            width: 100%;
            border-collapse: collapse;
        }
        .payment-table td {
            padding: 2px 0;
            vertical-align: top;
        }
        .payment-label {
            width: 148px;
            color: #475569;
        }
        .footer {
            margin-top: 16px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            font-size: 10px;
            color: #64748b;
        }
        .signature {
            margin-top: 6px;
            text-align: right;
            font-size: 10px;
            color: #475569;
        }
    </style>
</head>
<body>
@php
    $idr = fn ($value) => 'Rp ' . number_format((int) ($value ?? 0), 0, ',', '.');
    $bank = is_array($invoice['selected_bank_account'] ?? null) ? $invoice['selected_bank_account'] : null;
    $gateway = is_array($invoice['gateway_details'] ?? null) ? $invoice['gateway_details'] : null;
@endphp

<div class="page">
    <div class="top-accent"></div>

    <table class="header-table">
        <tr>
            <td class="header-left">
                <h1 class="company">{{ $invoice['company_name'] ?? 'DigiPro' }}</h1>
                <p class="invoice-title">Invoice Pembayaran</p>
                <div class="subline">Dokumen resmi bukti transaksi layanan penilaian properti</div>
            </td>
            <td class="header-right">
                <span class="status-badge">{{ $invoice['status_label'] ?? 'LUNAS' }}</span>
                <div class="meta-box">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="font-size: 10px; color: #64748b; text-align: left;">No Invoice</td>
                            <td style="font-size: 10px; font-weight: 700; text-align: right;">{{ $invoice['invoice_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="font-size: 10px; color: #64748b; text-align: left;">Tanggal</td>
                            <td style="font-size: 10px; font-weight: 700; text-align: right;">{{ $invoice['issued_at'] ?? '-' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Nomor Request</td>
            <td class="meta-colon">:</td>
            <td>{{ $invoice['request_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Nomor Kontrak</td>
            <td class="meta-colon">:</td>
            <td>{{ $invoice['contract_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Nama Klien</td>
            <td class="meta-colon">:</td>
            <td>{{ $invoice['client_name'] ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">Rincian Tagihan</div>
    <table class="detail-table">
        <thead>
        <tr>
            <th style="width: 40px;">No</th>
            <th>Deskripsi</th>
            <th class="amount-col">Nominal</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>1</td>
            <td>Biaya layanan penilaian properti - {{ $invoice['request_number'] ?? '-' }}</td>
            <td class="amount-col">{{ $idr($invoice['amount'] ?? 0) }}</td>
        </tr>
        <tr class="total-row">
            <td colspan="2" style="text-align: right;">Total Dibayar</td>
            <td class="amount-col">{{ $idr($invoice['amount'] ?? 0) }}</td>
        </tr>
        </tbody>
    </table>

    <div class="section-title">Informasi Pembayaran</div>
    <div class="payment-box">
        <div class="payment-box-title">Detail Transfer</div>
        <table class="payment-table">
            <tr>
                <td class="payment-label">Metode</td>
                <td>: {{ (string) ($invoice['method'] ?? 'Midtrans Snap') }}</td>
            </tr>
            <tr>
                <td class="payment-label">Order ID</td>
                <td>: {{ $invoice['external_payment_id'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="payment-label">Channel</td>
                <td>
                    :
                    @if($gateway)
                        {{ $gateway['label'] ?? '-' }}
                        @if(!empty($gateway['reference']))
                            | {{ $gateway['reference'] }}
                        @endif
                    @elseif($bank)
                        {{ $bank['bank_name'] ?? '-' }} | {{ $bank['account_number'] ?? '-' }} | a.n. {{ $bank['account_holder'] ?? '-' }}
                    @else
                        -
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Invoice ini diterbitkan otomatis oleh sistem {{ $invoice['company_name'] ?? 'DigiPro' }}.
        Simpan dokumen ini sebagai arsip transaksi.
        <div class="signature">Generated at {{ now()->format('Y-m-d H:i:s') }}</div>
    </div>
</div>
</body>
</html>
