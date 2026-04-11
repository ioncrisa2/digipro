<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $doc['title'] ?? 'Disclaimer DigiPro by KJPP HJAR' }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #0f172a;
            margin: 32px;
            line-height: 1.55;
        }
        h1 {
            font-size: 20px;
            margin: 0 0 8px;
        }
        h2 {
            font-size: 14px;
            margin: 20px 0 8px;
        }
        .muted {
            color: #475569;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0 20px;
        }
        .meta td {
            padding: 6px 0;
            vertical-align: top;
        }
        .meta td:first-child {
            width: 180px;
            color: #475569;
        }
        .section {
            margin-top: 18px;
        }
        .section-title {
            font-weight: 700;
            margin-bottom: 8px;
        }
        ul {
            margin: 8px 0 0 20px;
            padding: 0;
        }
        li {
            margin: 0 0 6px;
        }
        .footer {
            margin-top: 28px;
            padding-top: 12px;
            border-top: 1px solid #cbd5e1;
            font-size: 11px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <h1>{{ $doc['title'] ?? 'DISCLAIMER & PERSETUJUAN DIGIPRO BY KJPP HJAR' }}</h1>
    <div class="muted">{{ $doc['document_title'] ?? 'Dokumen consent DigiPro by KJPP HJAR' }}</div>

    <table class="meta">
        <tr>
            <td>Nomor Request</td>
            <td>{{ $doc['request_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <td>Nama Klien</td>
            <td>{{ $doc['client_name'] ?? '-' }}</td>
        </tr>
        <tr>
            <td>Waktu Persetujuan</td>
            <td>{{ $doc['accepted_at'] ?? '-' }}</td>
        </tr>
        <tr>
            <td>Versi Dokumen</td>
            <td>{{ $doc['version'] ?? '-' }}</td>
        </tr>
        <tr>
            <td>Hash Dokumen</td>
            <td>{{ $doc['hash'] ?? '-' }}</td>
        </tr>
    </table>

    @foreach (($doc['sections'] ?? []) as $section)
        <div class="section">
            <div class="section-title">{{ $section['heading'] ?? 'Bagian Dokumen' }}</div>

            @if (!empty($section['lead']))
                <div>{{ $section['lead'] }}</div>
            @endif

            @if (!empty($section['items']) && is_array($section['items']))
                <ul>
                    @foreach ($section['items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach

    <div class="section">
        <div class="section-title">Pernyataan Persetujuan</div>
        <div>{{ $doc['checkbox_label'] ?? 'Saya telah membaca, memahami, dan menyetujui dokumen ini.' }}</div>
    </div>

    <div class="footer">
        Dokumen ini dibuat otomatis oleh sistem DigiPro by KJPP HJAR berdasarkan snapshot consent yang terkait dengan request ini.
    </div>
</body>
</html>
