<?php

namespace App\Http\Requests\Reviewer;

class SyncComparablesRequest extends ReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['array'],
        ];
    }

    public function filteredItems(): array
    {
        return collect((array) $this->validated('items', []))
            ->filter(fn ($item): bool => is_array($item) && isset($item['id']))
            ->values()
            ->all();
    }
}
