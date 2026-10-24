<?php

namespace FlashCommerce\Identity\Domain\Exceptions;

use FlashCommerce\Identity\Domain\Enums\IdentityErrorCode;

class UserAlreadyExistsException extends IdentityDomainException
{
    public function __construct(string $message = 'A user with this email address already exists.')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 409;
    }

    public function getErrorCode(): string
    {
        return IdentityErrorCode::UserAlreadyExists->value;
    }

    public function getTitle(): string
    {
        return 'Conflict';
    }

    public function getDetail(): string
    {
        return $this->getMessage();
    }
}
