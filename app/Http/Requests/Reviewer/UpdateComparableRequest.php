<?php

namespace App\Http\Requests\Reviewer;

class UpdateComparableRequest extends ReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'is_selected' => ['required', 'boolean'],
            'manual_rank' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
