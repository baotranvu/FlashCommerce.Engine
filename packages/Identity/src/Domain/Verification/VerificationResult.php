<?php

namespace FlashCommerce\Identity\Domain\Verification;

readonly class VerificationResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?string $errorCode = null
    ) {}

    public static function success(string $message = 'Verification successful.'): self
    {
        return new self(success: true, message: $message);
    }

    public static function failure(string $message, string $errorCode = 'VERIFICATION_FAILED'): self
    {
        return new self(success: false, message: $message, errorCode: $errorCode);
    }
}
