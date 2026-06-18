<?php

namespace App\Http\Requests\Customer;

class SubmitCustomerSignatureSpecimenRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'signature_image' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'signature_image.required' => 'Tanda tangan wajib dibuat atau diunggah.',
            'signature_image.image' => 'Tanda tangan harus berupa gambar.',
            'signature_image.mimes' => 'Tanda tangan harus berformat JPG atau PNG.',
            'signature_image.max' => 'Ukuran gambar tanda tangan maksimal 5 MB.',
        ];
    }
}
