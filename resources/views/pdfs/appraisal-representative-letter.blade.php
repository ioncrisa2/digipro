<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Representatif</title>
    <style>
        @page { margin: 26px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #111827;
        }
        .eyebrow {
            font-size: 10px;
            color: #475569;
            margin-bottom: 10px;
        }
        .title {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
        }
        .subtitle {
            margin: 4px 0 18px;
            font-size: 11px;
            color: #475569;
            text-align: center;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .meta td {
            padding: 2px 0;
            vertical-align: top;
        }
        .meta .label {
            width: 135px;
            color: #4b5563;
        }
        .section-title {
            margin: 16px 0 8px;
            font-size: 12px;
            font-weight: 700;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .table th,
        .table td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
        }
        .table th {
            background: #f8fafc;
            text-align: left;
        }
        .statement-list {
            margin: 0;
            padding-left: 18px;
        }
        .statement-list li {
            margin-bottom: 7px;
        }
        .signature-box {
            margin-top: 22px;
        }
        .muted {
            color: #6b7280;
            font-size: 10px;
        }
    </style>
</head>
<body>
<div class="eyebrow">DigiPro - Platform Digital Penilaian Properti</div>

<h1 class="title">{{ $doc['title'] ?? 'SURAT REPRESENTATIF' }}</h1>
<p class="subtitle">{{ $doc['subtitle'] ?? '' }}</p>

<table class="meta">
    <tr>
        <td class="label">Nomor Request</td>
        <td>: {{ $doc['request_number'] ?? '-' }}</td>
        <td class="label">Nomor Kontrak</td>
        <td>: {{ $doc['contract_number'] ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Tanggal Dokumen</td>
        <td>: {{ $doc['date'] ?? '-' }}</td>
        <td class="label">Nama Pemohon</td>
        <td>: {{ $doc['requester_name'] ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Email Pemohon</td>
        <td>: {{ $doc['requester_email'] ?? '-' }}</td>
        <td class="label">Nama Klien</td>
        <td>: {{ $doc['client_name'] ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Tujuan Penilaian</td>
        <td colspan="3">: {{ $doc['valuation_objective_label'] ?? '-' }}</td>
    </tr>
</table>

<p>
    Saya yang bertanda tangan secara elektronik melalui platform DigiPro menyatakan bahwa data, informasi,
    dan dokumen yang saya kirimkan untuk permohonan penilaian properti adalah benar dan dapat dipertanggungjawabkan.
    Pernyataan ini dibuat sebagai bagian dari proses digital DigiPro untuk mendukung penyusunan dokumen layanan
    dan keluaran penilaian.
</p>

<div class="section-title">Ringkasan Objek</div>
<table class="table">
    <thead>
    <tr>
        <th style="width: 36px;">No</th>
        <th>Jenis</th>
        <th>Alamat</th>
        <th>Dokumen</th>
        <th>LT</th>
        <th>LB</th>
    </tr>
    </thead>
    <tbody>
    @foreach (($doc['asset_summaries'] ?? []) as $asset)
        <tr>
            <td>{{ $asset['no'] ?? '-' }}</td>
            <td>{{ $asset['type_label'] ?? '-' }}</td>
            <td>{{ $asset['address'] ?? '-' }}</td>
            <td>{{ $asset['title_document'] ?? '-' }}</td>
            <td>{{ $asset['land_area'] ?? '-' }}</td>
            <td>{{ $asset['building_area'] ?? '-' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="section-title">Pernyataan</div>
<ol class="statement-list">
    @foreach (($doc['statement_items'] ?? []) as $item)
        <li>{{ $item }}</li>
    @endforeach
</ol>

<p>
    Demikian surat representatif ini dibuat dan dilekatkan pada proses permohonan penilaian di DigiPro untuk
    digunakan sebagaimana mestinya dalam konteks layanan platform digital.
</p>

<div class="signature-box">
    <p>Hormat saya,</p>
    <p style="margin-top: 28px; font-weight: 700;">{{ $doc['signature']['signed_by_name'] ?? '-' }}</p>
    <p style="margin-top: 4px;">{{ $doc['signature']['signed_by_email'] ?? '-' }}</p>
    <p class="muted" style="margin-top: 8px;">
        Ditandatangani secara elektronik melalui DigiPro pada {{ $doc['signature']['signed_at'] ?? '-' }}<br>
        Signature ID: {{ $doc['signature']['signature_id'] ?? '-' }}<br>
        Hash Referensi: {{ $doc['signature']['document_hash'] ?? '-' }}
    </p>
</div>
</body>
</html>
