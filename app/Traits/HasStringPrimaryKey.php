<?php

namespace App\Traits;

trait HasStringPrimaryKey
{
    public function initializeHasStringPrimaryKey(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }
}
