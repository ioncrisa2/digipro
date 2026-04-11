<?php

namespace App\Http\Requests\Admin;

class RestoreAppraisalBackupRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'backup_zip' => ['required', 'file', 'mimes:zip', 'max:102400'],
        ];
    }
}
