<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] ?? 'Laporan Kajian Pasar Properti' }}</title>
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #0f172a; line-height: 1.5; }
        h1, h2, h3, p { margin: 0; }
        .page-break { page-break-before: always; }
        .cover { min-height: 92vh; display: table; width: 100%; }
        .cover-inner { display: table-cell; vertical-align: middle; }
        .eyebrow { font-size: 10px; letter-spacing: 0.22em; text-transform: uppercase; color: #64748b; }
        .title { font-size: 25px; font-weight: 700; line-height: 1.2; margin-top: 12px; }
        .subtitle { font-size: 11px; color: #475569; margin-top: 12px; max-width: 480px; }
        .cover-box { margin-top: 28px; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px 18px; background: #f8fafc; }
        .cover-meta { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .cover-meta td { padding: 8px 0; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .cover-meta td:first-child { width: 34%; color: #64748b; }
        .section { margin-top: 24px; }
        .section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 10px; }
        .section-subtitle { font-size: 11px; font-weight: 700; margin: 16px 0 8px; }
        .lead { color: #475569; }
        .summary-boxes { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin-left: -10px; }
        .summary-boxes td { width: 50%; border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 12px; padding: 14px; }
        .metric-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
        .metric-value { font-size: 16px; font-weight: 700; margin-top: 6px; }
        .list { margin: 8px 0 0 16px; padding: 0; }
        .list li { margin: 0 0 5px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th, .table td { border: 1px solid #cbd5e1; padding: 8px 9px; vertical-align: top; }
        .table th { background: #e2e8f0; text-align: left; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.05em; }
        .asset-card { border: 1px solid #cbd5e1; border-radius: 14px; padding: 16px; margin-top: 18px; }
        .asset-header { margin-bottom: 12px; }
        .asset-address { color: #475569; margin-top: 4px; }
        .grid-2 { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-left: -12px; }
        .grid-2 td { width: 50%; vertical-align: top; }
        .panel { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; background: #fff; }
        .attribute-table { width: 100%; border-collapse: collapse; }
        .attribute-table td { padding: 5px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .attribute-table td:first-child { width: 42%; color: #64748b; }
        .attribute-table tr:last-child td { border-bottom: none; }
        .range-grid { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin-top: 12px; margin-left: -10px; }
        .range-grid td { width: 50%; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; background: #f8fafc; }
        .small-note { margin-top: 8px; color: #64748b; font-size: 9.5px; }
        .photo-grid { width: 100%; border-collapse: separate; border-spacing: 10px; margin: 10px -10px 0; }
        .photo-grid td { width: 50%; border: 1px solid #cbd5e1; border-radius: 12px; padding: 8px; }
        .photo-grid img { width: 100%; height: 180px; object-fit: cover; border-radius: 8px; }
        .photo-label { font-size: 9.5px; color: #475569; margin-top: 6px; }
        .signatures { width: 100%; border-collapse: separate; border-spacing: 16px 0; margin-top: 18px; margin-left: -16px; }
        .signatures td { width: 50%; vertical-align: top; }
        .signature-box { border: 1px solid #cbd5e1; border-radius: 14px; padding: 16px; min-height: 170px; }
        .signature-space { height: 72px; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <section class="cover">
        <div class="cover-inner">
            <div class="eyebrow">DigiPro Property Market Study</div>
            <div class="title">{{ $report['title'] ?? '-' }}</div>
            <div class="subtitle">{{ $report['subtitle'] ?? '-' }}</div>

            <div class="cover-box">
                <div class="metric-label">Kesimpulan Range</div>
                <div class="metric-value">{{ $report['cover_range_text'] ?? '-' }}</div>
                <div class="small-note">
                    Dokumen ini merangkum estimasi bawah dan estimasi atas properti berdasarkan kajian pasar yang dilakukan DigiPro.
                </div>
            </div>

            <table class="cover-meta">
                <tr>
                    <td>Nomor Request</td>
                    <td>{{ $report['request_number'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Disiapkan untuk</td>
                    <td>{{ $report['prepared_for'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Nomor Kontrak</td>
                    <td>{{ $report['contract_number'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Tanggal Penugasan</td>
                    <td>{{ $report['request_date'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Tanggal Penilaian</td>
                    <td>{{ $report['valuation_date'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Objek Utama</td>
                    <td>{{ data_get($report, 'property_summary.primary_asset_type', '-') }} - {{ data_get($report, 'property_summary.primary_address', '-') }}</td>
                </tr>
                <tr>
                    <td>Jumlah Aset</td>
                    <td>{{ data_get($report, 'property_summary.asset_count', 0) }}</td>
                </tr>
            </table>
        </div>
    </section>

    <section class="page-break">
        <div class="section-title">Surat Pengantar / Executive Summary</div>
        <p class="lead">
            Laporan ini disusun untuk memberikan gambaran estimasi rentang harga properti berdasarkan data objek, dokumen legalitas, foto, dan kajian pasar yang relevan pada tanggal penilaian.
            Output DigiPro disajikan dalam bentuk range agar pengguna memperoleh batas bawah dan batas atas estimasi pasar, bukan opini nilai tunggal.
        </p>

        <div class="section">
            <table class="summary-boxes">
                <tr>
                    <td>
                        <div class="metric-label">Estimasi Bawah</div>
                        <div class="metric-value">Rp {{ number_format((int) data_get($report, 'summary.estimated_value_low', 0), 0, ',', '.') }}</div>
                    </td>
                    <td>
                        <div class="metric-label">Estimasi Atas</div>
                        <div class="metric-value">Rp {{ number_format((int) data_get($report, 'summary.estimated_value_high', 0), 0, ',', '.') }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Definisi dan Lingkup Penugasan</div>
            <ul class="list">
                @foreach(($report['scope_points'] ?? []) as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        </div>

        <div class="section">
            <div class="section-title">Asumsi dan Syarat Pembatas</div>
            <ul class="list">
                @foreach(($report['assumptions'] ?? []) as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        </div>

        <div class="section">
            <div class="section-title">Pernyataan dan Otorisasi</div>
            <ul class="list">
                @foreach(($report['statement_points'] ?? []) as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="page-break">
        <div class="section-title">Uraian Pasar dan Metode Kajian</div>
        <p class="lead">
            DigiPro menggunakan pendekatan kajian pasar dengan melihat karakteristik objek, kecocokan peruntukan, kualitas legalitas, serta data pembanding pasar yang tersedia pada sistem.
            Rentang hasil kajian merangkum posisi estimasi bawah dan estimasi atas yang dinilai paling merepresentasikan kondisi pasar pada tanggal penilaian.
        </p>

        <div class="section">
            <div class="section-subtitle">Langkah Kajian</div>
            <ul class="list">
                @foreach(($report['methodology_points'] ?? []) as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        </div>

        <div class="section">
            <div class="section-subtitle">Ringkasan Hasil Kajian</div>
            <table class="table">
                <thead>
                    <tr>
                        <th width="8%">No</th>
                        <th width="18%">Jenis Aset</th>
                        <th width="32%">Alamat</th>
                        <th width="14%">LT / LB</th>
                        <th width="14%">Estimasi Bawah</th>
                        <th width="14%">Estimasi Atas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($report['assets'] ?? []) as $asset)
                        <tr>
                            <td>{{ $asset['no'] ?? '-' }}</td>
                            <td>{{ $asset['asset_type_label'] ?? '-' }}</td>
                            <td>{{ $asset['address'] ?? '-' }}</td>
                            <td>
                                LT {{ isset($asset['land_area']) ? number_format((float) $asset['land_area'], 2, ',', '.') : '-' }} m2
                                <br>
                                LB
                                @if(isset($asset['building_area']) && $asset['building_area'] !== null)
                                    {{ number_format((float) $asset['building_area'], 2, ',', '.') }} m2
                                @else
                                    -
                                @endif
                            </td>
                            <td>Rp {{ number_format((int) data_get($asset, 'valuation.estimated_value_low', 0), 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((int) data_get($asset, 'valuation.estimated_value_high', 0), 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @foreach(($report['assets'] ?? []) as $asset)
        <section class="page-break">
            <div class="section-title">Data Objek Properti - Aset {{ $asset['no'] ?? '-' }}</div>
            <div class="asset-card">
                <div class="asset-header">
                    <h2>{{ $asset['asset_narrative_label'] ?? '-' }} - {{ $asset['asset_type_label'] ?? '-' }}</h2>
                    <p class="asset-address">{{ $asset['address'] ?? '-' }}</p>
                    @if(!empty($asset['coordinates']) || !empty($asset['maps_link']))
                        <p class="small-note">
                            {{ $asset['coordinates'] ?? '' }}
                            @if(!empty($asset['coordinates']) && !empty($asset['maps_link']))
                                |
                            @endif
                            {{ $asset['maps_link'] ?? '' }}
                        </p>
                    @endif
                </div>

                <table class="grid-2">
                    <tr>
                        <td>
                            <div class="panel">
                                <div class="section-subtitle">Data Legal dan Karakteristik Tanah</div>
                                <table class="attribute-table">
                                    @foreach(($asset['land_characteristics'] ?? []) as $row)
                                        <tr>
                                            <td>{{ $row['label'] ?? '-' }}</td>
                                            <td>{{ $row['value'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                                @if(!empty($asset['legal_notes']))
                                    <div class="small-note">Catatan legal: {{ $asset['legal_notes'] }}</div>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="panel">
                                <div class="section-subtitle">Hasil Kajian Range</div>
                                <table class="range-grid">
                                    <tr>
                                        <td>
                                            <div class="metric-label">Estimasi Bawah</div>
                                            <div class="metric-value">Rp {{ number_format((int) data_get($asset, 'valuation.estimated_value_low', 0), 0, ',', '.') }}</div>
                                        </td>
                                        <td>
                                            <div class="metric-label">Estimasi Atas</div>
                                            <div class="metric-value">Rp {{ number_format((int) data_get($asset, 'valuation.estimated_value_high', 0), 0, ',', '.') }}</div>
                                        </td>
                                    </tr>
                                </table>
                                <div class="small-note">
                                    Range disusun berdasarkan karakteristik objek dan data pembanding pasar yang relevan.
                                </div>
                            </div>

                            @if(!empty($asset['building_characteristics']))
                                <div class="panel" style="margin-top: 12px;">
                                    <div class="section-subtitle">Data Bangunan</div>
                                    <table class="attribute-table">
                                        @foreach(($asset['building_characteristics'] ?? []) as $row)
                                            <tr>
                                                <td>{{ $row['label'] ?? '-' }}</td>
                                                <td>{{ $row['value'] ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            @endif
                        </td>
                    </tr>
                </table>

                <div class="section">
                    <div class="section-subtitle">Dokumen Pendukung Aset</div>
                    @if(!empty($asset['supporting_documents']))
                        <table class="table">
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
                        <p class="muted">Tidak ada dokumen pendukung aset yang aktif pada saat report ini dibuat.</p>
                    @endif
                </div>

                <div class="section">
                    <div class="section-subtitle">Lampiran Foto Aset</div>
                    @if(!empty($asset['photos']))
                        <table class="photo-grid">
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
                    @else
                        <p class="muted">Belum ada lampiran foto aktif untuk aset ini.</p>
                    @endif
                </div>
            </div>
        </section>
    @endforeach

    <section class="page-break">
        <div class="section-title">Lampiran Dokumen Permohonan</div>
        @if(!empty($report['request_supporting_documents']))
            <table class="table">
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
            <p class="muted">Tidak ada dokumen request level yang aktif saat report disusun.</p>
        @endif

        <div class="section">
            <div class="section-title">Penutup dan Otorisasi</div>
            <p class="lead">
                Draft ini disiapkan sebagai dasar finalisasi laporan DigiPro. Identitas reviewer dan penilai publik yang tampil di bawah mengikuti profil signer yang dipilih admin pada request ini.
            </p>

            <table class="signatures">
                <tr>
                    <td>
                        <div class="signature-box">
                            <div class="metric-label">Direview oleh</div>
                            <div class="signature-space"></div>
                            <div><strong>{{ data_get($report, 'signers.reviewer.name', '-') }}</strong>@if(data_get($report, 'signers.reviewer.title_suffix')) , {{ data_get($report, 'signers.reviewer.title_suffix') }}@endif</div>
                            <div class="muted">{{ data_get($report, 'signers.reviewer.position_title', 'Reviewer') }}</div>
                            <div class="small-note">No. Sertifikasi / Izin: {{ data_get($report, 'signers.reviewer.certification_number', '-') }}</div>
                        </div>
                    </td>
                    <td>
                        <div class="signature-box">
                            <div class="metric-label">Disetujui oleh</div>
                            <div class="signature-space"></div>
                            <div><strong>{{ data_get($report, 'signers.public_appraiser.name', '-') }}</strong>@if(data_get($report, 'signers.public_appraiser.title_suffix')) , {{ data_get($report, 'signers.public_appraiser.title_suffix') }}@endif</div>
                            <div class="muted">{{ data_get($report, 'signers.public_appraiser.position_title', 'Penilai Publik') }}</div>
                            <div class="small-note">No. Sertifikasi / Izin: {{ data_get($report, 'signers.public_appraiser.certification_number', '-') }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </section>
</body>
</html>
