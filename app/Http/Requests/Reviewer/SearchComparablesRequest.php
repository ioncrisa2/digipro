<?php

namespace App\Http\Requests\Reviewer;

class SearchComparablesRequest extends ReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'range_km' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'limit' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }
}
