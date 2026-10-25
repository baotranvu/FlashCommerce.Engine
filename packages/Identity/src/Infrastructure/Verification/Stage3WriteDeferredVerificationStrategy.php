<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Infrastructure\Verification;

use FlashCommerce\Identity\Domain\Entities\UserEntity;
use FlashCommerce\Identity\Domain\Exceptions\InvalidOtpException;
use FlashCommerce\Identity\Domain\Exceptions\MaxOtpAttemptsExceededException;
use FlashCommerce\Identity\Domain\Exceptions\OtpExpiredException;
use FlashCommerce\Identity\Domain\Verification\VerificationResult;
use FlashCommerce\Identity\Domain\Verification\VerificationStrategyInterface;
use FlashCommerce\Identity\Infrastructure\Redis\IdentityRedisKeys;
use Illuminate\Support\Facades\Redis;

class Stage3WriteDeferredVerificationStrategy implements VerificationStrategyInterface
{
    private ?string $luaScript = null;

    public function __construct(
        private readonly EmailOtpVerificationStrategy $otpStrategy
    ) {}

    public function sendChallenge(UserEntity $user): VerificationResult
    {
        return $this->otpStrategy->sendChallenge($user);
    }

    public function verify(UserEntity $user, string $credential): VerificationResult
    {
        $maxAttempts = (int) config('identity.otp.max_attempts', 5);
        $script = $this->getLuaScript();

        $keys = [
            IdentityRedisKeys::otp($user->id),
            IdentityRedisKeys::attempts($user->id),
            IdentityRedisKeys::cooldown($user->id),
            "user_verified:{$user->id}",
            'stream:user_verified',
        ];

        $streamPayload = json_encode([
            'user_id' => $user->id,
            'email' => $user->email,
            'verified_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        $args = [
            $credential,
            (string) $maxAttempts,
            '86400',
            $streamPayload,
        ];

        // Execute atomic single-hop verification via Redis eval
        $res = Redis::eval($script, count($keys), ...$keys, ...$args);
        $resultCode = (int) $res;

        return match ($resultCode) {
            1 => VerificationResult::success('Email verified successfully via atomic write-deferred pipeline.'),
            0 => $this->handleInvalidOtp($user->id, $maxAttempts),
            -1 => throw new MaxOtpAttemptsExceededException(900),
            -2 => throw new OtpExpiredException(),
            default => throw new InvalidOtpException(0),
        };
    }

    private function handleInvalidOtp(int $userId, int $maxAttempts): never
    {
        $attemptsKey = IdentityRedisKeys::attempts($userId);
        $currentAttempts = (int) Redis::get($attemptsKey);
        $remaining = max(0, $maxAttempts - $currentAttempts);

        throw new InvalidOtpException($remaining);
    }

    private function getLuaScript(): string
    {
        if ($this->luaScript === null) {
            $path = __DIR__ . '/../Redis/Lua/verify_otp_atomic.lua';
            $this->luaScript = (string) file_get_contents($path);
        }

        return $this->luaScript;
    }
}
