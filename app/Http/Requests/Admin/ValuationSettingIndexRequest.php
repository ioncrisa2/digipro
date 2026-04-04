<?php

namespace App\Http\Requests\Admin;

class ValuationSettingIndexRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->hasAdminAccess() || $user?->isReviewer());
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'guideline_set_id' => ['nullable', 'string', 'max:20'],
            'year' => ['nullable', 'string', 'max:10'],
            'key' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): array
    {
        return [
            'q' => trim((string) $this->query('q', '')),
            'guideline_set_id' => (string) $this->query('guideline_set_id', 'all'),
            'year' => (string) $this->query('year', 'all'),
            'key' => (string) $this->query('key', 'all'),
            'per_page' => (string) $this->resolvePerPage(),
        ];
    }

    public function perPage(): int
    {
        return $this->resolvePerPage();
    }
}
