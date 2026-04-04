<?php

namespace App\Http\Requests\Customer;

class AppraisalCreatePageRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'province_id' => ['nullable', 'string', 'max:20'],
            'regency_id' => ['nullable', 'string', 'max:20'],
            'district_id' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function provinceId(): ?string
    {
        $value = trim((string) $this->get('province_id', ''));

        return $value !== '' ? $value : null;
    }

    public function regencyId(): ?string
    {
        $value = trim((string) $this->get('regency_id', ''));

        return $value !== '' ? $value : null;
    }

    public function districtId(): ?string
    {
        $value = trim((string) $this->get('district_id', ''));

        return $value !== '' ? $value : null;
    }
}
