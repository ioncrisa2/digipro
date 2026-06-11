<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $doc['title'] ?? 'Penawaran Layanan Estimasi Rentang Harga Properti' }}</title>
    <style>
        @page { margin: 24px 58px 34px; size: A4; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.8px;
            line-height: 1.42;
            color: #111827;
        }
        .page {
            width: 100%;
        }
        .letterhead-table,
        .letter-meta-table,
        .item-table,
        .signature-table,
        .signature-meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .letterhead-table {
            margin-bottom: 18px;
        }
        .letterhead-logo-cell {
            width: 92px;
            vertical-align: top;
        }
        .brand-logo {
            width: 82px;
            height: auto;
            display: block;
        }
        .logo-mark {
            width: 76px;
            height: 76px;
            border: 8px solid #0f2f63;
            background: #0f2f63;
            color: #ffffff;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            line-height: 60px;
            letter-spacing: 0.04em;
        }
        .letterhead-copy {
            vertical-align: top;
            padding-left: 7px;
            color: #020617;
        }
        .office-eyebrow {
            margin: 0;
            font-size: 10px;
            font-weight: 700;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .office-name {
            margin: 1px 0 2px;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .office-lines {
            font-size: 8.8px;
            line-height: 1.28;
        }
        .letter-meta-table {
            margin: 0 0 18px;
        }
        .letter-meta-table td {
            vertical-align: top;
            padding: 0;
        }
        .letter-date {
            text-align: right;
            white-space: nowrap;
        }
        .doc-number,
        .request-id {
            font-size: 10.5px;
        }
        .request-id {
            margin-top: 4px;
            color: #374151;
        }
        .recipient {
            margin: 0 0 18px;
            font-weight: 700;
        }
        .recipient .recipient-line {
            margin: 0 0 2px;
        }
        .recipient .location-line {
            margin-top: 20px;
        }
        .perihal {
            margin: 0 0 18px;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
        }
        .perihal span {
            text-decoration: underline;
        }
        .opening-para,
        .body-copy {
            margin: 0 0 13px;
            text-align: justify;
        }
        .item-table {
            margin: 0 0 12px;
            page-break-inside: avoid;
        }
        .item-table td {
            vertical-align: top;
            padding: 0;
        }
        .item-num {
            width: 34px;
            padding-right: 8px;
            font-weight: 700;
            white-space: nowrap;
        }
        .item-label {
            width: 178px;
            padding-right: 8px;
            font-weight: 700;
        }
        .item-colon {
            width: 16px;
            padding-right: 8px;
            text-align: center;
        }
        .item-content {
            text-align: justify;
        }
        .item-content p {
            margin: 0 0 5px;
        }
        .item-content ul,
        .asset-list {
            margin: 0;
            padding-left: 16px;
        }
        .item-content li,
        .asset-list li {
            margin-bottom: 4px;
        }
        .asset-list {
            padding-left: 24px;
        }
        .asset-title,
        .fee-highlight,
        .strong {
            font-weight: 700;
        }
        .muted {
            color: #4b5563;
        }
        .small-note {
            margin-top: 5px;
            font-size: 9.3px;
            color: #4b5563;
        }
        .signature-table {
            margin-top: 26px;
            page-break-inside: avoid;
        }
        .signature-table td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }
        .signature-left {
            padding-right: 28px !important;
        }
        .signature-right {
            padding-left: 20px !important;
        }
        .signature-heading {
            margin: 0 0 4px;
        }
        .signature-organization {
            margin: 0 0 20px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .signature-space {
            min-height: 76px;
            margin: 0 0 8px;
        }
        .signature-name {
            margin: 0;
            font-weight: 700;
            text-decoration: underline;
        }
        .signature-title {
            margin: 2px 0 0;
        }
        .signature-line-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 82px;
        }
        .signature-line-table td {
            padding: 2px 0;
            vertical-align: bottom;
        }
        .signature-line-label {
            width: 58px;
        }
        .signature-line-colon {
            width: 12px;
            text-align: center;
        }
        .signature-line {
            border-bottom: 1px solid #6b7280;
            height: 17px;
        }
        .signature-meta-table {
            margin-top: 8px;
            font-size: 8.6px;
            line-height: 1.28;
            color: #4b5563;
        }
        .signature-meta-table td {
            padding: 1px 0;
            vertical-align: top;
        }
        .signature-meta-label {
            width: 58px;
        }
        .footer-line {
            margin-top: 16px;
            padding-top: 6px;
            border-top: 1px solid #d1d5db;
            font-size: 8.5px;
            color: #4b5563;
            text-align: justify;
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
    $brandLogoPath = public_path('images/brand/digipro-by-kjpp-hjar-logo-dark.png');
    $hasBrandLogo = is_file($brandLogoPath);
@endphp

<div class="page">
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
                <p class="office-name">{{ $officeName }}</p>
                <div class="office-lines">
                    {{ $officeDivision }}<br>
                    Penawaran melalui platform DigiPro, berbasis dokumen, foto, dan data digital tanpa inspeksi lapangan.<br>
                    Dukungan pelanggan: {{ $supportContact['phone'] ?? '-' }} / {{ $supportContact['whatsapp'] ?? '-' }}<br>
                    Email: {{ $supportContact['email'] ?? '-' }}
                </div>
            </td>
        </tr>
    </table>

    <table class="letter-meta-table">
        <tr>
            <td>
                <div class="doc-number">{{ $doc['agr_no'] ?? '-' }}</div>
                @if (!empty($doc['request_id']))
                    <div class="request-id">ID Permohonan: {{ $doc['request_id'] }}</div>
                @endif
            </td>
            <td class="letter-date">{{ $doc['city_date_line'] ?? ($doc['date_label'] ?? '-') }}</td>
        </tr>
    </table>

    <div class="recipient">
        <div class="recipient-line">Kepada Yth.</div>
        @forelse ($recipientLines as $line)
            <div class="recipient-line">{{ $line }}</div>
        @empty
            <div class="recipient-line">{{ $doc['user_name'] ?? '-' }}</div>
        @endforelse
    </div>

    <div class="perihal">Perihal : <span>{{ $subject }}</span></div>

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

                        @if (!empty($assets))
                            <ol class="asset-list">
                                @foreach ($assets as $index => $asset)
                                    <li>
                                        <span class="asset-title">{{ $asset['label'] ?? 'Aset' }}</span>
                                        yang berlokasi di {{ $asset['address'] ?? '-' }}.
                                        Dokumen utama: {{ $asset['main_documents'] ?? '-' }}.
                                        Basis luas: {{ $asset['area_basis'] ?? '-' }}.
                                        Catatan: {{ $asset['note'] ?? '-' }}.
                                    </li>
                                @endforeach
                            </ol>
                        @else
                            <p>Belum ada data aset.</p>
                        @endif

                        <p class="small-note">
                            Data aset mengikuti dokumen dan input yang diunggah pengguna pada sistem DigiPro by KJPP HJAR.
                        </p>

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

    <table class="signature-table">
        <tr>
            <td class="signature-left">
                <p class="signature-heading">Hormat kami,</p>
                <p class="signature-organization">{{ $officeName }}</p>
                <div class="signature-space"></div>
                <p class="signature-name">{{ $publicAppraiserSignature['name'] ?? ($sender['representative_name'] ?? '-') }}</p>
                <p class="signature-title">{{ $sender['representative_title'] ?? 'Penilai Publik / Penandatangan Atasan' }}</p>
                <p class="signature-title">{{ $officeDivision }}</p>

                <table class="signature-meta-table">
                    <tr>
                        <td class="signature-meta-label">Status</td>
                        <td>: {{ $isPublicAppraiserSigned ? 'Sudah ditandatangani digital' : 'Belum ditandatangani digital' }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Waktu</td>
                        <td>: {{ $publicAppraiserSignature['signed_at'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Email</td>
                        <td>: {{ $publicAppraiserSignature['email'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Signature ID</td>
                        <td>: {{ $publicAppraiserSignature['external_order_id'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Hash</td>
                        <td>: {{ $documentHash ?: '-' }}</td>
                    </tr>
                </table>
            </td>
            <td class="signature-right">
                <p class="signature-heading">Persetujuan Customer,</p>
                <table class="signature-line-table">
                    <tr>
                        <td class="signature-line-label">Nama</td>
                        <td class="signature-line-colon">:</td>
                        <td class="signature-line">{{ $customerSignature['name'] ?? ($signature['signed_by_name'] ?? ($approval['client_name'] ?? ($doc['user_name'] ?? ''))) }}</td>
                    </tr>
                    <tr>
                        <td class="signature-line-label">Jabatan</td>
                        <td class="signature-line-colon">:</td>
                        <td class="signature-line">{{ $approval['client_title'] ?? 'Pemberi Tugas / Pengguna Hasil' }}</td>
                    </tr>
                    <tr>
                        <td class="signature-line-label">Tanggal</td>
                        <td class="signature-line-colon">:</td>
                        <td class="signature-line">{{ $customerSignature['signed_at'] ?? ($signature['signed_at'] ?? ($doc['accepted_at'] ?? '')) }}</td>
                    </tr>
                </table>

                <table class="signature-meta-table">
                    <tr>
                        <td class="signature-meta-label">Status</td>
                        <td>: {{ $isCustomerSigned ? 'Sudah ditandatangani digital' : 'Menunggu persetujuan digital' }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Email</td>
                        <td>: {{ $customerSignature['email'] ?? ($signature['signed_by_email'] ?? ($doc['user_identifier'] ?? '-')) }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Signature ID</td>
                        <td>: {{ $customerSignature['external_order_id'] ?? ($signature['signature_id'] ?? ($doc['consent_id'] ?? '-')) }}</td>
                    </tr>
                    <tr>
                        <td class="signature-meta-label">Hash</td>
                        <td>: {{ $documentHash ?: '-' }}</td>
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
