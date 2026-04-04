<?php

namespace App\Http\Requests\Admin;

class LocationOptionsRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->hasAdminAccess() || $user?->isReviewer());
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:provinces,regencies,districts'],
            'province_id' => ['nullable', 'string', 'size:2'],
            'regency_id' => ['nullable', 'string', 'size:4'],
        ];
    }
}
