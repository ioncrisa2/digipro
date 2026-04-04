<?php

namespace App\Http\Requests\Admin;

class RoleIndexRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'guard' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): array
    {
        return [
            'q' => trim((string) $this->query('q', '')),
            'guard' => (string) $this->query('guard', 'all'),
            'per_page' => (string) $this->resolvePerPage(),
        ];
    }

    public function perPage(): int
    {
        return $this->resolvePerPage();
    }
}
