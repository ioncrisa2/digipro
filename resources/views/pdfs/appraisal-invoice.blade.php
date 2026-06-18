<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice['invoice_number'] ?? 'Invoice Pembayaran' }}</title>
    <style>
        @page { margin: 22px 54px 30px; size: A4; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.4px;
            line-height: 1.24;
            color: #111827;
        }
        .page {
            width: 100%;
        }
        .top-band-table,
        .letterhead-table,
        .invoice-meta-table,
        .invoice-table,
        .signature-table,
        .acceptance-table {
            width: 100%;
            border-collapse: collapse;
        }
        .top-band-table {
            margin-bottom: 0;
        }
        .top-band {
            height: 16px;
            background: #44205f;
            color: #ffffff;
            font-family: DejaVu Serif, serif;
            font-size: 18px;
            line-height: 16px;
            text-align: right;
            letter-spacing: 0;
        }
        .letterhead-table {
            margin-bottom: 8px;
        }
        .letterhead-logo-cell {
            width: 78px;
            vertical-align: top;
            border-left: 2px solid #1d3557;
            border-bottom: 2px solid #1d3557;
        }
        .brand-logo {
            width: 70px;
            height: auto;
            display: block;
            margin-top: 2px;
        }
        .logo-mark {
            width: 68px;
            height: 68px;
            background: #1d3557;
            color: #ffffff;
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            line-height: 68px;
        }
        .letterhead-copy {
            vertical-align: top;
            padding: 2px 0 0 6px;
        }
        .office-eyebrow {
            margin: 0;
            font-size: 8.2px;
            font-weight: 700;
            line-height: 1.02;
            text-transform: uppercase;
        }
        .office-name {
            margin: 0;
            font-size: 10.2px;
            font-weight: 700;
            line-height: 1.1;
            text-transform: uppercase;
        }
        .office-lines {
            margin-top: 1px;
            font-size: 6.8px;
            font-weight: 700;
            line-height: 1.2;
        }
        .digipro-line {
            margin-top: 2px;
            color: #0f5c88;
            font-size: 7.2px;
            font-weight: 700;
        }
        .recipient {
            margin: 4px 0 8px;
            font-size: 11.4px;
            font-weight: 700;
        }
        .recipient .address {
            margin-top: 5px;
            font-size: 8.8px;
            font-weight: 400;
            line-height: 1.45;
        }
        .invoice-meta-table {
            margin-bottom: 3px;
        }
        .invoice-meta-table td {
            padding: 0 0 2px;
            vertical-align: top;
        }
        .meta-label {
            width: 64%;
            text-align: right;
            padding-right: 8px !important;
        }
        .meta-value {
            width: 36%;
            border-left: 1px solid #111827;
            padding-left: 8px !important;
            font-weight: 400;
        }
        .status-line {
            margin: 0 0 3px;
            text-align: right;
            color: #0f5c88;
            font-size: 7.8px;
            font-weight: 700;
        }
        .invoice-table {
            border: 1.5px solid #111827;
            margin-top: 2px;
        }
        .invoice-table th,
        .invoice-table td {
            border: 1px solid #111827;
            padding: 2px 4px;
            vertical-align: top;
        }
        .invoice-table th {
            background: #f5c28e;
            font-size: 8.6px;
            line-height: 1.05;
            text-align: center;
            font-weight: 700;
        }
        .no-col {
            width: 48px;
            text-align: center;
        }
        .description-col {
            width: auto;
        }
        .unit-col {
            width: 124px;
        }
        .amount-col {
            width: 126px;
        }
        .item-no {
            text-align: center;
            vertical-align: middle !important;
            font-size: 12px;
            padding-top: 22px !important;
        }
        .desc-title {
            font-weight: 700;
        }
        .desc-line {
            margin-top: 2px;
        }
        .muted {
            color: #4b5563;
        }
        .money-label {
            width: 26px;
            display: inline-block;
            font-weight: 700;
        }
        .money-value {
            float: right;
            font-weight: 700;
        }
        .money-cell {
            text-align: right;
            white-space: nowrap;
        }
        .total-label {
            font-weight: 700;
        }
        .total-amount {
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }
        .pay-line {
            margin: 1px 0 2px;
            padding-left: 58px;
        }
        .amount-words {
            margin: 0 0 8px;
            text-align: center;
            font-size: 8.8px;
            font-weight: 700;
        }
        .signing-organization {
            margin: 8px 0 22px 64px;
            font-size: 9.8px;
            font-weight: 700;
        }
        .signature-table {
            margin-top: 0;
        }
        .signature-table td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }
        .signature-left {
            padding-left: 64px !important;
            padding-right: 16px !important;
        }
        .signature-right {
            padding-left: 16px !important;
        }
        .signature-name {
            margin: 0;
            font-size: 8.8px;
            font-weight: 700;
            text-decoration: underline;
        }
        .signature-title {
            margin: 1px 0 0;
            font-size: 8.2px;
            font-style: italic;
        }
        .signature-detail {
            margin: 2px 0 0;
            font-size: 8.2px;
            font-style: italic;
            line-height: 1.28;
        }
        .digipro-note {
            margin: 0;
            padding-top: 4px;
            color: #334155;
            font-size: 7.4px;
            line-height: 1.28;
        }
        .digipro-note strong {
            color: #0f5c88;
        }
        .acceptance-table {
            margin-top: 8px;
        }
        .acceptance-table td {
            vertical-align: top;
        }
        .acceptance-cell {
            width: 134px;
            height: 70px;
            border: 1px solid #111827;
            margin-left: auto;
            text-align: center;
        }
        .acceptance-title {
            padding: 3px 0;
            border-bottom: 1px solid #111827;
            font-size: 8px;
            font-weight: 700;
        }
        .acceptance-subtitle {
            padding: 3px 0;
            border-bottom: 1px solid #111827;
            background: #d9d9d9;
            font-size: 7px;
            font-style: italic;
            font-weight: 700;
        }
    </style>
</head>
<body>
@php
    $billing = is_array($invoice['billing_summary'] ?? null) ? $invoice['billing_summary'] : [];
    $gateway = is_array($invoice['gateway_details'] ?? null) ? $invoice['gateway_details'] : null;
    $brandLogoPath = public_path('images/brand/digipro-by-kjpp-hjar-logo-dark.png');
    $hasBrandLogo = is_file($brandLogoPath);

    $idrNumber = fn ($value) => number_format((int) ($value ?? 0), 0, ',', '.');
    $idr = fn ($value) => 'Rp ' . $idrNumber($value);

    $billingName = trim((string) ($billing['nama_tagihan'] ?? ''));
    $clientName = $billingName !== '' ? $billingName : (string) ($invoice['client_name'] ?? '-');
    $billingAddress = trim((string) ($billing['alamat_tagihan'] ?? ''));
    $invoiceNumber = (string) ($invoice['invoice_number'] ?? '-');
    $invoiceDate = (string) (($billing['tanggal_invoice'] ?? null) ?: ($invoice['issued_at'] ?? '-'));
    $requestNumber = (string) ($invoice['request_number'] ?? '-');
    $contractNumber = (string) ($invoice['contract_number'] ?? '-');
    $dpp = (int) ($billing['nilai_jasa_dpp'] ?? 0);
    $ppnRate = (float) ($billing['tarif_ppn_persen'] ?? 11);
    $ppn = (int) ($billing['nilai_ppn'] ?? 0);
    $total = (int) ($billing['total_tagihan'] ?? ($invoice['amount'] ?? 0));
    $pphLabel = (string) ($billing['jenis_pph_dipotong_label'] ?? 'PPh 23');
    $pph = (int) ($billing['nilai_pph_dipotong'] ?? 0);
    $net = (int) ($billing['total_transfer_customer'] ?? ($invoice['amount'] ?? $total));

    $terbilang = function (int $value) use (&$terbilang): string {
        $value = abs($value);
        $words = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];

        if ($value < 12) {
            return $words[$value];
        }
        if ($value < 20) {
            return trim($terbilang($value - 10) . ' Belas');
        }
        if ($value < 100) {
            return trim($terbilang(intdiv($value, 10)) . ' Puluh ' . $terbilang($value % 10));
        }
        if ($value < 200) {
            return trim('Seratus ' . $terbilang($value - 100));
        }
        if ($value < 1000) {
            return trim($terbilang(intdiv($value, 100)) . ' Ratus ' . $terbilang($value % 100));
        }
        if ($value < 2000) {
            return trim('Seribu ' . $terbilang($value - 1000));
        }
        if ($value < 1000000) {
            return trim($terbilang(intdiv($value, 1000)) . ' Ribu ' . $terbilang($value % 1000));
        }
        if ($value < 1000000000) {
            return trim($terbilang(intdiv($value, 1000000)) . ' Juta ' . $terbilang($value % 1000000));
        }

        return trim($terbilang(intdiv($value, 1000000000)) . ' Miliar ' . $terbilang($value % 1000000000));
    };

    $amountWords = trim($terbilang($total)) . ' Rupiah';
@endphp

<div class="page">
    <table class="top-band-table">
        <tr>
            <td class="top-band">INVOICE</td>
        </tr>
    </table>

    <table class="letterhead-table">
        <tr>
            <td class="letterhead-logo-cell">
                @if ($hasBrandLogo)
                    <img src="{{ $brandLogoPath }}" class="brand-logo" alt="DigiPro by KJPP HJAR">
                @else
                    <div class="logo-mark">DP</div>
                @endif
            </td>
            <td class="letterhead-copy">
                <p class="office-eyebrow">Kantor Jasa Penilai Publik</p>
                <p class="office-name">Henricus Judi Adrianto dan Rekan</p>
                <div class="office-lines">
                    Bidang Jasa Penilai Properti.<br>
                    KMK No. 17/KM.1/2014, Tgl. 16 Januari 2014<br>
                    Kantor Pusat: Ruko Terminal Sako No.18, Palembang<br>
                    Telp. 0711 5615793, Email: henricusja@yahoo.com<br>
                    Wilayah Kerja Seluruh Indonesia
                </div>
                <div class="digipro-line">DigiPro by KJPP HJAR - invoice digital layanan penilaian properti</div>
            </td>
        </tr>
    </table>

    <div class="recipient">
        To : {{ $clientName }}
        @if ($billingAddress !== '')
            <div class="address">Di-<br>{{ $billingAddress }}</div>
        @endif
    </div>

    <table class="invoice-meta-table">
        <tr>
            <td class="meta-label">INVOICE NUMBER</td>
            <td class="meta-value">{{ $invoiceNumber }}</td>
        </tr>
        <tr>
            <td class="meta-label">INVOICE DATE</td>
            <td class="meta-value">{{ $invoiceDate }}</td>
        </tr>
    </table>

    <p class="status-line">Status: {{ $invoice['status_label'] ?? 'LUNAS' }}</p>

    <table class="invoice-table">
        <thead>
        <tr>
            <th class="no-col">NO</th>
            <th class="description-col">DESCRIPTION</th>
            <th class="unit-col">UNIT PRICE</th>
            <th class="amount-col">AMOUNT</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td class="item-no" rowspan="5">1</td>
            <td>
                <div class="desc-title">Fee pekerjaan Penilaian atas nama :</div>
                <div class="desc-title">{{ $clientName }}</div>
                <div class="desc-line">Layanan penilaian properti melalui DigiPro.</div>
                <div class="desc-line muted">Request: {{ $requestNumber }} | Kontrak: {{ $contractNumber }}</div>
            </td>
            <td class="money-cell">{{ $idr($total) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="desc-title">DPP</td>
            <td class="money-cell"><span class="money-label">Rp</span> {{ $idrNumber($dpp) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="desc-title">PPN {{ rtrim(rtrim(number_format($ppnRate, 2, ',', '.'), '0'), ',') }}%</td>
            <td class="money-cell"><span class="money-label">Rp</span> {{ $idrNumber($ppn) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="total-label">TOTAL PEMBAYARAN</td>
            <td></td>
            <td class="total-amount"><span class="money-label">Rp</span> {{ $idrNumber($total) }}</td>
        </tr>
        <tr>
            <td class="muted">Dipotong {{ $pphLabel }}: {{ $idr($pph) }}. Total transfer customer: {{ $idr($net) }}.</td>
            <td colspan="2" class="muted">Metode: {{ (string) ($invoice['method'] ?? 'Midtrans Snap') }}</td>
        </tr>
        </tbody>
    </table>

    <p class="pay-line">Pay this amount</p>
    <p class="amount-words">{{ $amountWords }}</p>

    <div class="signing-organization">KJPP Henricus Judi Adrianto</div>

    <table class="signature-table">
        <tr>
            <td class="signature-left">
                <p class="signature-name">Ir. Marleen E. Dien</p>
                <p class="signature-title">Bag. Keuangan</p>
            </td>
            <td class="signature-right">
                <p class="signature-name">Dr(ek). Dr(hk). Dr(min). Henricus Judi Adrianto, S.E.,<br>M.Ec.Dev., M.H., M.M, MCIArb., CIB., MAPPI (Cert)</p>
                <p class="signature-title">Pimpinan</p>
                <p class="signature-detail">Penilai Properti Izin Men Keu No. : P-1.13.000380<br>MAPPI No : 96-S-0827</p>
            </td>
        </tr>
    </table>

    <table class="acceptance-table">
        <tr>
            <td style="width: 70%; padding-left: 64px; padding-right: 18px;">
                <div class="digipro-note">
                    <strong>Catatan DigiPro:</strong>
                    Invoice ini diterbitkan otomatis oleh sistem {{ $invoice['company_name'] ?? 'DigiPro by KJPP HJAR' }}.
                    Order ID: {{ $invoice['external_payment_id'] ?? '-' }}.
                    @if ($gateway)
                        Channel: {{ $gateway['label'] ?? '-' }}@if (!empty($gateway['reference'])) | {{ $gateway['reference'] }}@endif.
                    @endif
                    Simpan dokumen ini sebagai arsip transaksi.
                </div>
            </td>
            <td>
                <div class="acceptance-cell">
                    <div class="acceptance-title">Penerima</div>
                    <div class="acceptance-subtitle">Ttd dan Stempel Perusahaan</div>
                </div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
