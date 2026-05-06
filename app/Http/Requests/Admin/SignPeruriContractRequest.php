<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SignPeruriContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAdminAccess();
    }

    public function rules(): array
    {
        return [
            'keyla_token' => ['required', 'string', 'min:6', 'max:64', 'regex:/^[A-Za-z0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyla_token.required' => 'Token KEYLA wajib diisi.',
            'keyla_token.regex' => 'Token KEYLA hanya boleh berisi huruf/angka tanpa spasi.',
        ];
    }
}

