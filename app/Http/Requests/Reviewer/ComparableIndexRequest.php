<?php

namespace App\Http\Requests\Reviewer;

use Illuminate\Validation\Rule;

class ComparableIndexRequest extends ReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'asset_id' => ['nullable', 'integer', 'min:1'],
            'is_selected' => ['nullable', 'string', Rule::in(['all', '0', '1'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }

    public function filters(): array
    {
        return [
            'q' => trim((string) $this->string('q')->toString()),
            'asset_id' => $this->integer('asset_id') ?: null,
            'is_selected' => $this->string('is_selected', 'all')->toString(),
            'per_page' => $this->perPage(15),
        ];
    }
}
