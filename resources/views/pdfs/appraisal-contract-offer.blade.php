<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Penawaran Layanan Estimasi Rentang Harga Properti</title>
    <style>
        @page { margin: 24px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #111827;
        }
        .header {
            font-size: 11px;
            color: #374151;
            margin-bottom: 12px;
        }
        .title {
            text-align: center;
            margin: 0 0 2px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .subtitle {
            text-align: center;
            margin: 0 0 14px;
            font-size: 11px;
            color: #4b5563;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .meta td {
            vertical-align: top;
            padding: 2px 0;
        }
        .meta .label {
            width: 95px;
            color: #4b5563;
        }
        .section-title {
            margin: 14px 0 6px;
            font-weight: 700;
            font-size: 12px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th,
        .table td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
        }
        .table th {
            background: #f3f4f6;
            text-align: left;
        }
        .small {
            font-size: 10px;
            color: #4b5563;
        }
        .scope {
            width: 100%;
            border-collapse: collapse;
        }
        .scope td {
            width: 50%;
            vertical-align: top;
            border: 1px solid #d1d5db;
            padding: 8px;
        }
        .scope-title {
            font-weight: 700;
            margin-bottom: 6px;
        }
        .scope ul {
            margin: 0;
            padding-left: 15px;
        }
        .scope li {
            margin: 0 0 4px;
        }
        .signature-box {
            border: 1px solid #d1d5db;
            min-height: 64px;
            margin-top: 6px;
        }
        .footer-note {
            margin-top: 16px;
            font-size: 10px;
            color: #4b5563;
            text-align: right;
        }
    </style>
</head>
<body>
@php
    $idr = fn ($value) => 'Rp ' . number_format((int) ($value ?? 0), 0, ',', '.');
    $assets = is_array($doc['assets'] ?? null) ? $doc['assets'] : [];
    $included = is_array($doc['included_scope'] ?? null) ? $doc['included_scope'] : [];
    $excluded = is_array($doc['excluded_scope'] ?? null) ? $doc['excluded_scope'] : [];
    $signature = is_array($doc['signature'] ?? null) ? $doc['signature'] : [];
    $isSigned = (bool) ($signature['is_signed'] ?? false);
@endphp

<div class="header">DigiPro - Penawaran Layanan Estimasi Rentang Harga Properti</div>

<h1 class="title">{{ $doc['title'] ?? 'PENAWARAN LAYANAN ESTIMASI RENTANG HARGA PROPERTI' }}</h1>
<p class="subtitle">{{ $doc['subtitle'] ?? '(Tanpa Inspeksi Lapangan - Non-Reliance)' }}</p>

<table class="meta">
    <tr>
        <td class="label">No</td>
        <td>: {{ $doc['agr_no'] ?? '-' }}</td>
        <td class="label">Tanggal</td>
        <td>: {{ $doc['date'] ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Kepada</td>
        <td>: {{ $doc['user_name'] ?? '-' }}</td>
        <td class="label">ID Permohonan</td>
        <td>: {{ $doc['request_id'] ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Tujuan Penilaian</td>
        <td colspan="3">: {{ $doc['valuation_objective_label'] ?? '-' }}</td>
    </tr>
</table>

<p>
    DigiPro menyampaikan penawaran layanan Estimasi Rentang Harga Properti berdasarkan dokumen,
    foto, dan informasi yang diunggah pengguna serta data pembanding pada Bank Data DigiPro.
    Layanan ini dilakukan tanpa inspeksi lapangan dan tanpa pengukuran fisik. Hasil layanan berupa
    rentang estimasi (batas bawah - batas atas), bukan nilai tunggal/final, dan tidak dimaksudkan
    untuk digunakan sebagai dasar penjaminan/agunan, kredit, transaksi mengikat, perpajakan,
    pelaporan keuangan, ataupun tujuan penilaian profesional lainnya.
</p>

<div class="section-title">A. Daftar Aset</div>
<table class="table">
    <thead>
    <tr>
        <th style="width: 30px;">No</th>
        <th>Nama/Label Aset</th>
        <th>Lokasi Singkat</th>
        <th>Dokumen Utama</th>
        <th>Luas (basis)</th>
        <th>Catatan</th>
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
            <td>{{ $asset['note'] ?? '-' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6">Belum ada data aset.</td>
        </tr>
    @endforelse
    </tbody>
</table>
<p class="small">Catatan basis luas: DOC = luas berdasarkan dokumen yang diunggah; USER = luas berdasarkan input pengguna.</p>

<div class="section-title">B. Ruang Lingkup Layanan</div>
<table class="scope">
    <tr>
        <td>
            <div class="scope-title">Termasuk</div>
            <ul>
                @foreach ($included as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </td>
        <td>
            <div class="scope-title">Tidak termasuk</div>
            <ul>
                @foreach ($excluded as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </td>
    </tr>
</table>

<div class="section-title">C. Output</div>
<p>{{ $doc['output_text'] ?? '-' }}</p>

<div class="section-title">D. Waktu Penyelesaian (SLA)</div>
<p>{{ $doc['sla_text'] ?? '-' }}</p>

<div class="section-title">E. Biaya</div>
<table class="table">
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
        <td>Total</td>
        <td>{{ $idr($doc['total_fee'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Pajak</td>
        <td>{{ $doc['tax_note'] ?? '-' }}</td>
    </tr>
    <tr>
        <td>Metode bayar</td>
        <td>{{ $doc['payment_methods'] ?? '-' }}</td>
    </tr>
</table>

<div class="section-title">F. Pernyataan Kunci (Non-Reliance)</div>
<p>{{ $doc['statement_text'] ?? '-' }}</p>

<p>Hormat kami,</p>
<p>Platform DigiPro<br>{{ $doc['official_contact'] ?? '-' }}</p>

<div class="section-title">Persetujuan Pengguna (untuk PDF)</div>
<table class="meta">
    <tr>
        <td class="label">Nama</td>
        <td>: {{ $doc['user_name'] ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">ID/Email</td>
        <td>: {{ $doc['user_identifier'] ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Tanggal</td>
        <td>: {{ $doc['accepted_at'] ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Consent ID</td>
        <td>: {{ $doc['consent_id'] ?? '-' }}</td>
    </tr>
</table>

<div>Tanda tangan:</div>
@if ($isSigned)
    <table class="meta">
        <tr>
            <td class="label">Status</td>
            <td>: Ditandatangani digital (mock)</td>
        </tr>
        <tr>
            <td class="label">Signer</td>
            <td>: {{ $signature['signed_by_name'] ?? '-' }} ({{ $signature['signed_by_email'] ?? '-' }})</td>
        </tr>
        <tr>
            <td class="label">Waktu</td>
            <td>: {{ $signature['signed_at'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Signature ID</td>
            <td>: {{ $signature['signature_id'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Hash</td>
            <td>: {{ $signature['document_hash'] ?? '-' }}</td>
        </tr>
    </table>
@else
    <div class="signature-box"></div>
@endif

<div class="footer-note">{{ $doc['disclaimer_footer'] ?? '' }}</div>

</body>
</html>
