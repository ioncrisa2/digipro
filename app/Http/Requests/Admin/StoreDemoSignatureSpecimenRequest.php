<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDemoSignatureSpecimenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAdminAccess();
    }

    public function rules(): array
    {
        return [
            'signature_image' => [
                'required',
                'file',
                'image',
                'mimes:png,jpg,jpeg',
                'mimetypes:image/png,image/jpeg',
                'max:2048',
                'dimensions:min_width=100,min_height=50,max_width=3000,max_height=1500',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->route('reportSigner')?->role !== 'public_appraiser') {
                    $validator->errors()->add(
                        'signature_image',
                        'Tanda tangan demo hanya dapat disetel untuk Penilai Publik.',
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'signature_image.required' => 'Gambar tanda tangan wajib dipilih atau digambar.',
            'signature_image.image' => 'File tanda tangan harus berupa gambar yang valid.',
            'signature_image.mimes' => 'Tanda tangan hanya mendukung PNG atau JPG.',
            'signature_image.max' => 'Ukuran tanda tangan maksimal 2 MB.',
            'signature_image.dimensions' => 'Dimensi gambar tanda tangan tidak valid.',
        ];
    }
}
