<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\District;
use App\Models\Regency;
use App\Models\Village;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MobileProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user())],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'billing_recipient_name' => ['nullable', 'string', 'max:255'],
            'billing_province_id' => ['nullable', 'string', 'size:2', 'exists:provinces,id'],
            'billing_regency_id' => ['nullable', 'string', 'size:4', 'exists:regencies,id'],
            'billing_district_id' => ['nullable', 'string', 'size:7', 'exists:districts,id'],
            'billing_village_id' => ['nullable', 'string', 'size:10', 'exists:villages,id'],
            'billing_postal_code' => ['nullable', 'string', 'max:10'],
            'billing_address_detail' => ['nullable', 'string'],
            'billing_npwp' => ['nullable', 'string', 'max:40'],
            'billing_nik' => ['nullable', 'string', 'max:40'],
            'billing_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $provinceId = $this->string('billing_province_id')->toString();
            $regencyId = $this->string('billing_regency_id')->toString();
            $districtId = $this->string('billing_district_id')->toString();
            $villageId = $this->string('billing_village_id')->toString();

            if ($regencyId !== '' && ($provinceId === '' || ! Regency::query()->whereKey($regencyId)->where('province_id', $provinceId)->exists())) {
                $validator->errors()->add('billing_regency_id', 'Kabupaten/kota billing tidak cocok dengan provinsi yang dipilih.');
            }

            if ($districtId !== '' && ($regencyId === '' || ! District::query()->whereKey($districtId)->where('regency_id', $regencyId)->exists())) {
                $validator->errors()->add('billing_district_id', 'Kecamatan billing tidak cocok dengan kabupaten/kota yang dipilih.');
            }

            if ($villageId !== '' && ($districtId === '' || ! Village::query()->whereKey($villageId)->where('district_id', $districtId)->exists())) {
                $validator->errors()->add('billing_village_id', 'Kelurahan/desa billing tidak cocok dengan kecamatan yang dipilih.');
            }
        }];
    }
}
