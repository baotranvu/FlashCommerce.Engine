<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Domain\Exceptions;

use FlashCommerce\Identity\Domain\Enums\IdentityErrorCode;

class TokenReplayDetectedException extends IdentityDomainException
{
    public function __construct(string $message = 'Token reuse detected. All sessions in this family have been terminated.')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 401;
    }

    public function getErrorCode(): string
    {
        return IdentityErrorCode::TokenReplayDetected->value;
    }

    public function getTitle(): string
    {
        return 'Unauthorized';
    }

    public function getDetail(): string
    {
        return $this->getMessage();
    }
}
