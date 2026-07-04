<?php

namespace App\Http\Requests\Customer;

class SignContractRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        if (config('signatures.contract_mode') === 'canvas_demo') {
            return [
                'agree_contract' => ['accepted'],
                'signature_image' => [
                    'required',
                    'file',
                    'image',
                    'mimes:png',
                    'mimetypes:image/png',
                    'max:1024',
                    'dimensions:min_width=100,min_height=50,max_width=3000,max_height=1500',
                ],
            ];
        }

        return [
            'agree_contract' => ['accepted'],
            'keyla_token' => ['required', 'string', 'min:6', 'max:64', 'regex:/^[A-Za-z0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'agree_contract.accepted' => 'Anda harus menyetujui dokumen sebelum menandatangani kontrak.',
            'keyla_token.required' => 'Kode dari aplikasi KEYLA wajib diisi untuk proses tanda tangan digital.',
            'keyla_token.regex' => 'Kode KEYLA hanya boleh berisi huruf/angka tanpa spasi.',
            'signature_image.required' => 'Tanda tangan wajib digambar sebelum kontrak diproses.',
            'signature_image.image' => 'Tanda tangan harus berupa gambar PNG yang valid.',
            'signature_image.mimes' => 'Tanda tangan canvas harus menggunakan format PNG.',
            'signature_image.max' => 'Ukuran tanda tangan maksimal 1 MB.',
            'signature_image.dimensions' => 'Dimensi tanda tangan canvas tidak valid.',
        ];
    }
}
