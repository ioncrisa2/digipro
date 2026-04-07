<?php

namespace App\Http\Requests\Admin;

class UsersIndexRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:100'],
            'verified' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): array
    {
        return $this->filtersFromQuery([
            'q' => '',
            'role' => 'all',
            'verified' => 'all',
        ]);
    }
}
