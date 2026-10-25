<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Domain\Exceptions;

use FlashCommerce\Identity\Domain\Enums\IdentityErrorCode;

class AccountUnverifiedException extends IdentityDomainException
{
    public function __construct(string $message = 'Email verification is required before logging in.')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 403;
    }

    public function getErrorCode(): string
    {
        return IdentityErrorCode::AccountUnverified->value;
    }

    public function getTitle(): string
    {
        return 'Account Not Verified';
    }

    public function getDetail(): string
    {
        return $this->getMessage();
    }
}
