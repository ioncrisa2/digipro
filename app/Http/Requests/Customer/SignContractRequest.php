<?php

namespace App\Http\Requests\Customer;

class SignContractRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'agree_contract' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'agree_contract.accepted' => 'Anda harus menyetujui dokumen sebelum menandatangani kontrak.',
        ];
    }
}
