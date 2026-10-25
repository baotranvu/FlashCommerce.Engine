<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\E2E;

final class GlobalExceptionRfc7807Test extends IdentityE2ETestCase
{
    public function test_invalid_otp_returns_rfc7807_problem_details_with_http_422(): void
    {
        $email = 'rfc7807_phpunit_' . time() . '_' . random_int(1000, 9999) . '@test.com';

        // Register user
        $this->request('POST', '/api/v1/auth/register', [
            'name' => 'RfcTester',
            'email' => $email,
            'password' => 'secret123456',
        ]);

        // Submit invalid OTP
        $res = $this->request('POST', '/api/v1/auth/email/verify-otp', [
            'email' => $email,
            'otp' => '000000',
        ]);

        $this->assertSame(422, $res['status']);
        $this->assertSame('Unprocessable Entity', $res['body']['title']);
        $this->assertSame('INVALID_OTP', $res['body']['code']);
        $this->assertStringContainsString('4 attempt(s) remaining', $res['body']['detail']);
        $this->assertSame('/api/v1/auth/email/verify-otp', $res['body']['instance']);
        $this->assertArrayHasKey('timestamp', $res['body']);
    }

    public function test_rapid_resend_triggers_cooldown_429_with_rfc6585_retry_after_header(): void
    {
        $email = 'cooldown_phpunit_' . time() . '_' . random_int(1000, 9999) . '@test.com';

        // Register user (fires OTP #1 and sets 60s cooldown)
        $this->request('POST', '/api/v1/auth/register', [
            'name' => 'CooldownTester',
            'email' => $email,
            'password' => 'secret123456',
        ]);

        // Rapid resend while cooldown is active
        $res = $this->request('POST', '/api/v1/auth/email/resend-otp', [
            'email' => $email,
        ]);

        $this->assertSame(429, $res['status']);
        $this->assertSame('Too Many Requests', $res['body']['title']);
        $this->assertSame('COOLDOWN_ACTIVE', $res['body']['code']);
        $this->assertArrayHasKey('retry-after', $res['headers'], 'Expected RFC 6585 Retry-After header to be present');
        $this->assertGreaterThanOrEqual(1, (int) $res['headers']['retry-after']);
        $this->assertLessThanOrEqual(60, (int) $res['headers']['retry-after']);
        $this->assertArrayHasKey('retry_after_seconds', $res['body']);
    }
}
