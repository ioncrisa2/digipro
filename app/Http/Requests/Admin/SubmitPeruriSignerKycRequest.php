<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPeruriSignerKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAdminAccess();
    }

    public function rules(): array
    {
        return [
            'kyc_video' => ['required', 'file', 'mimes:mp4,mov,avi,webm,mkv', 'max:51200'],
            'payload_json' => ['nullable', 'string', 'json'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $json = (string) ($this->validated('payload_json') ?? '');
        if ($json === '') {
            return [];
        }

        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : [];
    }
}
