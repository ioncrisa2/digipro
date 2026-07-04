<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class MobileAvatarUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
