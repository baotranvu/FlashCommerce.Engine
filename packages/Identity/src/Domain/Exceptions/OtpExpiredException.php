<?php

namespace FlashCommerce\Identity\Domain\Exceptions;

use FlashCommerce\Identity\Domain\Enums\IdentityErrorCode;

class OtpExpiredException extends IdentityDomainException
{
    public function __construct(string $message = 'Verification code has expired or does not exist.')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 410;
    }

    public function getErrorCode(): string
    {
        return IdentityErrorCode::OtpExpired->value;
    }

    public function getTitle(): string
    {
        return 'Gone';
    }

    public function getDetail(): string
    {
        return $this->getMessage();
    }
}
