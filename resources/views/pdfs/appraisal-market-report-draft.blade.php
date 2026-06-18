<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] ?? 'Laporan Kajian Pasar Properti' }}</title>
    <style>
        @page { margin: 28px 44px 32px; size: A4; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5px;
            line-height: 1.34;
            color: #111827;
        }
        h1, h2, h3, p { margin: 0; }
        .page-break { page-break-before: always; }
        .avoid-break { page-break-inside: avoid; }
        .muted { color: #4b5563; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .strong { font-weight: 700; }
        .office-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .office-header td {
            vertical-align: top;
        }
        .office-header .logo-cell {
            width: 76px;
        }
        .brand-logo {
            width: 68px;
            height: auto;
            display: block;
        }
        .logo-mark {
            width: 64px;
            height: 64px;
            background: #12345f;
            color: #ffffff;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            line-height: 64px;
        }
        .office-copy {
            padding-left: 6px;
            font-size: 7.8px;
            line-height: 1.15;
            font-weight: 700;
        }
        .office-copy .office-eyebrow {
            font-size: 8px;
            text-transform: uppercase;
        }
        .office-copy .office-name {
            font-size: 11px;
            text-transform: uppercase;
        }
        .cover {
            position: relative;
            height: 270mm;
            border: 3px solid #173f72;
            padding: 0 42px 24px 112px;
        }
        .cover-band-purple {
            position: absolute;
            top: 0;
            left: 34px;
            width: 48px;
            height: 100%;
            background: #b3a3ca;
        }
        .cover-band-blue {
            position: absolute;
            top: 0;
            left: 82px;
            width: 26px;
            height: 82%;
            background: #8db4dc;
        }
        .cover-band-gray {
            position: absolute;
            bottom: 0;
            left: 82px;
            width: 26px;
            height: 19%;
            background: #cfd2d3;
        }
        .cover-header {
            margin-left: 240px;
            padding-top: 3px;
        }
        .cover-box {
            margin: 160px 18px 0 98px;
            min-height: 190px;
            border: 3px double #333333;
            border-radius: 42px;
            padding: 42px 36px 26px;
            text-align: center;
        }
        .cover-title {
            font-size: 17px;
            line-height: 1.24;
            font-weight: 700;
            text-transform: uppercase;
        }
        .cover-subtitle {
            margin-top: 4px;
            font-size: 12px;
            font-weight: 700;
        }
        .cover-client {
            margin-top: 24px;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .cover-location {
            margin-top: 48px;
            font-size: 9px;
            line-height: 1.45;
        }
        .cover-prepared {
            position: absolute;
            left: 112px;
            right: 42px;
            bottom: 58px;
            text-align: center;
            font-weight: 700;
        }
        .cover-prepared .prepared-label {
            margin-bottom: 18px;
            font-size: 9.5px;
        }
        .cover-prepared .report-no {
            margin-top: 12px;
            font-size: 7.5px;
        }
        .section-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .section-header td {
            vertical-align: top;
        }
        .section-header .header-spacer {
            width: 62%;
        }
        .section-bar {
            margin: 8px 0 12px;
            padding: 5px 10px;
            background: #5b94d3;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.34em;
            text-align: center;
            text-transform: uppercase;
        }
        .letter-meta {
            width: 100%;
            border-collapse: collapse;
            margin: 2px 0 12px;
        }
        .letter-meta td {
            padding: 1px 0;
            vertical-align: top;
        }
        .letter-label {
            width: 74px;
        }
        .letter-colon {
            width: 12px;
            text-align: center;
        }
        .letter-recipient {
            margin: 8px 0 10px;
            font-weight: 700;
        }
        .letter-subject {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 12px;
        }
        .letter-subject td {
            padding: 2px 0;
            vertical-align: top;
        }
        .body-copy {
            margin: 0 0 7px;
            text-align: justify;
        }
        .value-block {
            margin: 12px auto 14px;
            width: 58%;
            text-align: center;
            font-weight: 700;
        }
        .value-block .range-text {
            margin-top: 3px;
            font-size: 13px;
        }
        .formal-list {
            margin: 6px 0 0 13px;
            padding: 0;
        }
        .formal-list li {
            margin-bottom: 5px;
            text-align: justify;
        }
        .definition-table,
        .formal-table,
        .signature-list-table {
            width: 100%;
            border-collapse: collapse;
        }
        .definition-table {
            border-left: 16px solid #5b94d3;
        }
        .definition-table td {
            padding: 3px 6px;
            vertical-align: top;
        }
        .definition-table .key {
            width: 29%;
        }
        .definition-table .colon {
            width: 10px;
            text-align: center;
        }
        .definition-table .value {
            border: 1px solid #333333;
            text-align: justify;
        }
        .formal-table {
            margin-top: 8px;
        }
        .formal-table th,
        .formal-table td {
            border: 1px solid #333333;
            padding: 4px 6px;
            vertical-align: top;
        }
        .formal-table th {
            background: #e5edf7;
            text-align: center;
            font-size: 8.6px;
            text-transform: uppercase;
        }
        .asset-title {
            margin: 0 0 5px;
            font-size: 12px;
            font-weight: 700;
        }
        .photo-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .photo-table td {
            width: 50%;
            border: 1px solid #333333;
            padding: 5px;
            vertical-align: top;
        }
        .photo-table img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .photo-label {
            margin-top: 4px;
            font-size: 8.4px;
        }
        .signature-list-table {
            margin-top: 18px;
        }
        .signature-list-table th,
        .signature-list-table td {
            padding: 5px 6px;
            vertical-align: top;
        }
        .signature-list-table th {
            border-bottom: 1px solid #333333;
            text-align: left;
        }
        .signature-space {
            width: 170px;
            height: 72px;
            border-bottom: 1px solid #9ca3af;
        }
        .small-note {
            margin-top: 5px;
            color: #4b5563;
            font-size: 8.4px;
        }
    </style>
</head>
<body>
@php
    $brandLogoPath = public_path('images/brand/digipro-by-kjpp-hjar-logo-dark.png');
    $hasBrandLogo = is_file($brandLogoPath);
    $officeHeader = function () use ($hasBrandLogo, $brandLogoPath): string {
        ob_start();
@endphp
        <table class="office-header">
            <tr>
                <td class="logo-cell">
                    @if ($hasBrandLogo)
                        <img src="{{ $brandLogoPath }}" class="brand-logo" alt="DigiPro by KJPP HJAR">
                    @else
                        <div class="logo-mark">DP</div>
                    @endif
                </td>
                <td class="office-copy">
                    <div class="office-eyebrow">Kantor Jasa Penilai Publik</div>
                    <div class="office-name">Henricus Judi Adrianto dan Rekan</div>
                    <div>Bidang Jasa Penilai Properti.</div>
                    <div>KMK No. 17/KM.1/2014, Tgl. 16 Januari 2014</div>
                    <div>Kantor Pusat: Ruko Terminal Sako No.18, Palembang</div>
                    <div>Telp. 0711 5615793, Email: henricusja@yahoo.com</div>
                    <div>Wilayah Kerja Seluruh Indonesia</div>
                </td>
            </tr>
        </table>
@php
        return ob_get_clean();
    };
    $idr = fn ($value) => 'Rp ' . number_format((int) ($value ?? 0), 0, ',', '.');
    $primaryAssetType = data_get($report, 'property_summary.primary_asset_type', 'Properti');
    $primaryAddress = data_get($report, 'property_summary.primary_address', '-');
    $assetCount = (int) data_get($report, 'property_summary.asset_count', 0);
@endphp

<section class="cover">
    <div class="cover-band-purple"></div>
    <div class="cover-band-blue"></div>
    <div class="cover-band-gray"></div>

    <div class="cover-header">{!! $officeHeader() !!}</div>

    <div class="cover-box">
        <div class="cover-title">Laporan Kajian Pasar Properti</div>
        <div class="cover-subtitle">{{ $primaryAssetType }}</div>
        <div class="cover-client">{{ $report['client_name'] ?? '-' }}</div>
        <div class="cover-location">
            <strong>Berlokasi di :</strong><br>
            {{ $primaryAddress }}
        </div>
    </div>

    <div class="cover-prepared">
        <div class="prepared-label">Dipersiapkan Untuk :</div>
        <div>{{ $report['prepared_for'] ?? '-' }}</div>
        <div class="muted">DigiPro by KJPP HJAR - Kajian nilai pasar dalam bentuk range</div>
        <div class="report-no">No. Request : {{ $report['request_number'] ?? '-' }} / No. Kontrak : {{ $report['contract_number'] ?? '-' }}</div>
    </div>
</section>

<section class="page-break">
    {!! $officeHeader() !!}

    <table class="letter-meta">
        <tr>
            <td colspan="3">Palembang, {{ $report['valuation_date'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="letter-label">Nomor</td>
            <td class="letter-colon">:</td>
            <td>{{ $report['request_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="letter-label">Lampiran</td>
            <td class="letter-colon">:</td>
            <td>1 berkas digital</td>
        </tr>
    </table>

    <div class="letter-recipient">
        Kepada Yth.<br>
        {{ $report['prepared_for'] ?? '-' }}
    </div>

    <table class="letter-subject">
        <tr>
            <td class="letter-label">Perihal</td>
            <td class="letter-colon">:</td>
            <td><strong><em>Laporan Kajian Pasar Properti dalam Bentuk Range</em></strong><br>a.n. {{ $report['client_name'] ?? '-' }}</td>
        </tr>
    </table>

    <p class="body-copy">Dengan hormat,</p>
    <p class="body-copy">
        Sesuai data permohonan pada sistem DigiPro dengan nomor kontrak {{ $report['contract_number'] ?? '-' }},
        kami menyampaikan laporan kajian pasar properti untuk objek {{ $primaryAssetType }} yang berlokasi di {{ $primaryAddress }}.
        Kajian ini disusun berdasarkan data, foto, dokumen, dan informasi digital yang tersedia pada sistem.
    </p>
    <p class="body-copy">
        Hasil layanan DigiPro disajikan sebagai estimasi rentang harga pasar, bukan opini nilai tunggal dan bukan pengganti laporan penilaian formal dengan inspeksi lapangan.
        Pada tanggal kajian {{ $report['valuation_date'] ?? '-' }}, estimasi range properti adalah:
    </p>

    <div class="value-block">
        Estimasi Range Nilai Pasar (Rp)
        <div class="range-text">{{ $report['cover_range_text'] ?? '-' }}</div>
    </div>

    <p class="body-copy">Kajian ini bergantung kepada hal-hal sebagai berikut:</p>
    <ul class="formal-list">
        @foreach(($report['assumptions'] ?? []) as $point)
            <li>{{ $point }}</li>
        @endforeach
    </ul>

    <p class="body-copy" style="margin-top: 10px;">
        Akhirnya, laporan ini bersifat terbatas untuk pihak yang disebutkan dalam dokumen DigiPro dan digunakan dalam konteks kajian awal/range.
        Penggunaan di luar konteks tersebut memerlukan konfirmasi tertulis dari KJPP Henricus Judi Adrianto dan Rekan.
    </p>

    <div style="margin-top: 14px;">
        <strong>Hormat kami,</strong><br>
        <strong>KJPP HENRICUS JUDI ADRIANTO DAN REKAN</strong><br>
        <span>Public Appraisal & Consultant</span>
        <div style="height: 52px;"></div>
        <strong><u>{{ data_get($report, 'signers.public_appraiser.name', 'Dr. Henricus Judi Adrianto') }}</u></strong><br>
        <span>Pimpinan / {{ data_get($report, 'signers.public_appraiser.position_title', 'Penilai Publik') }}</span>
    </div>
</section>

<section class="page-break">
    <table class="section-header">
        <tr>
            <td class="header-spacer"></td>
            <td>{!! $officeHeader() !!}</td>
        </tr>
    </table>

    <div class="section-bar">Definisi dan Lingkup Penugasan</div>
    <table class="definition-table">
        <tr>
            <td class="key">a. Identifikasi Status Penilai</td>
            <td class="colon">:</td>
            <td class="value">KJPP Henricus Judi Adrianto dan Rekan bertindak sebagai penyedia layanan kajian pasar properti melalui platform DigiPro by KJPP HJAR.</td>
        </tr>
        <tr>
            <td class="key">b. Identifikasi Pemberi Tugas</td>
            <td class="colon">:</td>
            <td class="value">{{ $report['prepared_for'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="key">c. Identifikasi Pengguna Laporan</td>
            <td class="colon">:</td>
            <td class="value">{{ $report['prepared_for'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="key">d. Identifikasi Objek Kajian</td>
            <td class="colon">:</td>
            <td class="value">{{ $primaryAssetType }}<br>{{ $primaryAddress }}<br>Jumlah aset: {{ $assetCount }}</td>
        </tr>
        <tr>
            <td class="key">e. Jenis Mata Uang Yang Digunakan</td>
            <td class="colon">:</td>
            <td class="value">Rupiah (Rp.)</td>
        </tr>
        <tr>
            <td class="key">f. Maksud dan Tujuan Kajian</td>
            <td class="colon">:</td>
            <td class="value">{{ $report['valuation_objective_label'] ?? 'Kajian Nilai Pasar dalam Bentuk Range' }}</td>
        </tr>
        <tr>
            <td class="key">g. Dasar Kajian</td>
            <td class="colon">:</td>
            <td class="value">Kajian pasar digital berbasis data objek, dokumen, foto, dan data pembanding pasar yang tersedia. Hasil berupa estimasi bawah dan estimasi atas.</td>
        </tr>
        <tr>
            <td class="key">h. Tanggal Penilaian</td>
            <td class="colon">:</td>
            <td class="value">{{ $report['valuation_date'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="key">i. Tingkat Kedalaman Investigasi</td>
            <td class="colon">:</td>
            <td class="value">
                <ol class="formal-list">
                    @foreach(($report['scope_points'] ?? []) as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ol>
            </td>
        </tr>
        <tr>
            <td class="key">j. Sifat dan Sumber Informasi Yang Dapat Diandalkan</td>
            <td class="colon">:</td>
            <td class="value">Informasi bersumber dari input pengguna, dokumen dan foto yang diunggah, serta data pembanding pasar yang tersedia pada sistem DigiPro.</td>
        </tr>
        <tr>
            <td class="key">k. Asumsi Umum dan Khusus</td>
            <td class="colon">:</td>
            <td class="value">
                <ol class="formal-list">
                    @foreach(($report['assumptions'] ?? []) as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ol>
            </td>
        </tr>
        <tr>
            <td class="key">l. Pendekatan Kajian</td>
            <td class="colon">:</td>
            <td class="value">
                <ol class="formal-list">
                    @foreach(($report['methodology_points'] ?? []) as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ol>
            </td>
        </tr>
    </table>
</section>

<section class="page-break">
    <table class="section-header">
        <tr>
            <td class="header-spacer"></td>
            <td>{!! $officeHeader() !!}</td>
        </tr>
    </table>

    <div class="section-bar">Asumsi dan Syarat Pembatas</div>
    <ol class="formal-list">
        @foreach(($report['assumptions'] ?? []) as $point)
            <li>{{ $point }}</li>
        @endforeach
        <li>DigiPro by KJPP HJAR tidak melakukan inspeksi lapangan dalam layanan ini; seluruh hasil bergantung pada kecukupan dan kewajaran data digital yang diberikan.</li>
        <li>Rentang nilai yang disajikan tidak dimaksudkan sebagai dasar tunggal untuk agunan, kredit, perpajakan, pelaporan keuangan, atau transaksi mengikat yang mensyaratkan laporan penilaian formal.</li>
        <li>Perubahan kondisi pasar, kondisi fisik objek, legalitas, atau data pembanding setelah tanggal kajian dapat memengaruhi estimasi range.</li>
        <li>Gambar, foto, denah, tautan peta, dan dokumen yang ditampilkan merupakan lampiran informasi untuk membantu identifikasi objek.</li>
    </ol>
</section>

<section class="page-break">
    <table class="section-header">
        <tr>
            <td class="header-spacer"></td>
            <td>{!! $officeHeader() !!}</td>
        </tr>
    </table>

    <div class="section-bar">Ringkasan Hasil Kajian</div>
    <table class="formal-table">
        <thead>
        <tr>
            <th width="7%">No</th>
            <th width="18%">Jenis Aset</th>
            <th width="35%">Alamat</th>
            <th width="13%">LT / LB</th>
            <th width="13%">Estimasi Bawah</th>
            <th width="14%">Estimasi Atas</th>
        </tr>
        </thead>
        <tbody>
        @foreach(($report['assets'] ?? []) as $asset)
            <tr>
                <td class="text-center">{{ $asset['no'] ?? '-' }}</td>
                <td>{{ $asset['asset_type_label'] ?? '-' }}</td>
                <td>{{ $asset['address'] ?? '-' }}</td>
                <td>
                    LT {{ isset($asset['land_area']) ? number_format((float) $asset['land_area'], 2, ',', '.') : '-' }} m2<br>
                    LB
                    @if(isset($asset['building_area']) && $asset['building_area'] !== null)
                        {{ number_format((float) $asset['building_area'], 2, ',', '.') }} m2
                    @else
                        -
                    @endif
                </td>
                <td>{{ $idr(data_get($asset, 'valuation.estimated_value_low', 0)) }}</td>
                <td>{{ $idr(data_get($asset, 'valuation.estimated_value_high', 0)) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>

@foreach(($report['assets'] ?? []) as $asset)
    <section class="page-break">
        <table class="section-header">
            <tr>
                <td class="header-spacer"></td>
                <td>{!! $officeHeader() !!}</td>
            </tr>
        </table>

        <div class="section-bar">Data Objek Properti</div>
        <div class="asset-title">Aset {{ $asset['no'] ?? '-' }} - {{ $asset['asset_narrative_label'] ?? '-' }}</div>
        <table class="definition-table">
            <tr>
                <td class="key">Jenis Objek</td>
                <td class="colon">:</td>
                <td class="value">{{ $asset['asset_type_label'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="key">Alamat</td>
                <td class="colon">:</td>
                <td class="value">{{ $asset['address'] ?? '-' }}</td>
            </tr>
            @if(!empty($asset['coordinates']) || !empty($asset['maps_link']))
                <tr>
                    <td class="key">Koordinat / Peta</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $asset['coordinates'] ?? '' }} {{ !empty($asset['maps_link']) ? '| '.$asset['maps_link'] : '' }}</td>
                </tr>
            @endif
            @foreach(($asset['land_characteristics'] ?? []) as $row)
                <tr>
                    <td class="key">{{ $row['label'] ?? '-' }}</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $row['value'] ?? '-' }}</td>
                </tr>
            @endforeach
            @if(!empty($asset['legal_notes']))
                <tr>
                    <td class="key">Catatan Legal</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $asset['legal_notes'] }}</td>
                </tr>
            @endif
        </table>

        @if(!empty($asset['building_characteristics']))
            <div class="section-bar" style="margin-top: 14px;">Data Bangunan</div>
            <table class="definition-table">
                @foreach(($asset['building_characteristics'] ?? []) as $row)
                    <tr>
                        <td class="key">{{ $row['label'] ?? '-' }}</td>
                        <td class="colon">:</td>
                        <td class="value">{{ $row['value'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <div class="section-bar" style="margin-top: 14px;">Hasil Kajian Range</div>
        <table class="formal-table avoid-break">
            <tr>
                <th>Estimasi Bawah</th>
                <th>Estimasi Atas</th>
                <th>Keterangan</th>
            </tr>
            <tr>
                <td class="text-center strong">{{ $idr(data_get($asset, 'valuation.estimated_value_low', 0)) }}</td>
                <td class="text-center strong">{{ $idr(data_get($asset, 'valuation.estimated_value_high', 0)) }}</td>
                <td>Range disusun berdasarkan karakteristik objek dan data pembanding pasar yang relevan pada sistem DigiPro.</td>
            </tr>
        </table>

        <div class="section-bar" style="margin-top: 14px;">Dokumen Pendukung Aset</div>
        @if(!empty($asset['supporting_documents']))
            <table class="formal-table">
                <thead>
                <tr>
                    <th width="35%">Jenis Dokumen</th>
                    <th width="45%">Nama File</th>
                    <th width="20%">Waktu Upload</th>
                </tr>
                </thead>
                <tbody>
                @foreach(($asset['supporting_documents'] ?? []) as $file)
                    <tr>
                        <td>{{ $file['label'] ?? '-' }}</td>
                        <td>{{ $file['original_name'] ?? '-' }}</td>
                        <td>{{ $file['created_at'] ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="muted">Tidak ada dokumen pendukung aset yang aktif pada saat laporan ini dibuat.</p>
        @endif

        @if(!empty($asset['photos']))
            <div class="section-bar" style="margin-top: 14px;">Lampiran Foto Aset</div>
            <table class="photo-table">
                @foreach(array_chunk($asset['photos'], 2) as $photoRow)
                    <tr>
                        @foreach($photoRow as $photo)
                            <td>
                                <img src="{{ $photo['image_data_uri'] }}" alt="{{ $photo['label'] ?? 'Foto Aset' }}">
                                <div class="photo-label">{{ $photo['label'] ?? '-' }}</div>
                            </td>
                        @endforeach
                        @if(count($photoRow) === 1)
                            <td></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @endif
    </section>
@endforeach

<section class="page-break">
    <table class="section-header">
        <tr>
            <td class="header-spacer"></td>
            <td>{!! $officeHeader() !!}</td>
        </tr>
    </table>

    <div class="section-bar">Pernyataan dan Otorisasi</div>
    <ol class="formal-list">
        @foreach(($report['statement_points'] ?? []) as $point)
            <li>{{ $point }}</li>
        @endforeach
        <li>Laporan ini merupakan laporan administratif DigiPro dan diproses berdasarkan dokumen pendukung, QR/barcode, dan tanda tangan yang tersedia pada sistem.</li>
    </ol>

    <table class="signature-list-table">
        <thead>
        <tr>
            <th width="7%">No.</th>
            <th width="61%">Nama</th>
            <th width="32%">Tanda Tangan</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td><strong>1.</strong></td>
            <td>
                <strong>Pimpinan / Penilai Publik</strong><br><br>
                <strong>{{ data_get($report, 'signers.public_appraiser.name', '-') }}</strong><br>
                Jabatan: {{ data_get($report, 'signers.public_appraiser.position_title', 'Penilai Publik') }}<br>
                No. Sertifikasi / Izin: {{ data_get($report, 'signers.public_appraiser.certification_number', '-') }}
            </td>
            <td><div class="signature-space"></div></td>
        </tr>
        <tr>
            <td><strong>2.</strong></td>
            <td>
                <strong>Reviewer / Pemeriksa Laporan</strong><br><br>
                <strong>{{ data_get($report, 'signers.reviewer.name', '-') }}</strong><br>
                Jabatan: {{ data_get($report, 'signers.reviewer.position_title', 'Reviewer') }}<br>
                No. Sertifikasi / Izin: {{ data_get($report, 'signers.reviewer.certification_number', '-') }}
            </td>
            <td><div class="signature-space"></div></td>
        </tr>
        </tbody>
    </table>

    <div class="section-bar" style="margin-top: 18px;">Lampiran Dokumen Permohonan</div>
    @if(!empty($report['request_supporting_documents']))
        <table class="formal-table">
            <thead>
            <tr>
                <th width="28%">Jenis Dokumen</th>
                <th width="52%">Nama File</th>
                <th width="20%">Waktu Upload</th>
            </tr>
            </thead>
            <tbody>
            @foreach(($report['request_supporting_documents'] ?? []) as $file)
                <tr>
                    <td>{{ $file['label'] ?? '-' }}</td>
                    <td>{{ $file['original_name'] ?? '-' }}</td>
                    <td>{{ $file['created_at'] ?? '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Tidak ada dokumen request level yang aktif saat laporan disusun.</p>
    @endif
</section>
</body>
</html>
