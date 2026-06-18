<?php

namespace App\Http\Requests\Customer;

class SubmitCustomerSignatureKycRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'kyc_video' => ['required', 'file', 'mimes:mp4,mov,avi,webm,mkv', 'max:51200'],
        ];
    }

    public function messages(): array
    {
        return [
            'kyc_video.required' => 'Video wajah wajib direkam atau diunggah.',
            'kyc_video.mimes' => 'Format video belum didukung. Gunakan MP4, MOV, AVI, WEBM, atau MKV.',
            'kyc_video.max' => 'Ukuran video maksimal 50 MB.',
        ];
    }
}
