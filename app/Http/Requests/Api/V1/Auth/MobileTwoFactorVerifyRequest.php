<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MobileTwoFactorVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'size:64'],
            'code' => ['nullable', 'required_without:recovery_code', 'digits:6'],
            'recovery_code' => ['nullable', 'required_without:code', 'string'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (filled($this->input('code')) && filled($this->input('recovery_code'))) {
                    $validator->errors()->add('recovery_code', 'Pilih kode autentikasi atau recovery code, bukan keduanya.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'challenge_token.required' => 'Challenge token wajib diisi.',
            'code.required_without' => 'Kode autentikasi atau recovery code wajib diisi.',
            'code.digits' => 'Kode autentikasi harus terdiri dari 6 digit.',
            'recovery_code.required_without' => 'Kode autentikasi atau recovery code wajib diisi.',
        ];
    }
}
