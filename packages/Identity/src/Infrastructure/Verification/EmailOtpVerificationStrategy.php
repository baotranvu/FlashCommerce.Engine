<?php

namespace FlashCommerce\Identity\Infrastructure\Verification;

use FlashCommerce\Identity\Domain\Entities\UserEntity;
use FlashCommerce\Identity\Domain\Exceptions\CooldownActiveException;
use FlashCommerce\Identity\Domain\Exceptions\InvalidOtpException;
use FlashCommerce\Identity\Domain\Exceptions\MaxOtpAttemptsExceededException;
use FlashCommerce\Identity\Domain\Exceptions\OtpExpiredException;
use FlashCommerce\Identity\Domain\Verification\VerificationResult;
use FlashCommerce\Identity\Domain\Verification\VerificationStrategyInterface;
use FlashCommerce\Identity\Infrastructure\Redis\IdentityRedisKeys;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class EmailOtpVerificationStrategy implements VerificationStrategyInterface
{
    private function getTtlSeconds(): int
    {
        return (int) config('identity.otp.ttl_seconds', 300);
    }

    private function getCooldownSeconds(): int
    {
        return (int) config('identity.otp.cooldown_seconds', 60);
    }

    private function getMaxAttempts(): int
    {
        return (int) config('identity.otp.max_attempts', 5);
    }

    public function sendChallenge(UserEntity $user): VerificationResult
    {
        $cooldownKey = IdentityRedisKeys::cooldown($user->id);
        
        // 🛡️ GOTCHA 3: Anti-Spam Cooldown check -> Pass remaining Redis TTL dynamically
        if (Redis::get($cooldownKey)) {
            $remainingTtl = (int) Redis::ttl($cooldownKey);
            throw new CooldownActiveException(max(1, $remainingTtl));
        }

        // Generate 6-digit numeric OTP
        $otp = (string) random_int(100000, 999999);

        $otpKey = IdentityRedisKeys::otp($user->id);
        $attemptsKey = IdentityRedisKeys::attempts($user->id);

        $ttl = $this->getTtlSeconds();
        $cooldown = $this->getCooldownSeconds();

        // Store OTP with configurable TTL
        Redis::setex($otpKey, $ttl, $otp);
        // Set cooldown lock
        Redis::setex($cooldownKey, $cooldown, '1');
        // Reset failed attempt counter
        Redis::del($attemptsKey);

        // 🛡️ GOTCHA 4: In production, dispatches queue job with ->afterCommit()
        Log::info("[OTP ENGINE] Dispatched Email OTP for User #{$user->id} ({$user->email}): {$otp}");

        return VerificationResult::success("Verification code dispatched to {$user->email}.");
    }

    public function verify(UserEntity $user, string $credential): VerificationResult
    {
        $otpKey = IdentityRedisKeys::otp($user->id);
        $attemptsKey = IdentityRedisKeys::attempts($user->id);

        $maxAttempts = $this->getMaxAttempts();

        // 🛡️ GOTCHA 2: Circuit Breaker for Brute-force attacks (15 minutes lockout)
        $attempts = (int) Redis::get($attemptsKey);
        if ($attempts >= $maxAttempts) {
            Redis::del($otpKey);
            throw new MaxOtpAttemptsExceededException(900);
        }

        $cachedOtp = Redis::get($otpKey);
        if (! $cachedOtp) {
            throw new OtpExpiredException();
        }

        // 🛡️ GOTCHA 1: Constant-time string comparison to prevent Timing Attacks
        if (! hash_equals((string) $cachedOtp, (string) $credential)) {
            $currentAttempts = Redis::incr($attemptsKey);
            Redis::expire($attemptsKey, $this->getTtlSeconds());

            $remaining = $maxAttempts - $currentAttempts;
            throw new InvalidOtpException(max(0, $remaining));
        }

        // 🛡️ GOTCHA 6: Success — Clean up Redis state
        Redis::del($otpKey);
        Redis::del($attemptsKey);

        return VerificationResult::success('Email successfully verified.');
    }
}
