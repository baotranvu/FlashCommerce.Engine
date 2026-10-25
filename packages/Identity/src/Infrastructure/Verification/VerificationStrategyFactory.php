<?php

namespace FlashCommerce\Identity\Infrastructure\Verification;

use FlashCommerce\Identity\Domain\Verification\VerificationStrategyInterface;
use InvalidArgumentException;

class VerificationStrategyFactory
{
    public function make(string $channel = 'email_otp'): VerificationStrategyInterface
    {
        return match ($channel) {
            'email_otp'       => app(EmailOtpVerificationStrategy::class),
            'write_deferred'  => app(Stage3WriteDeferredVerificationStrategy::class),
            default           => throw new InvalidArgumentException("Unsupported verification channel [{$channel}]"),
        };
    }
}
