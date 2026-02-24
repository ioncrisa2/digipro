<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sudah dibatasi middleware guest
    }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'string', 'email', 'max:255', 'unique:users,email', 'disposable_email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'terms'                 => ['accepted']
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'             => 'Nama wajib diisi.',
            'email.required'            => 'Email wajib diisi.',
            'email.email'               => 'Format email tidak valid.',
            'email.unique'              => 'Email sudah terdaftar.',
            'password.required'         => 'Password wajib diisi.',
            'password.min'              => 'Password minimal 8 karakter.',
            'password.confirmed'        => 'Konfirmasi password tidak cocok.',
            'email.disposable_email'    => 'Gunakan email valid. Email sementara/temporary tidak diperbolehkan!!',
            'terms.accepted'            => 'Anda wajib menyetujui Terms & Conditions.',
        ];
    }
}
