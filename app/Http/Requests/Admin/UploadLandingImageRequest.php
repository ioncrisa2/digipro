<?php

namespace App\Http\Requests\Admin;

class UploadLandingImageRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:20480'],
        ];
    }
}
