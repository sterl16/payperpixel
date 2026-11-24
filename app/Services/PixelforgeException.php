<?php

namespace App\Services;

use RuntimeException;

class PixelforgeException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $errorCode,
        string $message,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status);
    }

    // rate limits, overloaded and server errors don't create anything so it's safe to retry
    public function isRetryable(): bool
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }
}
