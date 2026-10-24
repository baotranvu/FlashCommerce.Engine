<?php

namespace FlashCommerce\Identity\Domain\Exceptions;

use FlashCommerce\Identity\Domain\Enums\IdentityErrorCode;

class MaxOtpAttemptsExceededException extends IdentityDomainException
{
    public function __construct(
        private readonly int $retryAfterSeconds = 900,
        string $message = 'Too many failed attempts. This verification code has been permanently invalidated.'
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 429;
    }

    public function getErrorCode(): string
    {
        return IdentityErrorCode::MaxAttemptsExceeded->value;
    }

    public function getTitle(): string
    {
        return 'Too Many Requests';
    }

    public function getDetail(): string
    {
        return $this->getMessage();
    }

    public function getHeaders(): array
    {
        return [
            'Retry-After' => (string) $this->retryAfterSeconds,
        ];
    }

    public function getExtensions(): array
    {
        return [
            'retry_after_seconds' => $this->retryAfterSeconds,
        ];
    }
}
