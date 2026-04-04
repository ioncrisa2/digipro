<?php

namespace App\Http\Requests\Admin;

class GuidelineSetIndexRequest extends AdminFormRequest
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
            'status' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(bool $withPerPage = true): array
    {
        $filters = [
            'q' => trim((string) $this->query('q', '')),
            'status' => (string) $this->query('status', 'all'),
        ];

        if ($withPerPage) {
            $filters['per_page'] = (string) $this->resolvePerPage();
        }

        return $filters;
    }

    public function perPage(): int
    {
        return $this->resolvePerPage();
    }
}
