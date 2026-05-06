<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $doc['title'] ?? 'Penawaran Layanan Estimasi Rentang Harga Properti' }}</title>
    <style>
        @page { margin: 20px 22px; size: A4; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.45;
            color: #111827;
        }
        .page {
            padding: 16px 18px 20px;
        }
        .header-table,
        .item-table,
        .asset-table,
        .signing-section,
        .signature-meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table {
            margin-bottom: 12px;
        }
        .header-logo-cell {
            width: 92px;
            vertical-align: top;
        }
        .logo-box {
            width: 72px;
            height: 72px;
            border: 3px solid #0f172a;
            color: #0f172a;
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            line-height: 66px;
            letter-spacing: 0.04em;
        }
        .header-info-cell {
            vertical-align: top;
            padding-left: 8px;
        }
        .office-name {
            margin: 0;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.35;
            text-transform: uppercase;
            color: #020617;
        }
        .office-sub {
            margin-top: 4px;
            font-size: 9px;
            line-height: 1.5;
            color: #334155;
        }
        .header-divider {
            border: none;
            border-top: 2px solid #0f172a;
            margin: 8px 0 10px;
        }
        .letter-heading {
            margin-bottom: 8px;
        }
        .letter-heading-left {
            float: left;
            max-width: 58%;
        }
        .letter-heading-right {
            float: right;
            max-width: 38%;
            text-align: right;
        }
        .clearfix::after {
            content: '';
            display: block;
            clear: both;
        }
        .doc-number,
        .city-date {
            font-size: 10px;
        }
        .recipient {
            margin: 10px 0 8px;
        }
        .recipient strong {
            font-weight: 700;
        }
        .perihal {
            margin: 8px 0 10px;
            text-align: center;
            font-size: 10.5px;
            font-weight: 700;
            text-decoration: underline;
            color: #0f172a;
        }
        .opening-para,
        .body-copy,
        .footer-line {
            text-align: justify;
        }
        .opening-para,
        .body-copy {
            margin: 6px 0 10px;
        }
        .item-table {
            margin-bottom: 6px;
        }
        .item-table td {
            vertical-align: top;
            padding: 2px 0;
        }
        .item-num {
            width: 28px;
            font-weight: 700;
            color: #0f172a;
        }
        .item-label {
            width: 168px;
            padding-right: 8px;
            font-weight: 700;
            color: #0f172a;
        }
        .item-colon {
            width: 12px;
        }
        .item-content {
            text-align: justify;
        }
        .item-content p {
            margin: 0 0 4px;
        }
        .item-content ul {
            margin: 2px 0;
            padding-left: 16px;
        }
        .item-content li {
            margin-bottom: 2px;
        }
        .bold { font-weight: 700; }
        .underline { text-decoration: underline; }
        .italic { font-style: italic; }
        .muted {
            color: #475569;
        }
        .asset-table {
            margin-top: 5px;
            font-size: 9.2px;
        }
        .asset-table th,
        .asset-table td {
            border: 1px solid #94a3b8;
            padding: 4px 5px;
            vertical-align: top;
        }
        .asset-table th {
            background: #e2e8f0;
            text-align: center;
            font-weight: 700;
            color: #0f172a;
        }
        .asset-note {
            margin-top: 4px;
            font-size: 8.8px;
            color: #475569;
        }
        .signing-section {
            margin-top: 20px;
        }
        .signing-section td {
            width: 50%;
            vertical-align: top;
            padding-right: 12px;
        }
        .sign-label {
            margin-bottom: 4px;
            font-weight: 700;
            color: #0f172a;
        }
        .sign-box {
            height: 68px;
            margin: 6px 0 5px;
            border: 1px solid #94a3b8;
        }
        .sign-name {
            font-size: 10px;
            font-weight: 700;
            color: #0f172a;
        }
        .sign-title {
            font-size: 9px;
            color: #475569;
            line-height: 1.45;
        }
        .signature-meta-table {
            margin-top: 6px;
            font-size: 9px;
        }
        .signature-meta-table td {
            padding: 2px 0;
            vertical-align: top;
        }
        .signature-meta-label {
            width: 74px;
            color: #475569;
        }
        .footer-line {
            margin-top: 14px;
            padding-top: 6px;
            border-top: 1px solid #cbd5e1;
            font-size: 8.5px;
            color: #475569;
        }
        .fee-highlight {
            font-weight: 700;
            color: #0f172a;
        }
    </style>
</head>
<body>
@php
    $assets = is_array($doc['assets'] ?? null) ? $doc['assets'] : [];
    $recipientLines = is_array($doc['recipient_lines'] ?? null) ? $doc['recipient_lines'] : [];
    $openingParagraphs = is_array($doc['opening_paragraphs'] ?? null) ? $doc['opening_paragraphs'] : [];
    $scopeItems = is_array($doc['scope_items'] ?? null) ? $doc['scope_items'] : [];
    $supportContact = is_array($doc['support_contact'] ?? null) ? $doc['support_contact'] : [];
    $sender = is_array($doc['sender'] ?? null) ? $doc['sender'] : [];
    $approval = is_array($doc['approval'] ?? null) ? $doc['approval'] : [];
    $signature = is_array($doc['signature'] ?? null) ? $doc['signature'] : [];
    $signatures = is_array($doc['signatures'] ?? null) ? $doc['signatures'] : [];
    $customerSignature = is_array($signatures['customer'] ?? null) ? $signatures['customer'] : [];
    $publicAppraiserSignature = is_array($signatures['public_appraiser'] ?? null) ? $signatures['public_appraiser'] : [];

    $isCustomerSigned = ($customerSignature['status'] ?? null) === 'signed'
        || (bool) ($signature['is_signed'] ?? false);
    $isPublicAppraiserSigned = ($publicAppraiserSignature['status'] ?? null) === 'signed';
    $documentHash = (string) data_get($doc, 'envelope.document_hash', $signature['document_hash'] ?? '-');
    $requestReference = trim((string) ($doc['request_reference'] ?? ''));
    $subject = trim((string) ($doc['subject'] ?? 'Lingkup Penugasan Jasa Penilaian Properti'));
    $officeName = (string) ($sender['organization'] ?? 'DigiPro by KJPP HJAR');
    $officeDivision = (string) ($sender['division'] ?? 'Layanan Kajian Nilai Pasar Properti Digital');
@endphp

<div class="page">
    <table class="header-table">
        <tr>
            <td class="header-logo-cell">
                <div class="logo-box">DP</div>
            </td>
            <td class="header-info-cell">
                <p class="office-name">{{ $officeName }}</p>
                <div class="office-sub">
                    {{ $officeDivision }}<br>
                    Penugasan berbasis dokumen, foto, dan data digital tanpa inspeksi lapangan.<br>
                    Dukungan pelanggan: {{ $supportContact['phone'] ?? '-' }} / {{ $supportContact['whatsapp'] ?? '-' }}<br>
                    Email: {{ $supportContact['email'] ?? '-' }}
                </div>
            </td>
        </tr>
    </table>
    <hr class="header-divider">

    <div class="letter-heading clearfix">
        <div class="letter-heading-left">
            <div class="doc-number">{{ $doc['agr_no'] ?? '-' }}</div>
            @if (!empty($doc['request_id']))
                <div class="doc-number">ID Permohonan: {{ $doc['request_id'] }}</div>
            @endif
        </div>
        <div class="letter-heading-right">
            <div class="city-date">{{ $doc['city_date_line'] ?? ($doc['date_label'] ?? '-') }}</div>
        </div>
    </div>

    <div class="recipient">
        <strong>Kepada Yth.</strong><br>
        @forelse ($recipientLines as $line)
            {{ $line }}<br>
        @empty
            {{ $doc['user_name'] ?? '-' }}<br>
        @endforelse
    </div>

    <div class="perihal">Perihal : {{ $subject }}</div>

    @if ($requestReference !== '')
        <p class="opening-para">{{ $requestReference }}</p>
    @endif

    @foreach ($openingParagraphs as $paragraph)
        <p class="body-copy">{{ $paragraph }}</p>
    @endforeach

    @foreach ($scopeItems as $item)
        @php
            $title = trim((string) ($item['title'] ?? '-'));
            $lines = is_array($item['lines'] ?? null) ? $item['lines'] : [];
            $isAssetSection = (int) ($item['no'] ?? 0) === 4;
            $isFeeSection = (int) ($item['no'] ?? 0) === 18;
        @endphp
        <table class="item-table">
            <tr>
                <td class="item-num">{{ $item['no'] ?? '-' }}.</td>
                <td class="item-label">{{ $title }}</td>
                <td class="item-colon">:</td>
                <td class="item-content">
                    @if ($isAssetSection)
                        @if (!empty($lines))
                            <p>{{ $lines[0] }}</p>
                        @endif

                        <table class="asset-table">
                            <thead>
                                <tr>
                                    <th style="width: 24px;">No</th>
                                    <th>Jenis Aset</th>
                                    <th>Lokasi</th>
                                    <th>Dokumen Utama</th>
                                    <th>Luas (Basis)</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($assets as $index => $asset)
                                    <tr>
                                        <td style="text-align: center;">{{ $asset['no'] ?? ($index + 1) }}</td>
                                        <td>{{ $asset['label'] ?? '-' }}</td>
                                        <td>{{ $asset['address'] ?? '-' }}</td>
                                        <td>{{ $asset['main_documents'] ?? '-' }}</td>
                                        <td>{{ $asset['area_basis'] ?? '-' }}</td>
                                        <td>{{ $asset['note'] ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #64748b;">Belum ada data aset.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <div class="asset-note">
                            Data aset mengikuti dokumen dan input yang diunggah pengguna pada sistem DigiPro by KJPP HJAR.
                        </div>

                        @foreach (array_slice($lines, 1) as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    @elseif (count($lines) <= 1)
                        @if (!empty($lines))
                            <p class="{{ $isFeeSection ? 'fee-highlight' : '' }}">{{ $lines[0] }}</p>
                        @else
                            <p>-</p>
                        @endif
                    @else
                        <ul>
                            @foreach ($lines as $line)
                                <li class="{{ $isFeeSection ? 'fee-highlight' : '' }}">{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                </td>
            </tr>
        </table>
    @endforeach

    <table class="signing-section">
        <tr>
            <td>
                <div>Hormat kami,</div>
                <div class="sign-label">{{ $officeName }}</div>
                <div class="sign-box"></div>
                <div class="sign-name">{{ $publicAppraiserSignature['name'] ?? ($sender['representative_name'] ?? '-') }}</div>
                <div class="sign-title">{{ $sender['representative_title'] ?? 'Perwakilan DigiPro by KJPP HJAR' }}</div>
                <div class="sign-title">{{ $officeDivision }}</div>

                <table class="signature-meta-table">
                    <tr>
                        <td class="signature-meta-label">Status</td>
                        <td>{{ $isPublicAppraiserSigned ? 'Sudah ditandatangani digital' : 'Belum ditandatangani digital' }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Waktu</td>
                        <td>{{ $publicAppraiserSignature['signed_at'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Email</td>
                        <td>{{ $publicAppraiserSignature['email'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Signature ID</td>
                        <td>{{ $publicAppraiserSignature['external_order_id'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Hash</td>
                        <td>{{ $documentHash ?: '-' }}</td>
                    </tr>
                </table>
            </td>
            <td>
                <div>Persetujuan Pemberi Tugas,</div>
                <div class="sign-box"></div>
                <div class="sign-name">
                    {{ $customerSignature['name'] ?? ($signature['signed_by_name'] ?? ($approval['client_name'] ?? ($doc['user_name'] ?? '-'))) }}
                </div>
                <div class="sign-title">{{ $approval['client_title'] ?? 'Pemberi Tugas / Pengguna Hasil' }}</div>

                <table class="signature-meta-table">
                    <tr>
                        <td class="signature-meta-label">Status</td>
                        <td>{{ $isCustomerSigned ? 'Sudah ditandatangani digital' : 'Menunggu persetujuan digital' }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Waktu</td>
                        <td>{{ $customerSignature['signed_at'] ?? ($signature['signed_at'] ?? ($doc['accepted_at'] ?? '-')) }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Email</td>
                        <td>{{ $customerSignature['email'] ?? ($signature['signed_by_email'] ?? ($doc['user_identifier'] ?? '-')) }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Signature ID</td>
                        <td>{{ $customerSignature['external_order_id'] ?? ($signature['signature_id'] ?? ($doc['consent_id'] ?? '-')) }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Hash</td>
                        <td>{{ $documentHash ?: '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if (!empty($doc['disclaimer_footer']))
        <div class="footer-line">{{ $doc['disclaimer_footer'] }}</div>
    @endif
</div>
</body>
</html>
