<?php

namespace FlashCommerce\Identity\Domain\Exceptions;

use FlashCommerce\Identity\Domain\Enums\IdentityErrorCode;

class CooldownActiveException extends IdentityDomainException
{
    public function __construct(
        private readonly int $retryAfterSeconds = 60
    ) {
        parent::__construct("Please wait {$this->retryAfterSeconds} second(s) before requesting another verification code.");
    }

    public function getStatusCode(): int
    {
        return 429;
    }

    public function getErrorCode(): string
    {
        return IdentityErrorCode::CooldownActive->value;
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
