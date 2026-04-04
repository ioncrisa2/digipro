<?php

namespace App\Http\Requests\Admin;

class SimpleStatusIndexRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function filters(): array
    {
        return [
            'q' => trim((string) $this->query('q', '')),
            'status' => (string) $this->query('status', 'all'),
        ];
    }
}
