<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RegisterPeruriSignerUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAdminAccess();
    }

    public function rules(): array
    {
        return [
            'payload_json' => ['required', 'string', 'json'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = json_decode((string) $this->validated('payload_json'), true);

        return is_array($payload) ? $payload : [];
    }
}
