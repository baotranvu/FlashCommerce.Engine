<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\E2E;

use FlashCommerce\Identity\Infrastructure\Redis\IdentityRedisKeys;

final class AuthLoginTokenLifecycleTest extends IdentityE2ETestCase
{
    private function registerAndVerifyUser(string $email, string $password): array
    {
        $regRes = $this->request('POST', '/api/v1/auth/register', [
            'name' => 'Flow3Tester',
            'email' => $email,
            'password' => $password,
        ]);
        $userId = (int) $regRes['body']['user']['id'];

        $otp = $this->getRedis()->get(IdentityRedisKeys::otp($userId));
        $this->request('POST', '/api/v1/auth/email/verify-otp', [
            'email' => $email,
            'otp' => (string) $otp,
        ]);

        return ['id' => $userId, 'email' => $email];
    }

    public function test_login_with_unverified_account_returns_403_rfc7807(): void
    {
        $email = 'unverified_' . time() . '_' . random_int(1000, 9999) . '@test.com';
        $password = 'secret123456';

        // Register but do NOT verify
        $this->request('POST', '/api/v1/auth/register', [
            'name' => 'UnverifiedUser',
            'email' => $email,
            'password' => $password,
        ]);

        $loginRes = $this->request('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $this->assertSame(403, $loginRes['status']);
        $this->assertSame('ACCOUNT_UNVERIFIED', $loginRes['body']['code'] ?? null);
        $this->assertSame('Account Not Verified', $loginRes['body']['title'] ?? null);
    }

    public function test_login_with_invalid_credentials_returns_401_rfc7807(): void
    {
        $email = 'wrongpass_' . time() . '_' . random_int(1000, 9999) . '@test.com';
        $this->registerAndVerifyUser($email, 'correct_pass_123');

        $loginRes = $this->request('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => 'wrong_pass_999',
        ]);

        $this->assertSame(401, $loginRes['status']);
        $this->assertSame('INVALID_CREDENTIALS', $loginRes['body']['code'] ?? null);
        $this->assertSame('Invalid credentials.', $loginRes['body']['detail'] ?? null);
    }

    public function test_successful_login_and_token_lifecycle(): void
    {
        $email = 'lifecycle_' . time() . '_' . random_int(1000, 9999) . '@test.com';
        $password = 'valid_pass_123';
        $this->registerAndVerifyUser($email, $password);

        // 1. Happy Login -> Returns Dual Tokens (TC-E2E-06)
        $loginRes = $this->request('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $this->assertSame(200, $loginRes['status']);
        $this->assertNotEmpty($loginRes['body']['access_token']);
        $this->assertNotEmpty($loginRes['body']['refresh_token']);
        $this->assertSame('Bearer', $loginRes['body']['token_type']);
        $this->assertSame(900, $loginRes['body']['expires_in']);

        $accessToken = $loginRes['body']['access_token'];
        $refreshToken1 = $loginRes['body']['refresh_token'];

        // 2. Access /me with Bearer Token (TC-E2E-11)
        $meRes = $this->request('GET', '/api/v1/auth/me', [], [
            'Authorization' => "Bearer {$accessToken}",
        ]);

        $this->assertSame(200, $meRes['status']);
        $this->assertSame($email, $meRes['body']['user']['email']);

        // 3. Access /me with invalid token returns 401
        $badMeRes = $this->request('GET', '/api/v1/auth/me', [], [
            'Authorization' => 'Bearer invalid.tampered.token',
        ]);
        $this->assertSame(401, $badMeRes['status']);

        // 4. Refresh Token Rotation (TC-E2E-09)
        $refreshRes = $this->request('POST', '/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken1,
        ]);

        $this->assertSame(200, $refreshRes['status']);
        $this->assertNotEmpty($refreshRes['body']['access_token']);
        $this->assertNotEmpty($refreshRes['body']['refresh_token']);
        $this->assertNotSame($refreshToken1, $refreshRes['body']['refresh_token']);

        $accessToken2 = $refreshRes['body']['access_token'];
        $refreshToken2 = $refreshRes['body']['refresh_token'];

        // 5. Test new access token works
        $meRes2 = $this->request('GET', '/api/v1/auth/me', [], [
            'Authorization' => "Bearer {$accessToken2}",
        ]);
        $this->assertSame(200, $meRes2['status']);

        // 6. Adversarial Replay Attack: Replay refreshToken1 (TC-E2E-10)
        $replayRes = $this->request('POST', '/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken1,
        ]);
        $this->assertSame(401, $replayRes['status']);

        // Replay attack revokes family: legitimate refreshToken2 must now also be revoked
        $nukedRes = $this->request('POST', '/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken2,
        ]);
        $this->assertSame(401, $nukedRes['status']);
    }

    public function test_logout_revokes_session(): void
    {
        $email = 'logout_' . time() . '_' . random_int(1000, 9999) . '@test.com';
        $password = 'secret_logout_123';
        $this->registerAndVerifyUser($email, $password);

        $loginRes = $this->request('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        $refreshToken = $loginRes['body']['refresh_token'];

        $logoutRes = $this->request('POST', '/api/v1/auth/logout', [
            'refresh_token' => $refreshToken,
        ]);
        $this->assertSame(200, $logoutRes['status']);

        // Subsequent refresh fails with 401
        $refreshRes = $this->request('POST', '/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);
        $this->assertSame(401, $refreshRes['status']);
    }
}
