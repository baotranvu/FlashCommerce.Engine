<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Domain\Exceptions;

use FlashCommerce\Identity\Domain\Enums\IdentityErrorCode;

class InvalidCredentialsException extends IdentityDomainException
{
    public function __construct(string $message = 'Invalid credentials.')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 401;
    }

    public function getErrorCode(): string
    {
        return IdentityErrorCode::InvalidCredentials->value;
    }

    public function getTitle(): string
    {
        return 'Invalid Credentials';
    }

    public function getDetail(): string
    {
        return $this->getMessage();
    }
}
