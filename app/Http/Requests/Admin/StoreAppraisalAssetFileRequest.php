<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppraisalAssetFileRequest extends FormRequest
{
    private const DOCUMENT_TYPES = [
        'doc_pbb',
        'doc_imb',
        'doc_certs',
    ];

    private const PHOTO_TYPES = [
        'photo_access_road',
        'photo_front',
        'photo_interior',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAdminAccess();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_merge(self::DOCUMENT_TYPES, self::PHOTO_TYPES))],
            'file' => ['required', 'file', 'max:15360'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $file = $this->file('file');
            $type = (string) $this->input('type');

            if (! $file) {
                return;
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());
            $documentExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            $photoExtensions = ['jpg', 'jpeg', 'png'];

            if (in_array($type, self::DOCUMENT_TYPES, true) && ! in_array($extension, $documentExtensions, true)) {
                $validator->errors()->add('file', 'Dokumen aset hanya menerima PDF atau gambar JPG/PNG.');
            }

            if (in_array($type, self::PHOTO_TYPES, true) && ! in_array($extension, $photoExtensions, true)) {
                $validator->errors()->add('file', 'Foto aset hanya menerima file JPG atau PNG.');
            }
        });
    }
}
