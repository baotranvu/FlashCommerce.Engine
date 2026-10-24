<?php

namespace FlashCommerce\Identity\Domain\Exceptions;

use FlashCommerce\Identity\Domain\Enums\IdentityErrorCode;

class DatabaseUnavailableException extends IdentityDomainException
{
    public function __construct(string $message = 'The database service is temporarily unavailable. Please retry later.')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 503;
    }

    public function getErrorCode(): string
    {
        return IdentityErrorCode::DatabaseUnavailable->value;
    }

    public function getTitle(): string
    {
        return 'Service Unavailable';
    }

    public function getDetail(): string
    {
        return $this->getMessage();
    }
}
