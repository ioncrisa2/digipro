<?php

namespace App\Services\Customer\Payloads;

use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use Illuminate\Support\Facades\Storage;

class AppraisalContractDocumentBuilder
{
    public function __construct(
        private readonly AppraisalPayloadFormatter $formatter,
    ) {
    }

    public function build(AppraisalRequest $record): array
    {
        $record->loadMissing([
            'user:id,name,email',
            'assets:id,appraisal_request_id,asset_type,land_area,building_area,address',
            'assets.files:id,appraisal_asset_id,type,original_name',
            'offerNegotiations:id,appraisal_request_id,user_id,action,meta,created_at',
            'offerNegotiations.user:id,name,email',
        ]);

        $assetRows = $record->assets
            ->values()
            ->map(function (AppraisalAsset $asset, int $index): array {
                $docLabels = $asset->files
                    ->filter(fn ($file) => ! $this->formatter->isPhotoFileType($file->type))
                    ->map(fn ($file) => $this->formatter->contractDocumentTypeLabel($file->type))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'no' => $index + 1,
                    'label' => $this->formatter->assetTypeLabelForContract($this->formatter->enumBackedValue(\App\Enums\AssetTypeEnum::class, $asset->asset_type)),
                    'address' => $asset->address ?: '-',
                    'main_documents' => empty($docLabels) ? '-' : implode(', ', $docLabels),
                    'area_basis' => $this->assetAreaBasisForContract($asset),
                    'note' => $this->assetNoteForContract($asset),
                ];
            })
            ->all();

        $assetCount = count($assetRows);
        $totalFee = (int) ($record->fee_total ?? 0);
        $feePerAsset = $assetCount > 0 ? (int) round($totalFee / $assetCount) : $totalFee;
        $contractDate = $record->contract_date ?? now();

        $acceptedAt = optional(
            $record->offerNegotiations
                ->where('action', 'accept_offer')
                ->sortByDesc('created_at')
                ->first()
        )->created_at;

        $supportContact = [
            'name' => (string) config('support.name', 'Tim Support DigiPro by KJPP HJAR'),
            'phone' => (string) config('support.phone', '-'),
            'whatsapp' => (string) config('support.whatsapp', '-'),
            'email' => (string) config('support.email', '-'),
            'availability_label' => (string) config('support.availability_label', '-'),
        ];

        $signatureLog = $record->offerNegotiations
            ->where('action', 'contract_sign_mock')
            ->sortByDesc('id')
            ->first();

        $signatureMeta = is_array($signatureLog?->meta) ? $signatureLog->meta : [];
        $signedAt = $signatureMeta['signed_at'] ?? ($signatureLog?->created_at?->toDateTimeString());
        $signedPdfPath = is_string($signatureMeta['signed_pdf_path'] ?? null)
            ? $signatureMeta['signed_pdf_path']
            : null;

        $signedPdfUrl = null;
        if ($signedPdfPath && Storage::disk('public')->exists($signedPdfPath)) {
            $signedPdfUrl = Storage::disk('public')->url($signedPdfPath);
        }

        $requestorName = $record->client_name ?: ($record->user?->name ?: '-');
        $openingParagraphs = $this->openingParagraphs($assetRows);
        $slaText = 'Estimasi waktu penyelesaian umumnya beberapa jam sejak data minimum dinyatakan lengkap oleh sistem, dengan batas waktu maksimum 1-24 jam.';

        return [
            'title' => 'PENAWARAN LAYANAN ESTIMASI RENTANG HARGA PROPERTI',
            'subtitle' => '(Tanpa Inspeksi Lapangan - Non-Reliance)',
            'valuation_objective_label' => $record->valuation_objective?->label() ?? 'Kajian Nilai Pasar dalam Bentuk Range',
            'agr_no' => $record->contract_number ?: '-',
            'date' => optional($contractDate)->toDateString() ?: now()->toDateString(),
            'date_label' => $this->formatIndonesianDate($contractDate),
            'city_date_line' => 'Jakarta, ' . $this->formatIndonesianDate($contractDate),
            'user_name' => $record->user?->name ?: $requestorName,
            'request_id' => $record->request_number ?: ('REQ-' . $record->id),
            'user_identifier' => $record->user?->email ?: '-',
            'subject' => 'Penawaran dan Lingkup Penugasan Layanan Kajian Nilai Pasar Properti Digital',
            'request_reference' => filled($record->client_spk_number)
                ? 'Menindaklanjuti referensi/permintaan dengan nomor ' . trim((string) $record->client_spk_number) . ', bersama ini kami sampaikan penawaran dan lingkup penugasan layanan DigiPro by KJPP HJAR.'
                : 'Bersama ini kami sampaikan penawaran dan lingkup penugasan layanan DigiPro by KJPP HJAR untuk kajian nilai pasar properti digital.',
            'recipient_lines' => array_values(array_filter([
                $requestorName,
                ...$this->multilineTextLines($record->client_address),
            ])),
            'opening_paragraphs' => $openingParagraphs,
            'scope_items' => $this->scopeItems($record, $assetRows, $supportContact, $slaText),
            'spi_references' => [
                'KEPI dan SPI Edisi VII 2018 dirujuk sepanjang relevan untuk identifikasi penugasan, dasar nilai, asumsi, pembatasan, dan pelaporan.',
                'Dasar nilai tetap Nilai Pasar, namun output layanan DigiPro by KJPP HJAR disajikan sebagai kajian nilai pasar dalam bentuk range, bukan opini nilai tunggal.',
                'Setiap penggunaan hasil di luar lingkup surat ini memerlukan evaluasi ulang dan tidak otomatis dapat diandalkan oleh pihak ketiga.',
            ],
            'assets' => $assetRows,
            'asset_count' => $assetCount,
            'fee_per_asset' => $feePerAsset,
            'total_fee' => $totalFee,
            'tax_note' => 'Menyesuaikan ketentuan perpajakan yang berlaku.',
            'payment_methods' => 'Pembayaran online melalui Midtrans Snap (VA, QRIS, dan e-wallet yang tersedia).',
            'included_scope' => [
                'Telaah administratif dokumen, foto, dan data aset yang diunggah pengguna.',
                'Pemilihan pembanding relevan dari bank data DigiPro by KJPP HJAR dan sumber sekunder yang dianggap layak.',
                'Penyusunan kajian nilai pasar dalam bentuk range beserta indikator pendukung internal.',
            ],
            'excluded_scope' => [
                'Inspeksi lapangan, kunjungan ke lokasi, dan pengukuran fisik objek.',
                'Verifikasi legalitas menyeluruh di luar dokumen yang diunggah pengguna.',
                'Penerbitan opini nilai tunggal/final untuk keperluan agunan, transaksi mengikat, perpajakan, atau pelaporan keuangan.',
            ],
            'output_text' => 'Hasil estimasi ditampilkan pada halaman DigiPro by KJPP HJAR dan tersedia untuk diunduh dalam format PDF.',
            'sla_text' => $slaText,
            'statement_text' => 'Dokumen penawaran dan hasil layanan DigiPro by KJPP HJAR bersifat informasi umum. DigiPro by KJPP HJAR tidak melakukan inspeksi lapangan. Hasil layanan berupa estimasi rentang, bukan nilai final, dan tidak dimaksudkan untuk digunakan sebagai dasar penjaminan/agunan, kredit, transaksi mengikat, perpajakan, pelaporan keuangan, maupun tujuan penilaian profesional.',
            'official_contact' => config('app.name') . ' User Portal',
            'accepted_at' => $acceptedAt?->toDateTimeString() ?: '-',
            'consent_id' => 'CONSENT-' . $record->id,
            'disclaimer_footer' => 'Dokumen ini bersifat informasi umum dan non-reliance (tanpa inspeksi lapangan).',
            'support_contact' => $supportContact,
            'sender' => [
                'organization' => (string) config('app.name', 'DigiPro by KJPP HJAR'),
                'division' => 'Layanan Kajian Nilai Pasar Properti Digital',
                'representative_name' => $supportContact['name'],
                'representative_title' => 'Perwakilan Layanan DigiPro by KJPP HJAR',
            ],
            'approval' => [
                'client_name' => $requestorName,
                'client_title' => 'Pemberi Tugas / Pengguna Hasil',
            ],
            'signature' => [
                'is_signed' => (bool) $signatureLog,
                'signed_at' => $signedAt ?: '-',
                'signed_by_name' => $signatureMeta['signed_by_name'] ?? ($signatureLog?->user?->name ?: '-'),
                'signed_by_email' => $signatureMeta['signed_by_email'] ?? ($signatureLog?->user?->email ?: '-'),
                'signature_id' => $signatureMeta['signature_id'] ?? '-',
                'method' => $signatureMeta['method'] ?? ($signatureLog ? 'clickwrap' : '-'),
                'provider' => $signatureMeta['provider'] ?? ($signatureLog ? 'mock' : '-'),
                'document_hash' => $signatureMeta['document_hash'] ?? '-',
                'signed_pdf_path' => $signedPdfPath,
                'signed_pdf_url' => $signedPdfUrl,
            ],
        ];
    }

    private function assetAreaBasisForContract(AppraisalAsset $asset): string
    {
        $landArea = is_numeric($asset->land_area) ? (float) $asset->land_area : null;
        $buildingArea = is_numeric($asset->building_area) ? (float) $asset->building_area : null;

        if ($landArea === null && $buildingArea === null) {
            return '-';
        }

        if ($landArea !== null && $buildingArea !== null) {
            return sprintf('DOC - LT %.2f m2 | LB %.2f m2', $landArea, $buildingArea);
        }

        if ($landArea !== null) {
            return sprintf('DOC - LT %.2f m2', $landArea);
        }

        return sprintf('DOC - LB %.2f m2', $buildingArea);
    }

    private function assetNoteForContract(AppraisalAsset $asset): string
    {
        $hasBuilding = is_numeric($asset->building_area) && (float) $asset->building_area > 0;

        return $hasBuilding ? 'Tanah dan bangunan' : 'Tanah/lahan';
    }

    private function openingParagraphs(array $assetRows): array
    {
        $assetCount = count($assetRows);

        return [
            'DigiPro by KJPP HJAR menyediakan layanan kajian nilai pasar properti berbasis dokumen, foto, input pengguna, dan data pembanding yang tersedia pada ekosistem DigiPro by KJPP HJAR serta sumber sekunder yang dianggap layak.',
            $assetCount > 0
                ? "Penugasan ini mencakup {$assetCount} aset properti yang akan dianalisis tanpa inspeksi lapangan, tanpa pengukuran fisik, dan tanpa verifikasi on-site."
                : 'Penugasan ini akan dianalisis tanpa inspeksi lapangan, tanpa pengukuran fisik, dan tanpa verifikasi on-site.',
            'Hasil layanan hanya disajikan dalam bentuk kajian nilai pasar dalam bentuk range dan tidak dimaksudkan sebagai opini nilai tunggal/final.',
        ];
    }

    private function scopeItems(AppraisalRequest $record, array $assetRows, array $supportContact, string $slaText): array
    {
        $requestorName = $record->client_name ?: ($record->user?->name ?: 'Pemberi Tugas');
        $requestorAddress = $this->singleLineText($record->client_address) ?: 'mengikuti data identitas yang tercatat pada portal DigiPro by KJPP HJAR';
        $assetDescription = $this->assetDescription($assetRows);
        $totalFee = $this->formatter->formatRupiah((int) ($record->fee_total ?? 0));
        $feePerAsset = $this->formatter->formatRupiah((int) (($record->fee_total ?? 0) > 0 && count($assetRows) > 0 ? round((int) $record->fee_total / count($assetRows)) : (int) ($record->fee_total ?? 0)));
        $validityText = $record->offer_validity_days
            ? $record->offer_validity_days . ' hari kalender sejak tanggal surat ini.'
            : 'mengikuti kebijakan aktif pada portal DigiPro by KJPP HJAR atau sampai ada pembaruan tertulis dari DigiPro by KJPP HJAR.';
        $analysisDate = $this->formatIndonesianDate($record->contract_date ?? now());
        $documentChecklist = $this->documentChecklistText($assetRows);

        return [
            [
                'no' => 1,
                'title' => 'Status Penugasan',
                'lines' => [
                    'Penugasan dilaksanakan sebagai layanan digital DigiPro by KJPP HJAR berbasis dokumen, foto, data input pengguna, dan data pembanding yang tersedia.',
                    'Tidak dilakukan inspeksi lapangan, kunjungan lokasi, ataupun pengukuran fisik terhadap objek kajian.',
                    'Analisis disusun secara independen berdasarkan data yang tersedia pada saat kajian dilakukan.',
                ],
            ],
            [
                'no' => 2,
                'title' => 'Pemberi Tugas',
                'lines' => [
                    "{$requestorName}, dengan alamat/identitas {$requestorAddress}.",
                ],
            ],
            [
                'no' => 3,
                'title' => 'Pengguna Hasil',
                'lines' => [
                    "Pengguna hasil dalam lingkup surat ini adalah {$requestorName} dan pihak internal yang secara sah ditunjuk olehnya.",
                    'Penggunaan oleh pihak lain di luar lingkup tersebut memerlukan persetujuan tertulis dari DigiPro by KJPP HJAR.',
                ],
            ],
            [
                'no' => 4,
                'title' => 'Objek Kajian dan Kepemilikan',
                'lines' => [
                    $assetDescription,
                    'Identifikasi kepemilikan, alamat, dan luas mengikuti dokumen serta data yang diunggah oleh pengguna dan tidak diverifikasi melalui inspeksi lapangan.',
                ],
            ],
            [
                'no' => 5,
                'title' => 'Bentuk Kepemilikan',
                'lines' => [
                    'Bentuk kepemilikan mengikuti dokumen yang diunggah pengguna atau pernyataan pengguna pada saat permohonan dibuat.',
                    'DigiPro by KJPP HJAR tidak melakukan konfirmasi fisik maupun investigasi yuridis mendalam di luar dokumen yang diterima.',
                ],
            ],
            [
                'no' => 6,
                'title' => 'Jenis Mata Uang yang Digunakan',
                'lines' => [
                    'Seluruh analisis, estimasi, dan penyajian hasil menggunakan mata uang Rupiah (Rp).',
                ],
            ],
            [
                'no' => 7,
                'title' => 'Maksud dan Tujuan Penugasan',
                'lines' => [
                    'Tujuan penugasan hanya untuk kajian nilai pasar dalam bentuk range.',
                    'Penugasan ini tidak bertujuan menghasilkan opini nilai tunggal/final dan tidak untuk menggantikan laporan penilaian formal dengan inspeksi lapangan.',
                ],
            ],
            [
                'no' => 8,
                'title' => 'Dasar Nilai',
                'lines' => [
                    'Dasar nilai yang dirujuk adalah Nilai Pasar sesuai kerangka SPI yang relevan.',
                    'Output DigiPro by KJPP HJAR disajikan sebagai rentang nilai pasar yang merepresentasikan kisaran wajar berdasarkan data yang tersedia, bukan nilai tunggal.',
                ],
            ],
            [
                'no' => 9,
                'title' => 'Tanggal Acuan Analisis',
                'lines' => [
                    "Tanggal acuan analisis ditetapkan pada {$analysisDate} atau tanggal terakhir data material dinyatakan lengkap oleh sistem.",
                    'Apabila terdapat perubahan material pada data atau dokumen setelah tanggal acuan tersebut, DigiPro by KJPP HJAR berhak melakukan penyesuaian atau meminta pengajuan ulang.',
                ],
            ],
            [
                'no' => 10,
                'title' => 'Tingkat Kedalaman Investigasi',
                'lines' => [
                    'Investigasi dibatasi pada telaah administratif atas dokumen, foto, dan data yang diunggah pengguna serta observasi tidak langsung melalui data pembanding.',
                    'Tidak terdapat inspeksi lapangan, wawancara on-site, pengukuran fisik, pengujian struktur, ataupun pemeriksaan lingkungan secara langsung.',
                ],
            ],
            [
                'no' => 11,
                'title' => 'Sifat dan Sumber Informasi yang Dapat Diandalkan',
                'lines' => [
                    'Sumber informasi dapat meliputi dokumen legal/fiskal yang diunggah pengguna, foto objek, input data aset, bank data pembanding internal DigiPro by KJPP HJAR, dan sumber publik/sekunder yang dianggap layak.',
                    'DigiPro by KJPP HJAR berhak mengabaikan informasi yang tidak konsisten, tidak lengkap, atau tidak dapat diverifikasi secara administratif.',
                ],
            ],
            [
                'no' => 12,
                'title' => 'Asumsi dan Asumsi Khusus',
                'lines' => [
                    'DigiPro by KJPP HJAR mengasumsikan bahwa dokumen dan data yang diunggah adalah benar, relevan, mutakhir, dan diberikan oleh pihak yang berwenang.',
                    'Kondisi fisik objek, batas-batas tanah, luas, utilitas, aksesibilitas, dan aspek lingkungan diasumsikan mengikuti informasi yang disampaikan pengguna karena tidak dilakukan inspeksi lapangan.',
                    'Setiap ketidaksesuaian material pada dokumen atau kondisi riil dapat mempengaruhi hasil kajian secara signifikan.',
                ],
            ],
            [
                'no' => 13,
                'title' => 'Persyaratan atas Persetujuan untuk Publikasi',
                'lines' => [
                    'Publikasi, kutipan, atau reproduksi sebagian maupun seluruh hasil kajian di luar kepentingan internal pengguna harus mendapat persetujuan tertulis dari DigiPro by KJPP HJAR, kecuali diwajibkan oleh hukum.',
                ],
            ],
            [
                'no' => 14,
                'title' => 'Standar Penilaian yang Dirujuk',
                'lines' => [
                    'Penugasan ini merujuk pada KEPI dan SPI Edisi VII 2018 sepanjang relevan dengan penugasan berbasis dokumen, identifikasi lingkup penugasan, dasar nilai, asumsi, pembatasan, dan pengungkapan hasil.',
                    'Penerapan standar tersebut dibatasi oleh sifat layanan DigiPro by KJPP HJAR yang tidak melakukan inspeksi lapangan.',
                ],
            ],
            [
                'no' => 15,
                'title' => 'Bentuk Hasil Kajian',
                'lines' => [
                    'Hasil yang disampaikan berupa kajian nilai pasar dalam bentuk range melalui portal DigiPro by KJPP HJAR dan/atau dokumen PDF.',
                    'Dokumen hasil bukan laporan penilaian formal dengan opini nilai tunggal dan tidak memuat reliance setara appraisal lengkap.',
                ],
            ],
            [
                'no' => 16,
                'title' => 'Batasan Tanggung Jawab kepada Pihak Selain Pemberi Tugas',
                'lines' => [
                    'DigiPro by KJPP HJAR tidak memiliki tanggung jawab kepada pihak ketiga yang menerima, menggunakan, atau mengandalkan hasil kajian tanpa persetujuan tertulis dari DigiPro by KJPP HJAR.',
                    'Pihak ketiga dilarang menganggap hasil ini sebagai dasar final untuk agunan, kredit, transaksi mengikat, perpajakan, atau pelaporan keuangan.',
                ],
            ],
            [
                'no' => 17,
                'title' => 'Pernyataan Tertulis dari Pemberi Tugas',
                'lines' => [
                    'Data, dokumen, dan informasi yang diunggah pengguna melalui portal DigiPro by KJPP HJAR diperlakukan sebagai pernyataan pengguna mengenai kebenaran dan sifat informasi tersebut.',
                    'Apabila diperlukan, DigiPro by KJPP HJAR dapat meminta klarifikasi atau dokumen tambahan sebelum kajian diterbitkan.',
                ],
            ],
            [
                'no' => 18,
                'title' => 'Biaya Jasa',
                'lines' => [
                    "Biaya layanan DigiPro by KJPP HJAR sebesar {$totalFee} untuk keseluruhan penugasan, dengan indikasi rata-rata {$feePerAsset} per aset.",
                    'Pajak mengikuti ketentuan perpajakan yang berlaku.',
                    'Metode pembayaran dilakukan melalui Midtrans Snap (VA, QRIS, dan e-wallet yang tersedia), dengan masa berlaku penawaran ' . $validityText,
                ],
            ],
            [
                'no' => 19,
                'title' => 'Permintaan Data Awal',
                'lines' => [
                    'Dokumen minimum yang lazim diminta meliputi sertifikat/bukti kepemilikan, PBB, IMB/PBG bila ada bangunan, foto objek, dan dokumen identitas pendukung lainnya.',
                    $documentChecklist,
                ],
            ],
            [
                'no' => 20,
                'title' => 'Kerangka Waktu Pelaksanaan',
                'lines' => [
                    $slaText,
                    'Perhitungan SLA dimulai setelah data minimum dinyatakan lengkap oleh sistem atau admin yang berwenang.',
                ],
            ],
            [
                'no' => 21,
                'title' => 'Prosedur Pelaksanaan Penugasan',
                'lines' => [
                    'Tahapan penugasan meliputi penerimaan data, review administratif, pemilihan pembanding, analisis rentang nilai pasar, review mutu internal, dan penerbitan hasil digital.',
                    'Apabila terdapat kekurangan data, DigiPro by KJPP HJAR dapat menunda analisis sampai dokumen tambahan diterima.',
                ],
            ],
            [
                'no' => 22,
                'title' => 'Pembatalan Penugasan',
                'lines' => [
                    'Pembatalan permohonan mengikuti kebijakan layanan DigiPro by KJPP HJAR yang berlaku pada saat transaksi dilakukan.',
                    'Apabila proses analisis telah dimulai atau dokumen final telah disiapkan, penyelesaian biaya dan administrasi akan mengikuti status pekerjaan yang telah berjalan.',
                ],
            ],
            [
                'no' => 23,
                'title' => 'Kerahasiaan Informasi',
                'lines' => [
                    'DigiPro by KJPP HJAR akan menjaga kerahasiaan informasi yang diterima dan hanya menggunakannya untuk kebutuhan penugasan, pemenuhan kewajiban hukum, audit internal, serta peningkatan kualitas layanan sesuai kebijakan yang berlaku.',
                ],
            ],
            [
                'no' => 24,
                'title' => 'Penutup',
                'lines' => [
                    "Apabila diperlukan klarifikasi lebih lanjut, pemberi tugas dapat menghubungi {$supportContact['name']} melalui {$supportContact['phone']} / {$supportContact['whatsapp']} atau email {$supportContact['email']}.",
                    'Persetujuan atas surat ini menandakan bahwa pemberi tugas memahami karakter layanan DigiPro by KJPP HJAR yang berbasis dokumen dan tanpa inspeksi lapangan.',
                ],
            ],
            [
                'no' => 25,
                'title' => 'Lain-lain',
                'lines' => [
                    'Perubahan material atas data, dokumen, tujuan penggunaan, atau identitas objek setelah surat ini diterbitkan dapat menyebabkan perubahan hasil, biaya, maupun SLA.',
                    'Dalam kondisi tersebut, DigiPro by KJPP HJAR berhak meminta pembaruan data atau membuat penawaran ulang agar tujuan penugasan tetap terbatas pada kajian nilai pasar dalam bentuk range.',
                ],
            ],
        ];
    }

    private function assetDescription(array $assetRows): string
    {
        if ($assetRows === []) {
            return 'Objek kajian akan mengikuti data aset yang diunggah dan dinyatakan lengkap pada portal DigiPro by KJPP HJAR.';
        }

        $lines = array_map(function (array $asset): string {
            $address = filled($asset['address'] ?? null) ? $asset['address'] : 'alamat belum diisi';
            $documents = filled($asset['main_documents'] ?? null) ? $asset['main_documents'] : 'dokumen pendukung mengikuti unggahan pengguna';

            return sprintf(
                'Aset %d berupa %s berlokasi di %s dengan dasar dokumen %s dan basis luas %s.',
                (int) ($asset['no'] ?? 0),
                $asset['label'] ?? 'Properti',
                $address,
                $documents,
                $asset['area_basis'] ?? '-'
            );
        }, $assetRows);

        return implode(' ', $lines);
    }

    private function documentChecklistText(array $assetRows): string
    {
        $documents = collect($assetRows)
            ->pluck('main_documents')
            ->filter(fn ($value) => filled($value) && $value !== '-')
            ->implode('; ');

        if ($documents === '') {
            return 'Jenis dokumen rinci akan mengikuti kelengkapan yang diunggah pengguna pada masing-masing aset.';
        }

        return 'Dokumen yang saat ini teridentifikasi pada aset meliputi: ' . $documents . '.';
    }

    private function multilineTextLines(?string $value): array
    {
        if (! filled($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\r\n|\r|\n/', trim((string) $value)) ?: []
        )));
    }

    private function singleLineText(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', (string) $value)) ?? '');
    }

    private function formatIndonesianDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d') . ' ' . $this->indonesianMonth((int) $value->format('m')) . ' ' . $value->format('Y');
        }

        $timestamp = strtotime((string) $value);

        if ($timestamp === false) {
            return (string) $value;
        }

        return date('d', $timestamp) . ' ' . $this->indonesianMonth((int) date('m', $timestamp)) . ' ' . date('Y', $timestamp);
    }

    private function indonesianMonth(int $month): string
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ][$month] ?? (string) $month;
    }
}
