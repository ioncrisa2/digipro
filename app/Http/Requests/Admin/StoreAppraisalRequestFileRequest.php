<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppraisalRequestFileRequest extends FormRequest
{
    public const ALLOWED_TYPES = [
        'npwp',
        'representative',
        'permission',
        'other_request_document',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(self::ALLOWED_TYPES)],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:15360'],
        ];
    }
}
