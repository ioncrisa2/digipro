<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAppraisalPhysicalReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAdminAccess();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:save_details,mark_printed,mark_shipped,mark_delivered'],
            'courier' => ['nullable', 'string', 'max:50'],
            'tracking_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalize = fn ($value) => is_string($value) && trim($value) === '' ? null : $value;

        $this->merge([
            'action' => $normalize($this->input('action')),
            'courier' => $normalize($this->input('courier')),
            'tracking_number' => $normalize($this->input('tracking_number')),
            'notes' => $normalize($this->input('notes')),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('action') !== 'mark_shipped') {
                return;
            }

            if (! filled($this->input('courier'))) {
                $validator->errors()->add('courier', 'Nama kurir wajib diisi sebelum hard copy ditandai dikirim.');
            }

            if (! filled($this->input('tracking_number'))) {
                $validator->errors()->add('tracking_number', 'Nomor resi wajib diisi sebelum hard copy ditandai dikirim.');
            }
        });
    }
}
