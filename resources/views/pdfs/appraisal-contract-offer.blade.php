<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Penawaran Layanan Estimasi Rentang Harga Properti</title>
    <style>
        @page { margin: 24px 22px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            line-height: 1.5;
            color: #111827;
        }
        .page {
            position: relative;
        }
        .top-rule {
            height: 5px;
            background: #0f172a;
            border-bottom: 2px solid #38bdf8;
            margin-bottom: 16px;
        }
        .brand-row {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .brand-mark {
            width: 120px;
            vertical-align: top;
        }
        .brand-pill {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: #0f172a;
            color: #e2e8f0;
            font-size: 9px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }
        .brand-pill strong {
            color: #ffffff;
            font-weight: 700;
        }
        .brand-pill span {
            color: #38bdf8;
        }
        .brand-copy {
            vertical-align: top;
            text-align: right;
        }
        .brand-copy .eyebrow {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: #64748b;
            margin-bottom: 3px;
        }
        .brand-copy .title {
            margin: 0;
            font-size: 18px;
            line-height: 1.2;
            font-weight: 700;
            text-transform: uppercase;
            color: #020617;
        }
        .brand-copy .subtitle {
            margin: 3px 0 0;
            font-size: 10px;
            color: #475569;
        }
        .meta,
        .subject-line,
        .scope-table,
        .summary-table,
        .two-column,
        .closing-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .meta {
            margin-bottom: 12px;
        }
        .meta td {
            vertical-align: top;
            padding: 2px 0;
        }
        .meta .label,
        .subject-line .label {
            width: 110px;
            color: #475569;
        }
        .recipient-block,
        .section-title,
        .footer-note {
            margin-top: 14px;
        }
        .subject-line {
            margin: 0 0 12px;
        }
        .subject-line td {
            vertical-align: top;
            padding: 0;
        }
        .body-copy {
            margin: 0 0 8px;
            text-align: justify;
        }
        .section-title {
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #0f172a;
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
        }
        .scope-table th,
        .scope-table td,
        .summary-table th,
        .summary-table td,
        .two-column td {
            border: 1px solid #cbd5e1;
            padding: 6px 7px;
            vertical-align: top;
        }
        .scope-table th,
        .summary-table th {
            background: #e2e8f0;
            text-align: left;
            color: #0f172a;
        }
        .scope-table .num {
            width: 28px;
            text-align: center;
            font-weight: 700;
        }
        .scope-table .title-col {
            width: 165px;
            font-weight: 700;
        }
        .line-list {
            margin: 0;
            padding-left: 14px;
        }
        .line-list li {
            margin: 0 0 3px;
        }
        .two-column {
            margin-top: 10px;
        }
        .two-column td {
            width: 50%;
            padding: 9px 10px;
        }
        .column-title {
            font-weight: 700;
            margin-bottom: 7px;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.08em;
            color: #334155;
        }
        .muted {
            color: #475569;
        }
        .closing-grid {
            margin-top: 16px;
        }
        .closing-grid td {
            width: 50%;
            vertical-align: top;
            padding-right: 14px;
        }
        .signature-box {
            border: 1px dashed #94a3b8;
            min-height: 58px;
            margin: 10px 0 8px;
        }
        .signature-meta {
            font-size: 9px;
            color: #475569;
            line-height: 1.45;
        }
        .sign-space {
            height: 60px;
        }
        .footer-note {
            font-size: 9px;
            color: #475569;
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
        }
    </style>
</head>
<body>
@php
    $idr = fn ($value) => 'Rp ' . number_format((int) ($value ?? 0), 0, ',', '.');
    $assets = is_array($doc['assets'] ?? null) ? $doc['assets'] : [];
    $scopeItems = is_array($doc['scope_items'] ?? null) ? $doc['scope_items'] : [];
    $included = is_array($doc['included_scope'] ?? null) ? $doc['included_scope'] : [];
    $excluded = is_array($doc['excluded_scope'] ?? null) ? $doc['excluded_scope'] : [];
    $spiReferences = is_array($doc['spi_references'] ?? null) ? $doc['spi_references'] : [];
    $recipientLines = is_array($doc['recipient_lines'] ?? null) ? $doc['recipient_lines'] : [];
    $openingParagraphs = is_array($doc['opening_paragraphs'] ?? null) ? $doc['opening_paragraphs'] : [];
    $supportContact = is_array($doc['support_contact'] ?? null) ? $doc['support_contact'] : [];
    $sender = is_array($doc['sender'] ?? null) ? $doc['sender'] : [];
    $approval = is_array($doc['approval'] ?? null) ? $doc['approval'] : [];
    $signature = is_array($doc['signature'] ?? null) ? $doc['signature'] : [];
    $isSigned = (bool) ($signature['is_signed'] ?? false);
@endphp

<div class="page">
    <div class="top-rule"></div>

    <table class="brand-row">
        <tr>
            <td class="brand-mark">
                <div class="brand-pill"><strong>DIGI<span>PRO</span></strong></div>
            </td>
            <td class="brand-copy">
                <div class="eyebrow">Dokumen Penawaran</div>
                <h1 class="title">{{ $doc['title'] ?? 'PENAWARAN LAYANAN ESTIMASI RENTANG HARGA PROPERTI' }}</h1>
                <p class="subtitle">{{ $doc['subtitle'] ?? '(Tanpa Inspeksi Lapangan - Non-Reliance)' }}</p>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="label">Nomor</td>
            <td>: {{ $doc['agr_no'] ?? '-' }}</td>
            <td class="label">Tanggal</td>
            <td>: {{ $doc['date_label'] ?? ($doc['date'] ?? '-') }}</td>
        </tr>
        <tr>
            <td class="label">ID Permohonan</td>
            <td>: {{ $doc['request_id'] ?? '-' }}</td>
            <td class="label">Tujuan Kajian</td>
            <td>: {{ $doc['valuation_objective_label'] ?? '-' }}</td>
        </tr>
    </table>

    <div class="recipient-block">
        <div>{{ $doc['city_date_line'] ?? '-' }}</div>
        <div style="margin-top: 8px;">Kepada Yth.</div>
        @forelse ($recipientLines as $line)
            <div>{{ $line }}</div>
        @empty
            <div>{{ $doc['user_name'] ?? '-' }}</div>
        @endforelse
    </div>

    <table class="subject-line">
        <tr>
            <td class="label">Perihal</td>
            <td>: {{ $doc['subject'] ?? '-' }}</td>
        </tr>
    </table>

    @if (!empty($doc['request_reference']))
        <p class="body-copy">{{ $doc['request_reference'] }}</p>
    @endif

    @foreach ($openingParagraphs as $paragraph)
        <p class="body-copy">{{ $paragraph }}</p>
    @endforeach

    <div class="section-title">Ringkasan Aset</div>
    <table class="summary-table">
        <thead>
        <tr>
            <th style="width: 28px;">No</th>
            <th>Label Aset</th>
            <th>Lokasi Singkat</th>
            <th>Dokumen Utama</th>
            <th>Luas (Basis)</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($assets as $asset)
            <tr>
                <td>{{ $asset['no'] ?? '-' }}</td>
                <td>{{ $asset['label'] ?? '-' }}</td>
                <td>{{ $asset['address'] ?? '-' }}</td>
                <td>{{ $asset['main_documents'] ?? '-' }}</td>
                <td>{{ $asset['area_basis'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5">Belum ada data aset.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="section-title">Lingkup Penugasan</div>
    <table class="scope-table">
        <thead>
        <tr>
            <th class="num">No</th>
            <th class="title-col">Uraian</th>
            <th>Penjelasan</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($scopeItems as $item)
            <tr>
                <td class="num">{{ $item['no'] ?? '-' }}</td>
                <td class="title-col">{{ $item['title'] ?? '-' }}</td>
                <td>
                    @php $lines = is_array($item['lines'] ?? null) ? $item['lines'] : []; @endphp
                    @if (count($lines) <= 1)
                        {{ $lines[0] ?? '-' }}
                    @else
                        <ul class="line-list">
                            @foreach ($lines as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="two-column">
        <tr>
            <td>
                <div class="column-title">Termasuk dalam Layanan</div>
                <ul class="line-list">
                    @foreach ($included as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </td>
            <td>
                <div class="column-title">Tidak Termasuk dalam Layanan</div>
                <ul class="line-list">
                    @foreach ($excluded as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </td>
        </tr>
    </table>

    <div class="section-title">Rujukan SPI dan Catatan Penting</div>
    <ul class="line-list">
        @foreach ($spiReferences as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>

    <div class="section-title">Ringkasan Komersial</div>
    <table class="summary-table">
        <tr>
            <th>Komponen</th>
            <th>Nilai</th>
        </tr>
        <tr>
            <td>Biaya layanan per aset</td>
            <td>{{ $idr($doc['fee_per_asset'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Jumlah aset</td>
            <td>{{ $doc['asset_count'] ?? 0 }}</td>
        </tr>
        <tr>
            <td>Total biaya</td>
            <td>{{ $idr($doc['total_fee'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Pajak</td>
            <td>{{ $doc['tax_note'] ?? '-' }}</td>
        </tr>
        <tr>
            <td>Metode pembayaran</td>
            <td>{{ $doc['payment_methods'] ?? '-' }}</td>
        </tr>
        <tr>
            <td>SLA</td>
            <td>{{ $doc['sla_text'] ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">Pernyataan Kunci</div>
    <p class="body-copy">{{ $doc['statement_text'] ?? '-' }}</p>

    <div class="section-title">Persetujuan</div>
    <p class="body-copy muted">
        Dengan menyetujui surat ini, pemberi tugas menyatakan memahami bahwa layanan DigiPro by KJPP HJAR dilaksanakan tanpa inspeksi lapangan
        dan hasilnya hanya berupa kajian nilai pasar dalam bentuk range.
    </p>

    <table class="closing-grid">
        <tr>
            <td>
                <div>Hormat kami,</div>
                <div style="font-weight:700; margin-top:4px;">{{ $sender['organization'] ?? 'DigiPro by KJPP HJAR' }}</div>
                <div class="muted">{{ $sender['division'] ?? '-' }}</div>
                <div class="sign-space"></div>
                <div style="font-weight:700;">{{ $sender['representative_name'] ?? '-' }}</div>
                <div class="muted">{{ $sender['representative_title'] ?? '-' }}</div>
                <div class="signature-meta" style="margin-top:8px;">
                    Kontak: {{ $supportContact['phone'] ?? '-' }} / {{ $supportContact['whatsapp'] ?? '-' }}<br>
                    Email: {{ $supportContact['email'] ?? '-' }}<br>
                    Jam layanan: {{ $supportContact['availability_label'] ?? '-' }}
                </div>
            </td>
            <td>
                <div>Persetujuan Pemberi Tugas,</div>
                @if ($isSigned)
                    <div class="signature-box"></div>
                    <div style="font-weight:700;">{{ $signature['signed_by_name'] ?? ($approval['client_name'] ?? '-') }}</div>
                    <div class="muted">{{ $approval['client_title'] ?? 'Pemberi Tugas / Pengguna Hasil' }}</div>
                    <div class="signature-meta" style="margin-top:8px;">
                        Ditandatangani digital pada {{ $signature['signed_at'] ?? '-' }}<br>
                        Email: {{ $signature['signed_by_email'] ?? '-' }}<br>
                        Signature ID: {{ $signature['signature_id'] ?? '-' }}<br>
                        Hash: {{ $signature['document_hash'] ?? '-' }}
                    </div>
                @else
                    <div class="signature-box"></div>
                    <div style="font-weight:700;">{{ $approval['client_name'] ?? ($doc['user_name'] ?? '-') }}</div>
                    <div class="muted">{{ $approval['client_title'] ?? 'Pemberi Tugas / Pengguna Hasil' }}</div>
                    <div class="signature-meta" style="margin-top:8px;">
                        Tanggal persetujuan: {{ $doc['accepted_at'] ?? '-' }}<br>
                        ID/Email: {{ $doc['user_identifier'] ?? '-' }}<br>
                        Consent ID: {{ $doc['consent_id'] ?? '-' }}
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <div class="footer-note">{{ $doc['disclaimer_footer'] ?? '' }}</div>
</div>
</body>
</html>
