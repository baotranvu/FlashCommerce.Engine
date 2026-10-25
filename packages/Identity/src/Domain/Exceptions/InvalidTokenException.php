<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Domain\Exceptions;

use FlashCommerce\Identity\Domain\Enums\IdentityErrorCode;

class InvalidTokenException extends IdentityDomainException
{
    public function __construct(string $message = 'Authentication token is missing, invalid, or expired.')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 401;
    }

    public function getErrorCode(): string
    {
        return IdentityErrorCode::InvalidToken->value;
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
