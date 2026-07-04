<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MobileAppraisalFileUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $photo = str_starts_with((string) $this->input('type'), 'photo_');

        return [
            'type' => ['required', Rule::in([
                'doc_pbb',
                'doc_imb',
                'doc_old_report',
                'doc_certs',
                'photo_access_road',
                'photo_front',
                'photo_interior',
            ])],
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => [
                'required',
                'file',
                $photo ? 'max:15360' : 'max:10240',
                $photo ? 'mimes:jpg,jpeg,png,webp' : 'mimes:pdf,jpg,jpeg,png',
                $photo
                    ? 'mimetypes:image/jpeg,image/png,image/webp'
                    : 'mimetypes:application/pdf,image/jpeg,image/png',
            ],
        ];
    }
}
