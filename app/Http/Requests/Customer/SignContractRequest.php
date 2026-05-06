<?php

namespace App\Http\Requests\Customer;

class SignContractRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'agree_contract' => ['accepted'],
            'keyla_token' => ['required', 'string', 'min:6', 'max:64', 'regex:/^[A-Za-z0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'agree_contract.accepted' => 'Anda harus menyetujui dokumen sebelum menandatangani kontrak.',
            'keyla_token.required' => 'Token KEYLA wajib diisi untuk proses tanda tangan digital.',
            'keyla_token.regex' => 'Token KEYLA hanya boleh berisi huruf/angka tanpa spasi.',
        ];
    }
}
