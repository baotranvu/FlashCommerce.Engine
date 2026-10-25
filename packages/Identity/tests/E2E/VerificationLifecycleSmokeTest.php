<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\E2E;

use FlashCommerce\Identity\Infrastructure\Redis\IdentityRedisKeys;

final class VerificationLifecycleSmokeTest extends IdentityE2ETestCase
{
    public function test_complete_user_lifecycle_from_registration_to_redis_otp_verification(): void
    {
        $email = 'smoke_phpunit_' . time() . '_' . random_int(1000, 9999) . '@test.com';

        // 1. Register User
        $registerRes = $this->request('POST', '/api/v1/auth/register', [
            'name' => 'SmokeTester',
            'email' => $email,
            'password' => 'secret123456',
        ]);

        $this->assertSame(201, $registerRes['status'], 'Expected HTTP 201 Created on registration');
        $this->assertArrayHasKey('user', $registerRes['body']);
        $userId = (int) $registerRes['body']['user']['id'];
        $this->assertFalse($registerRes['body']['user']['is_verified']);

        // 2. Fetch OTP from Redis RAM via IdentityRedisKeys
        $redis = $this->getRedis();
        $otpKey = IdentityRedisKeys::otp($userId);
        $otp = $redis->get($otpKey);

        $this->assertNotEmpty($otp, "Expected OTP key [{$otpKey}] to exist in Redis");
        $this->assertSame(6, strlen((string) $otp), 'Expected OTP to be 6 numeric digits');

        // 3. Verify OTP via HTTP API
        $verifyRes = $this->request('POST', '/api/v1/auth/email/verify-otp', [
            'email' => $email,
            'otp' => (string) $otp,
        ]);

        $this->assertSame(200, $verifyRes['status'], 'Expected HTTP 200 OK on valid OTP verification');
        $this->assertArrayHasKey('user', $verifyRes['body']);
        $this->assertTrue($verifyRes['body']['user']['is_verified'], 'Expected is_verified to become true');
        $this->assertNotNull($verifyRes['body']['user']['email_verified_at'], 'Expected email_verified_at timestamp to be set');

        // 4. Assert Redis Key Eviction
        $this->assertFalse($redis->get($otpKey), 'Expected Redis OTP key to be evicted immediately after successful verification');
    }
}
