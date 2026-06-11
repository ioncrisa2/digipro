<?php

namespace App\Services\Peruri;

use RuntimeException;

class PeruriApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $body
     */
    public function __construct(
        public readonly string $peruriStatus,
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?array $body = null,
    ) {
        parent::__construct($message);
    }
}
