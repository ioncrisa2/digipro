<?php

namespace App\Http\Requests\Admin;

class LocationIdPreviewRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->hasAdminAccess() || $user?->isReviewer());
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:province,regency,district,village'],
            'province_id' => ['nullable', 'string', 'size:2'],
            'regency_id' => ['nullable', 'string', 'size:4'],
            'district_id' => ['nullable', 'string', 'size:7'],
        ];
    }
}
