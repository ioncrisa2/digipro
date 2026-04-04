<?php

namespace App\Http\Requests\Reviewer;

class BtbWorksheetRequest extends ReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'btb_input' => ['nullable', 'array'],
            'btb_input.template_key' => ['nullable', 'string'],
            'btb_input.building_class' => ['nullable', 'string'],
            'btb_input.floor_count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'btb_input.building_area' => ['nullable', 'numeric', 'min:0'],
            'btb_input.land_area' => ['nullable', 'numeric', 'min:0'],
            'btb_input.build_year' => ['nullable', 'integer', 'min:1900', 'max:' . now()->year],
            'btb_input.renovation_year' => ['nullable', 'integer', 'min:1900', 'max:' . now()->year],
            'btb_input.design_finish_addition_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'btb_input.maintenance_adjustment_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'btb_input.incurable_depreciation_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'btb_input.functional_obsolescence_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'btb_input.economic_obsolescence_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'btb_input.market_value' => ['nullable', 'numeric', 'min:0'],
            'btb_input.subject_overrides' => ['nullable', 'array'],
            'btb_input.subject_overrides.*' => ['nullable', 'array'],
            'btb_input.subject_overrides.*.subject_material_spec' => ['nullable', 'string'],
            'btb_input.subject_overrides.*.subject_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'btb_input.subject_overrides.*.subject_volume_percent' => ['nullable', 'numeric', 'min:0'],
            'btb_input.subject_overrides.*.other_adjustment_factor' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
