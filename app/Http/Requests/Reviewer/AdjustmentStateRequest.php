<?php

namespace App\Http\Requests\Reviewer;

class AdjustmentStateRequest extends ReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'adjustment_inputs' => ['nullable', 'array'],
            'custom_adjustment_factors' => ['nullable', 'array'],
            'general_inputs' => ['nullable', 'array'],
            'general_inputs.*.assumed_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'general_inputs.*.material_quality_adj' => ['nullable', 'numeric', 'min:0.01', 'max:10'],
            'general_inputs.*.maintenance_adj_delta' => ['nullable', 'numeric', 'min:-100', 'max:100'],
        ];
    }
}
