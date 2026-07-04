<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Enums\AssetTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MobileAppraisalAssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assetTypeRule = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'asset_type' => [$assetTypeRule, Rule::enum(AssetTypeEnum::class)],
            'peruntukan' => ['sometimes', 'nullable', 'string', 'max:100'],
            'title_document' => ['sometimes', 'nullable', 'string', 'max:100'],
            'land_shape' => ['sometimes', 'nullable', 'string', 'max:100'],
            'land_position' => ['sometimes', 'nullable', 'string', 'max:100'],
            'land_condition' => ['sometimes', 'nullable', 'string', 'max:100'],
            'topography' => ['sometimes', 'nullable', 'string', 'max:100'],
            'province_id' => ['sometimes', 'nullable', 'string', 'max:2', 'exists:provinces,id'],
            'regency_id' => ['sometimes', 'nullable', 'string', 'max:4', 'exists:regencies,id'],
            'district_id' => ['sometimes', 'nullable', 'string', 'max:7', 'exists:districts,id'],
            'village_id' => ['sometimes', 'nullable', 'string', 'max:10', 'exists:villages,id'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'maps_link' => ['sometimes', 'nullable', 'url:http,https', 'max:1000'],
            'coordinates_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'coordinates_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'land_area' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'building_area' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'building_floors' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:200'],
            'build_year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'renovation_year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'frontage_width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'access_road_width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
