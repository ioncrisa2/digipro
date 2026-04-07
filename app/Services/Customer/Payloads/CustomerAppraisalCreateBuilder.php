<?php

namespace App\Services\Customer\Payloads;

use App\Enums\AssetTypeEnum;
use App\Enums\ValuationObjectiveEnum;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use App\Support\AppraisalAssetFieldOptions;

class CustomerAppraisalCreateBuilder
{
    public function build(?int $provinceId, ?int $regencyId, ?int $districtId, bool $needsConsent, ?array $consentData): array
    {
        $maxFileUploads = (int) ini_get('max_file_uploads');

        return [
            'provinces' => Province::select('id', 'name')->orderBy('name')->get(),
            'regencies' => $provinceId
                ? Regency::select('id', 'name')->where('province_id', $provinceId)->orderBy('name')->get()
                : [],
            'districts' => $regencyId
                ? District::select('id', 'name')->where('regency_id', $regencyId)->orderBy('name')->get()
                : [],
            'villages' => $districtId
                ? Village::select('id', 'name')->where('district_id', $districtId)->orderBy('name')->get()
                : [],
            'assetTypeOptions' => collect(AssetTypeEnum::cases())
                ->map(fn (AssetTypeEnum $case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                ])
                ->values()
                ->toArray(),
            'usageOptions' => AppraisalAssetFieldOptions::usageOptions(),
            'titleDocumentOptions' => AppraisalAssetFieldOptions::titleDocumentOptions(),
            'landShapeOptions' => AppraisalAssetFieldOptions::landShapeOptions(),
            'landPositionOptions' => AppraisalAssetFieldOptions::landPositionOptions(),
            'landConditionOptions' => AppraisalAssetFieldOptions::landConditionOptions(),
            'topographyOptions' => AppraisalAssetFieldOptions::topographyOptions(),
            'needsConsent' => $needsConsent,
            'consentData' => $consentData,
            'representativeLetterNotice' => [
                'title' => 'Surat Representatif DigiPro',
                'description' => 'Setelah request dilanjutkan dan kontrak ditandatangani, DigiPro akan menyiapkan surat representatif berdasarkan data permohonan dan dokumen yang Anda kirim.',
            ],
            'valuationObjective' => [
                'value' => ValuationObjectiveEnum::KajianNilaiPasarRange->value,
                'label' => ValuationObjectiveEnum::KajianNilaiPasarRange->label(),
            ],
            'uploadLimits' => [
                'maxFileUploads' => $maxFileUploads > 0 ? $maxFileUploads : null,
                'uploadMaxFilesize' => ini_get('upload_max_filesize'),
                'postMaxSize' => ini_get('post_max_size'),
            ],
        ];
    }
}
