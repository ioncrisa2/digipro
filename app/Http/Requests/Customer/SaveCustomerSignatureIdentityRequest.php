<?php

namespace App\Http\Requests\Customer;

use Illuminate\Validation\Rule;

class SaveCustomerSignatureIdentityRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        $hasStoredKtpPhoto = (bool) $this->user()?->signatureProfile?->ktp_photo_path;
        $isWna = $this->boolean('is_wna');

        return [
            'is_wna' => ['required', 'boolean'],
            'peruri_email' => ['required', 'email', 'max:255'],
            'peruri_phone' => ['required', 'string', 'max:32'],
            'nik' => $isWna
                ? ['required', 'string', 'min:6', 'max:32', 'regex:/^[A-Za-z0-9]+$/']
                : ['required', 'digits:16'],
            'reference_province_id' => ['required', 'string', 'max:32'],
            'reference_city_id' => ['required', 'string', 'max:32'],
            'gender' => ['required', Rule::in(['M', 'F'])],
            'place_of_birth' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'address' => ['required', 'string', 'max:500'],
            'ktp_photo' => [$hasStoredKtpPhoto ? 'nullable' : 'required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'peruri_email.required' => 'Email aktif wajib diisi.',
            'peruri_email.email' => 'Format email belum benar.',
            'peruri_phone.required' => 'Nomor HP aktif wajib diisi.',
            'nik.required' => 'Nomor identitas wajib diisi.',
            'nik.digits' => 'NIK wajib 16 digit angka.',
            'nik.regex' => 'Nomor KITAS/KITAP hanya boleh berisi huruf dan angka.',
            'reference_province_id.required' => 'Pilih provinsi sesuai identitas.',
            'reference_city_id.required' => 'Pilih kabupaten atau kota sesuai identitas.',
            'gender.required' => 'Pilih jenis kelamin.',
            'gender.in' => 'Pilih jenis kelamin yang tersedia.',
            'place_of_birth.required' => 'Tempat lahir wajib diisi.',
            'date_of_birth.required' => 'Tanggal lahir wajib diisi.',
            'date_of_birth.date_format' => 'Tanggal lahir belum valid.',
            'date_of_birth.before' => 'Tanggal lahir harus sebelum hari ini.',
            'address.required' => 'Alamat sesuai identitas wajib diisi.',
            'ktp_photo.required' => 'Foto identitas wajib diunggah.',
            'ktp_photo.image' => 'Foto identitas harus berupa gambar.',
            'ktp_photo.mimes' => 'Foto identitas harus berformat JPG atau PNG.',
            'ktp_photo.max' => 'Ukuran foto identitas maksimal 5 MB.',
        ];
    }
}
