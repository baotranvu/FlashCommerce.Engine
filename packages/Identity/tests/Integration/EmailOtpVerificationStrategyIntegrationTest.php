<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\Integration;

use FlashCommerce\Identity\Domain\Entities\UserEntity;
use FlashCommerce\Identity\Domain\Exceptions\CooldownActiveException;
use FlashCommerce\Identity\Domain\Exceptions\InvalidOtpException;
use FlashCommerce\Identity\Domain\Exceptions\MaxOtpAttemptsExceededException;
use FlashCommerce\Identity\Infrastructure\Redis\IdentityRedisKeys;
use FlashCommerce\Identity\Infrastructure\Verification\EmailOtpVerificationStrategy;
use Illuminate\Support\Facades\Redis;

/**
 * TC-INT-04, TC-INT-05, TC-INT-06, TC-INT-07: EmailOtpVerificationStrategy Integration Tests
 * 
 * Runs against real Redis 7 in Docker container.
 */
final class EmailOtpVerificationStrategyIntegrationTest extends IdentityIntegrationTestCase
{
    private EmailOtpVerificationStrategy $strategy;
    private UserEntity $dummyUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new EmailOtpVerificationStrategy();
        $this->dummyUser = new UserEntity(
            id: 99901,
            name: 'Strategy Tester',
            email: 'strategy_test@example.com'
        );

        // Clean up test keys in Redis before each test
        Redis::del(IdentityRedisKeys::otp($this->dummyUser->id));
        Redis::del(IdentityRedisKeys::cooldown($this->dummyUser->id));
        Redis::del(IdentityRedisKeys::attempts($this->dummyUser->id));
    }

    protected function tearDown(): void
    {
        // Clean up test keys after test
        Redis::del(IdentityRedisKeys::otp($this->dummyUser->id));
        Redis::del(IdentityRedisKeys::cooldown($this->dummyUser->id));
        Redis::del(IdentityRedisKeys::attempts($this->dummyUser->id));
        parent::tearDown();
    }

    /**
     * TC-INT-04: Dispatch OTP sets 6-digit code with TTL 300s.
     */
    public function test_it_dispatches_otp_and_stores_in_redis_with_ttl(): void
    {
        $result = $this->strategy->sendChallenge($this->dummyUser);
        $this->assertTrue($result->success);

        $otpKey = IdentityRedisKeys::otp($this->dummyUser->id);
        $cachedOtp = Redis::get($otpKey);

        $this->assertNotNull($cachedOtp);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $cachedOtp);

        $ttl = Redis::ttl($otpKey);
        $this->assertGreaterThan(280, $ttl);
        $this->assertLessThanOrEqual(300, $ttl);
    }

    /**
     * TC-INT-05: Cooldown blocks immediate resend and passes remaining seconds.
     */
    public function test_it_enforces_cooldown_window_on_resend(): void
    {
        // First dispatch sets cooldown key for 60s
        $this->strategy->sendChallenge($this->dummyUser);

        // Immediate second dispatch must throw CooldownActiveException
        try {
            $this->strategy->sendChallenge($this->dummyUser);
            $this->fail('Expected CooldownActiveException was not thrown');
        } catch (CooldownActiveException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertGreaterThanOrEqual(1, $e->getRetryAfterSeconds());
            $this->assertLessThanOrEqual(60, $e->getRetryAfterSeconds());
        }
    }

    /**
     * TC-INT-06: 5 wrong OTP attempts locks out user via Circuit Breaker.
     */
    public function test_it_locks_out_after_5_consecutive_invalid_attempts(): void
    {
        $this->strategy->sendChallenge($this->dummyUser);

        // Attempt 1 through 4: Throws InvalidOtpException
        for ($i = 1; $i <= 4; $i++) {
            try {
                $this->strategy->verify($this->dummyUser, '000000');
                $this->fail("Attempt {$i} should have failed with InvalidOtpException");
            } catch (InvalidOtpException $e) {
                $this->assertSame(5 - $i, $e->getRemainingAttempts());
            }
        }

        // Attempt 5: Reaches circuit breaker threshold -> Throws MaxOtpAttemptsExceededException
        $this->expectException(MaxOtpAttemptsExceededException::class);
        $this->strategy->verify($this->dummyUser, '000000');
    }

    /**
     * TC-INT-07: Successful verification cleans up Redis state.
     */
    public function test_it_verifies_valid_otp_and_evicts_redis_state(): void
    {
        $this->strategy->sendChallenge($this->dummyUser);

        $otpKey = IdentityRedisKeys::otp($this->dummyUser->id);
        $validOtp = (string) Redis::get($otpKey);

        $result = $this->strategy->verify($this->dummyUser, $validOtp);
        $this->assertTrue($result->success);

        // Assert keys evicted from Redis
        $this->assertNull(Redis::get($otpKey));
        $this->assertNull(Redis::get(IdentityRedisKeys::attempts($this->dummyUser->id)));
    }
}
