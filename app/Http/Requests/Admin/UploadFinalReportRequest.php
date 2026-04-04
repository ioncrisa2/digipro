<?php

namespace App\Http\Requests\Admin;

class UploadFinalReportRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'report_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }
}
