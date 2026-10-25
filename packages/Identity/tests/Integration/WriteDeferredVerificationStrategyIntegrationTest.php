<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\Integration;

use FlashCommerce\Identity\Domain\Entities\UserEntity;
use FlashCommerce\Identity\Domain\Exceptions\InvalidOtpException;
use FlashCommerce\Identity\Domain\Exceptions\MaxOtpAttemptsExceededException;
use FlashCommerce\Identity\Domain\Exceptions\OtpExpiredException;
use FlashCommerce\Identity\Domain\Repositories\UserRepositoryInterface;
use FlashCommerce\Identity\Infrastructure\Redis\IdentityRedisKeys;
use FlashCommerce\Identity\Infrastructure\Verification\WriteDeferredVerificationStrategy;
use Illuminate\Support\Facades\Redis;

final class WriteDeferredVerificationStrategyIntegrationTest extends IdentityIntegrationTestCase
{
    private WriteDeferredVerificationStrategy $strategy;
    private UserEntity $user;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushdb();

        $this->strategy = app(WriteDeferredVerificationStrategy::class);
        $userRepo = app(UserRepositoryInterface::class);
        $this->user = $userRepo->create([
            'name' => 'LuaTester',
            'email' => 'lua_' . uniqid() . '@flashcommerce.com',
            'password' => 'secret123456',
        ]);
    }

    public function test_it_verifies_otp_atomically_and_pushes_to_stream(): void
    {
        $userId = $this->user->id;
        $otpKey = IdentityRedisKeys::otp($userId);
        Redis::setex($otpKey, 300, '654321');

        $result = $this->strategy->verify($this->user, '654321');

        $this->assertTrue($result->success);
        // OTP key is evicted
        $this->assertNull(Redis::get($otpKey));
        // Fast-path key is present in Redis RAM
        $this->assertSame('1', (string) Redis::get("user_verified:{$userId}"));

        // Stream has the write-deferred payload
        $streamEntries = Redis::xrange('stream:user_verified', '-', '+');
        $this->assertCount(1, $streamEntries);

        $payload = json_decode((string) reset($streamEntries)['payload'], true);
        $this->assertSame($userId, $payload['user_id']);
        $this->assertSame($this->user->email, $payload['email']);
    }

    public function test_it_increments_attempts_on_invalid_otp(): void
    {
        $userId = $this->user->id;
        $otpKey = IdentityRedisKeys::otp($userId);
        Redis::setex($otpKey, 300, '654321');

        $this->expectException(InvalidOtpException::class);

        try {
            $this->strategy->verify($this->user, '000000');
        } finally {
            $attemptsKey = IdentityRedisKeys::attempts($userId);
            $this->assertSame('1', (string) Redis::get($attemptsKey));
        }
    }

    public function test_it_locks_out_on_circuit_breaker_threshold(): void
    {
        $userId = $this->user->id;
        $otpKey = IdentityRedisKeys::otp($userId);
        Redis::setex($otpKey, 300, '654321');
        $attemptsKey = IdentityRedisKeys::attempts($userId);
        Redis::set($attemptsKey, '4'); // Already 4 failed attempts

        $this->expectException(MaxOtpAttemptsExceededException::class);

        $this->strategy->verify($this->user, '000000'); // 5th failure trips breaker
    }

    public function test_it_throws_otp_expired_if_key_missing(): void
    {
        $this->expectException(OtpExpiredException::class);
        $this->strategy->verify($this->user, '123456');
    }
}
