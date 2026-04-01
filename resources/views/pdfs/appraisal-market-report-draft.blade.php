<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Draft Laporan Kajian Pasar</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        .title { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .subtitle { font-size: 11px; color: #475569; margin-bottom: 18px; }
        .section { margin-top: 18px; }
        .section-title { font-size: 12px; font-weight: 700; margin-bottom: 8px; text-transform: uppercase; }
        .meta-table, .value-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 6px 8px; border: 1px solid #cbd5e1; }
        .value-table th, .value-table td { padding: 8px; border: 1px solid #cbd5e1; }
        .value-table th { background: #e2e8f0; text-align: left; }
        .muted { color: #64748b; }
        .summary-grid { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .summary-grid td { padding: 10px 12px; background: #f8fafc; border: 1px solid #cbd5e1; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="title">DRAFT LAPORAN KAJIAN PASAR DALAM RANGE</div>
    <div class="subtitle">Dokumen draft internal untuk finalisasi admin sebelum upload laporan final ber-QR/barcode P2PK/ELSA.</div>

    <table class="meta-table">
        <tr>
            <td width="28%">Nomor Request</td>
            <td>{{ $report['request_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <td>Nama Klien</td>
            <td>{{ $report['client_name'] ?? '-' }}</td>
        </tr>
        <tr>
            <td>Nomor Kontrak</td>
            <td>{{ $report['contract_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <td>Versi Preview</td>
            <td>{{ $report['preview_version'] ?? 1 }}</td>
        </tr>
        <tr>
            <td>Draft Dibuat</td>
            <td>{{ $report['generated_at'] ?? '-' }}</td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Ringkasan Range Request</div>
        <table class="summary-grid">
            <tr>
                <td>
                    <div class="muted">Range Bawah</div>
                    <div>Rp {{ number_format((int) ($report['summary']['estimated_value_low'] ?? 0), 0, ',', '.') }}</div>
                </td>
                <td>
                    <div class="muted">Nilai Tengah</div>
                    <div>Rp {{ number_format((int) ($report['summary']['market_value_final'] ?? 0), 0, ',', '.') }}</div>
                </td>
                <td>
                    <div class="muted">Range Atas</div>
                    <div>Rp {{ number_format((int) ($report['summary']['estimated_value_high'] ?? 0), 0, ',', '.') }}</div>
                </td>
                <td>
                    <div class="muted">Jumlah Aset</div>
                    <div>{{ $report['summary']['assets_count'] ?? 0 }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Breakdown Aset</div>
        <table class="value-table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="17%">Jenis Aset</th>
                    <th width="30%">Alamat</th>
                    <th width="12%">LT / LB</th>
                    <th width="12%" class="right">Range Bawah</th>
                    <th width="12%" class="right">Nilai Tengah</th>
                    <th width="12%" class="right">Range Atas</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($report['assets'] ?? []) as $index => $asset)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $asset['asset_type_label'] ?? '-' }}</td>
                        <td>{{ $asset['address'] ?? '-' }}</td>
                        <td>
                            LT {{ isset($asset['land_area']) ? number_format((float) $asset['land_area'], 2, ',', '.') : '-' }}
                            <br>
                            LB {{ isset($asset['building_area']) && $asset['building_area'] !== null ? number_format((float) $asset['building_area'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="right">Rp {{ number_format((int) ($asset['estimated_value_low'] ?? 0), 0, ',', '.') }}</td>
                        <td class="right">Rp {{ number_format((int) ($asset['market_value_final'] ?? 0), 0, ',', '.') }}</td>
                        <td class="right">Rp {{ number_format((int) ($asset['estimated_value_high'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
