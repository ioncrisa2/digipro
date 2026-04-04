<?php

namespace App\Http\Requests\Reviewer;

use App\Support\AppraisalAssetFieldOptions;
use Illuminate\Validation\Rule;

class UpdateAssetGeneralDataRequest extends ReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'peruntukan' => ['required', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::usageOptions(), 'value'))],
            'title_document' => ['required', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::titleDocumentOptions(), 'value'))],
            'land_shape' => ['required', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::landShapeOptions(), 'value'))],
            'land_position' => ['required', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::landPositionOptions(), 'value'))],
            'land_condition' => ['required', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::landConditionOptions(), 'value'))],
            'topography' => ['required', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::topographyOptions(), 'value'))],
            'frontage_width' => ['required', 'numeric', 'min:0'],
            'access_road_width' => ['required', 'numeric', 'min:0'],
            'build_year' => ['nullable', 'integer', 'min:1900', 'max:' . now()->year],
        ];
    }
}
