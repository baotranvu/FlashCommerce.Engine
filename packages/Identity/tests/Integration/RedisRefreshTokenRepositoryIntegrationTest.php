<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\Integration;

use FlashCommerce\Identity\Domain\Exceptions\TokenReplayDetectedException;
use FlashCommerce\Identity\Infrastructure\Tokens\RedisRefreshTokenRepository;
use Illuminate\Support\Facades\Redis;

final class RedisRefreshTokenRepositoryIntegrationTest extends IdentityIntegrationTestCase
{
    private RedisRefreshTokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushdb();
        $this->repository = new RedisRefreshTokenRepository();
    }

    public function test_it_stores_and_rotates_refresh_token_successfully(): void
    {
        $userId = 777;
        $familyId = 'family-alpha-123';
        $token1 = 'token-secret-part-1';
        $token2 = 'token-secret-part-2';
        $ttl = 3600;

        $this->repository->store($userId, $token1, $familyId, $ttl);

        $result = $this->repository->validateAndRotate($token1, $token2, $ttl);

        $this->assertSame($userId, $result['user_id']);
        $this->assertSame($familyId, $result['family_id']);

        // Verify token2 works for next rotation
        $token3 = 'token-secret-part-3';
        $result2 = $this->repository->validateAndRotate($token2, $token3, $ttl);
        $this->assertSame($userId, $result2['user_id']);
    }

    public function test_it_detects_replay_attack_and_revokes_entire_family(): void
    {
        $userId = 888;
        $familyId = 'family-compromised-456';
        $token1 = 'compromised-token-1';
        $token2 = 'legitimate-rotated-token-2';
        $tokenAttacker = 'attacker-attempt-token';
        $ttl = 3600;

        // 1. Initial login
        $this->repository->store($userId, $token1, $familyId, $ttl);

        // 2. Legitimate client rotates token1 -> token2
        $this->repository->validateAndRotate($token1, $token2, $ttl);

        // 3. Attacker tries to replay the old token1!
        $this->expectException(TokenReplayDetectedException::class);

        try {
            $this->repository->validateAndRotate($token1, $tokenAttacker, $ttl);
        } finally {
            // Assert entire family was wiped: even legitimate token2 must now fail!
            $hash2 = hash('sha256', $token2);
            $this->assertNull(Redis::get('auth:refresh:token:' . $hash2));
            $this->assertNull(Redis::get('auth:refresh:family:' . $familyId));
        }
    }

    public function test_it_revokes_token_on_explicit_logout(): void
    {
        $userId = 999;
        $familyId = 'family-logout-789';
        $token = 'logout-test-token';
        $ttl = 3600;

        $this->repository->store($userId, $token, $familyId, $ttl);
        $this->repository->revokeToken($token);

        $hash = hash('sha256', $token);
        $this->assertNull(Redis::get('auth:refresh:token:' . $hash));
        $this->assertNull(Redis::get('auth:refresh:family:' . $familyId));
    }
}
