<?php

namespace App\Http\Requests\Account;

use App\Models\District;
use App\Models\Regency;
use App\Models\Village;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sudah dilindungi middleware auth
    }

    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email,' . $this->user()->id,
            ],
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
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $provinceId = $this->input('billing_province_id');
            $regencyId = $this->input('billing_regency_id');
            $districtId = $this->input('billing_district_id');
            $villageId = $this->input('billing_village_id');

            if ($regencyId && ! $provinceId) {
                $validator->errors()->add('billing_province_id', 'Provinsi billing wajib dipilih sebelum kabupaten/kota.');
            }

            if ($districtId && ! $regencyId) {
                $validator->errors()->add('billing_regency_id', 'Kabupaten/kota billing wajib dipilih sebelum kecamatan.');
            }

            if ($villageId && ! $districtId) {
                $validator->errors()->add('billing_district_id', 'Kecamatan billing wajib dipilih sebelum kelurahan/desa.');
            }

            if ($provinceId && $regencyId) {
                $regency = Regency::query()->select(['id', 'province_id'])->find($regencyId);

                if ($regency && (string) $regency->province_id !== (string) $provinceId) {
                    $validator->errors()->add('billing_regency_id', 'Kabupaten/kota billing tidak cocok dengan provinsi yang dipilih.');
                }
            }

            if ($regencyId && $districtId) {
                $district = District::query()->select(['id', 'regency_id'])->find($districtId);

                if ($district && (string) $district->regency_id !== (string) $regencyId) {
                    $validator->errors()->add('billing_district_id', 'Kecamatan billing tidak cocok dengan kabupaten/kota yang dipilih.');
                }
            }

            if ($districtId && $villageId) {
                $village = Village::query()->select(['id', 'district_id'])->find($villageId);

                if ($village && (string) $village->district_id !== (string) $districtId) {
                    $validator->errors()->add('billing_village_id', 'Kelurahan/desa billing tidak cocok dengan kecamatan yang dipilih.');
                }
            }
        });
    }
}
