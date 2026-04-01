<?php

namespace App\Http\Requests\Admin;

use App\Enums\AssetTypeEnum;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use App\Support\AppraisalAssetFieldOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertAppraisalAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAdminAccess();
    }

    public function rules(): array
    {
        return [
            'asset_code' => ['nullable', 'string', 'max:50'],
            'asset_type' => ['required', Rule::in([
                AssetTypeEnum::TANAH->value,
                AssetTypeEnum::TANAH_BANGUNAN->value,
            ])],
            'peruntukan' => ['nullable', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::usageOptions(), 'value'))],
            'title_document' => ['nullable', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::titleDocumentOptions(), 'value'))],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'certificate_holder_name' => ['nullable', 'string', 'max:255'],
            'certificate_issued_at' => ['nullable', 'date'],
            'land_book_date' => ['nullable', 'date'],
            'document_land_area' => ['nullable', 'numeric', 'min:0'],
            'legal_notes' => ['nullable', 'string'],
            'land_shape' => ['nullable', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::landShapeOptions(), 'value'))],
            'land_position' => ['nullable', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::landPositionOptions(), 'value'))],
            'land_condition' => ['nullable', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::landConditionOptions(), 'value'))],
            'topography' => ['nullable', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::topographyOptions(), 'value'))],
            'province_id' => ['nullable', 'string', 'size:2', 'exists:provinces,id'],
            'regency_id' => ['nullable', 'string', 'size:4', 'exists:regencies,id'],
            'district_id' => ['nullable', 'string', 'size:7', 'exists:districts,id'],
            'village_id' => ['nullable', 'string', 'size:10', 'exists:villages,id'],
            'address' => ['nullable', 'string'],
            'maps_link' => ['nullable', 'string', 'max:2048'],
            'coordinates_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'coordinates_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'land_area' => ['nullable', 'numeric', 'min:0'],
            'building_area' => ['nullable', 'numeric', 'min:0'],
            'building_floors' => ['nullable', 'integer', 'min:0'],
            'build_year' => ['nullable', 'integer', 'min:1900', 'max:' . ((int) now()->format('Y') + 1)],
            'renovation_year' => ['nullable', 'integer', 'min:1900', 'max:' . ((int) now()->format('Y') + 1)],
            'frontage_width' => ['nullable', 'numeric', 'min:0'],
            'access_road_width' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalize = function (mixed $value): mixed {
            if (is_string($value)) {
                $value = trim($value);
            }

            return blank($value) ? null : $value;
        };

        $this->merge([
            'asset_code' => $normalize($this->input('asset_code')),
            'asset_type' => $normalize($this->input('asset_type')),
            'peruntukan' => $normalize($this->input('peruntukan')),
            'title_document' => $normalize($this->input('title_document')),
            'certificate_number' => $normalize($this->input('certificate_number')),
            'certificate_holder_name' => $normalize($this->input('certificate_holder_name')),
            'certificate_issued_at' => $normalize($this->input('certificate_issued_at')),
            'land_book_date' => $normalize($this->input('land_book_date')),
            'document_land_area' => $normalize($this->input('document_land_area')),
            'legal_notes' => $normalize($this->input('legal_notes')),
            'land_shape' => $normalize($this->input('land_shape')),
            'land_position' => $normalize($this->input('land_position')),
            'land_condition' => $normalize($this->input('land_condition')),
            'topography' => $normalize($this->input('topography')),
            'province_id' => $this->normalizeLocationCode($this->input('province_id'), 2),
            'regency_id' => $this->normalizeLocationCode($this->input('regency_id'), 4),
            'district_id' => $this->normalizeLocationCode($this->input('district_id'), 7),
            'village_id' => $this->normalizeLocationCode($this->input('village_id'), 10),
            'address' => $normalize($this->input('address')),
            'maps_link' => $normalize($this->input('maps_link')),
            'coordinates_lat' => $normalize($this->input('coordinates_lat')),
            'coordinates_lng' => $normalize($this->input('coordinates_lng')),
            'land_area' => $normalize($this->input('land_area')),
            'building_area' => $normalize($this->input('building_area')),
            'building_floors' => $normalize($this->input('building_floors')),
            'build_year' => $normalize($this->input('build_year')),
            'renovation_year' => $normalize($this->input('renovation_year')),
            'frontage_width' => $normalize($this->input('frontage_width')),
            'access_road_width' => $normalize($this->input('access_road_width')),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $provinceId = $this->input('province_id');
            $regencyId = $this->input('regency_id');
            $districtId = $this->input('district_id');
            $villageId = $this->input('village_id');

            if ($regencyId && ! $provinceId) {
                $validator->errors()->add('province_id', 'Provinsi wajib dipilih sebelum kabupaten/kota.');
            }

            if ($districtId && ! $regencyId) {
                $validator->errors()->add('regency_id', 'Kabupaten/kota wajib dipilih sebelum kecamatan.');
            }

            if ($villageId && ! $districtId) {
                $validator->errors()->add('district_id', 'Kecamatan wajib dipilih sebelum kelurahan.');
            }

            if ($provinceId && $regencyId) {
                $regency = Regency::query()->select(['id', 'province_id'])->find($regencyId);

                if ($regency && (string) $regency->province_id !== (string) $provinceId) {
                    $validator->errors()->add('regency_id', 'Kabupaten/kota tidak cocok dengan provinsi yang dipilih.');
                }
            }

            if ($regencyId && $districtId) {
                $district = District::query()->select(['id', 'regency_id'])->find($districtId);

                if ($district && (string) $district->regency_id !== (string) $regencyId) {
                    $validator->errors()->add('district_id', 'Kecamatan tidak cocok dengan kabupaten/kota yang dipilih.');
                }
            }

            if ($districtId && $villageId) {
                $village = Village::query()->select(['id', 'district_id'])->find($villageId);

                if ($village && (string) $village->district_id !== (string) $districtId) {
                    $validator->errors()->add('village_id', 'Kelurahan tidak cocok dengan kecamatan yang dipilih.');
                }
            }

            if ($provinceId && ! Province::query()->whereKey($provinceId)->exists()) {
                $validator->errors()->add('province_id', 'Provinsi yang dipilih tidak valid.');
            }
        });
    }

    private function normalizeLocationCode(mixed $value, int $length): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) > $length) {
            return null;
        }

        return str_pad($digits, $length, '0', STR_PAD_LEFT);
    }
}
