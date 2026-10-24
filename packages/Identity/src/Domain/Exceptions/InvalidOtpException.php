<?php

namespace FlashCommerce\Identity\Domain\Exceptions;

use FlashCommerce\Identity\Domain\Enums\IdentityErrorCode;

class InvalidOtpException extends IdentityDomainException
{
    public function __construct(
        private readonly int $remainingAttempts = 0
    ) {
        parent::__construct("Invalid verification code. You have {$remainingAttempts} attempt(s) remaining.");
    }

    public function getStatusCode(): int
    {
        return 422;
    }

    public function getErrorCode(): string
    {
        return IdentityErrorCode::InvalidOtp->value;
    }

    public function getTitle(): string
    {
        return 'Unprocessable Entity';
    }

    public function getDetail(): string
    {
        return $this->getMessage();
    }
}
