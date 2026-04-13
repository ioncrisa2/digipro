<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class ActivityLogIndexRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && (bool) $this->user()?->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'workspace' => ['nullable', Rule::in(['all', 'admin', 'customer', 'reviewer', 'account', 'auth', 'public'])],
            'method' => ['nullable', Rule::in(['all', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'event_type' => ['nullable', Rule::in(['all', 'visit', 'action'])],
            'status' => ['nullable', Rule::in(['all', 'success', 'error'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return $this->resolvePerPage(25, 100);
    }

    public function filters(): array
    {
        return $this->filtersFromQuery([
            'q' => '',
            'workspace' => 'all',
            'method' => 'all',
            'event_type' => 'all',
            'status' => 'all',
            'date_from' => '',
            'date_to' => '',
        ]);
    }
}
