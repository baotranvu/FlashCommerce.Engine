<?php

namespace FlashCommerce\Identity\Domain\Verification;

use FlashCommerce\Identity\Domain\Entities\UserEntity;

interface VerificationStrategyInterface
{
    /**
     * Dispatch challenge (e.g. 6-digit OTP code, signed link) to the user.
     */
    public function sendChallenge(UserEntity $user): VerificationResult;

    /**
     * Verify the presented credential (e.g. OTP code).
     */
    public function verify(UserEntity $user, string $credential): VerificationResult;
}
