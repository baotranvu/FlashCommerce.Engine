<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\Unit;

use FlashCommerce\Identity\Infrastructure\Redis\IdentityRedisKeys;
use PHPUnit\Framework\TestCase;

/**
 * TC-UNIT-05: IdentityRedisKeys Unit Tests
 * 
 * In-Memory verification of key string formats. Zero-I/O.
 */
final class IdentityRedisKeysTest extends TestCase
{
    public function test_it_generates_correct_otp_key_format(): void
    {
        $this->assertSame('email_otp:501', IdentityRedisKeys::otp(501));
        $this->assertSame('email_otp:uuid-test-123', IdentityRedisKeys::otp('uuid-test-123'));
    }

    public function test_it_generates_correct_cooldown_key_format(): void
    {
        $this->assertSame('email_otp_cooldown:501', IdentityRedisKeys::cooldown(501));
        $this->assertSame('email_otp_cooldown:alpha', IdentityRedisKeys::cooldown('alpha'));
    }

    public function test_it_generates_correct_attempts_key_format(): void
    {
        $this->assertSame('email_otp_attempts:501', IdentityRedisKeys::attempts(501));
        $this->assertSame('email_otp_attempts:xyz', IdentityRedisKeys::attempts('xyz'));
    }
}
