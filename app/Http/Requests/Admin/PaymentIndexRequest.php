<?php

namespace App\Http\Requests\Admin;

class PaymentIndexRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): array
    {
        return $this->filtersFromQuery([
            'q' => '',
            'status' => 'all',
        ]);
    }
}
