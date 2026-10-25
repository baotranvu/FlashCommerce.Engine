<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\Unit;

use FlashCommerce\Identity\Domain\Entities\UserEntity;
use FlashCommerce\Identity\Domain\Exceptions\InvalidTokenException;
use FlashCommerce\Identity\Infrastructure\Tokens\JwtTokenService;
use PHPUnit\Framework\TestCase;

final class JwtTokenServiceTest extends TestCase
{
    private string $secret = 'super-secret-test-key-32-bytes-long!';
    private JwtTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new JwtTokenService(
            secret: $this->secret,
            accessTtlSeconds: 900,
            refreshTtlSeconds: 2592000
        );
    }

    public function test_it_creates_and_verifies_valid_access_token(): void
    {
        $user = new UserEntity(
            id: 501,
            name: 'Bao Tran',
            email: 'bao@flashcommerce.com',
            createdAt: '2026-10-25T10:00:00Z',
            emailVerifiedAt: '2026-10-25T10:05:00Z'
        );

        $token = $this->service->createAccessToken($user);
        $this->assertNotEmpty($token);

        $payload = $this->service->verifyAccessToken($token);

        $this->assertSame(501, $payload['sub']);
        $this->assertSame('bao@flashcommerce.com', $payload['email']);
        $this->assertTrue($payload['email_verified']);
        $this->assertGreaterThan(time() - 5, $payload['iat']);
        $this->assertGreaterThan(time(), $payload['exp']);
    }

    public function test_it_rejects_expired_access_token(): void
    {
        $shortTtlService = new JwtTokenService(
            secret: $this->secret,
            accessTtlSeconds: -10, // already expired
            refreshTtlSeconds: 60
        );

        $user = new UserEntity(id: 1, name: 'Alice', email: 'alice@example.com');
        $token = $shortTtlService->createAccessToken($user);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Token has expired.');

        $this->service->verifyAccessToken($token);
    }

    public function test_it_rejects_tampered_token_signature(): void
    {
        $user = new UserEntity(id: 1, name: 'Bob', email: 'bob@example.com');
        $token = $this->service->createAccessToken($user);

        $parts = explode('.', $token);
        $tamperedToken = $parts[0] . '.' . $parts[1] . '.invalidsignature';

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Invalid token signature.');

        $this->service->verifyAccessToken($tamperedToken);
    }

    public function test_it_creates_refresh_token_with_family(): void
    {
        $user = new UserEntity(id: 99, name: 'Charlie', email: 'charlie@example.com');
        $refresh = $this->service->createRefreshToken($user);

        $this->assertNotEmpty($refresh['token']);
        $this->assertNotEmpty($refresh['family_id']);
        $this->assertSame(2592000, $refresh['expires_in']);
    }
}
