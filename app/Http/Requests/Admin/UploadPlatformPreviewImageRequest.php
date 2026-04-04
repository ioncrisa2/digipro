<?php

namespace App\Http\Requests\Admin;

class UploadPlatformPreviewImageRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:6144'],
        ];
    }
}
