<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

class MobileProfileLocationOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['provinces', 'regencies', 'districts', 'villages'])],
            'province_id' => [$this->requiredFor('regencies'), 'nullable', 'string', 'size:2', 'exists:provinces,id'],
            'regency_id' => [$this->requiredFor('districts'), 'nullable', 'string', 'size:4', 'exists:regencies,id'],
            'district_id' => [$this->requiredFor('villages'), 'nullable', 'string', 'size:7', 'exists:districts,id'],
        ];
    }

    private function requiredFor(string $type): RequiredIf
    {
        return Rule::requiredIf($this->string('type')->toString() === $type);
    }
}
