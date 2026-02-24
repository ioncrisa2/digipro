<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasRequestNumber
{
    protected static function bootHasRequestNumber(): void
    {
        static::creating(function ($model) {
            if (! empty($model->request_number)) {
                return;
            }

            $model->request_number = $model->generateRequestNumber();
        });
    }

    protected function generateRequestNumber(): string
    {
        $year = now()->year;

        for ($i = 0; $i < 5; $i++) {
            $candidate = 'REQ-' . $year . '-' . Str::upper(Str::random(6));

            if (! $this->requestNumberExists($candidate)) {
                return $candidate;
            }
        }

        return 'REQ-' . $year . '-' . Str::upper((string) Str::ulid());
    }

    protected function requestNumberExists(string $candidate): bool
    {
        return static::query()
            ->where('request_number', $candidate)
            ->exists();
    }
}
