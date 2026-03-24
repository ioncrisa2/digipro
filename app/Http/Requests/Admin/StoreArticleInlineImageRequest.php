<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreArticleInlineImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:4096'],
            'alt' => ['nullable', 'string', 'max:255'],
        ];
    }
}
