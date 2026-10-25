<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Infrastructure\Tokens;

use FlashCommerce\Identity\Domain\Exceptions\InvalidTokenException;
use FlashCommerce\Identity\Domain\Exceptions\TokenReplayDetectedException;
use FlashCommerce\Identity\Domain\Tokens\RefreshTokenRepositoryInterface;
use Illuminate\Support\Facades\Redis;

class RedisRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    private const PREFIX_TOKEN = 'auth:refresh:token:';
    private const PREFIX_FAMILY = 'auth:refresh:family:';
    private const PREFIX_FAMILY_HISTORY = 'auth:refresh:family:history:';

    public function store(int $userId, string $refreshToken, string $familyId, int $ttlSeconds): void
    {
        $hash = $this->hashToken($refreshToken);
        $payload = json_encode([
            'user_id' => $userId,
            'family_id' => $familyId,
            'created_at' => time(),
        ], JSON_THROW_ON_ERROR);

        Redis::pipeline(function ($pipe) use ($hash, $familyId, $payload, $ttlSeconds) {
            $pipe->setex(self::PREFIX_TOKEN . $hash, $ttlSeconds, $payload);
            $pipe->setex(self::PREFIX_FAMILY . $familyId, $ttlSeconds, $hash);
        });
    }

    public function validateAndRotate(string $refreshToken, string $newRefreshToken, int $ttlSeconds): array
    {
        $hash = $this->hashToken($refreshToken);
        $raw = Redis::get(self::PREFIX_TOKEN . $hash);

        if (!$raw) {
            // Check if this hash exists in any family history (Token Replay Detection)
            // If family key exists, or token was previously rotated:
            $historyFamily = Redis::get(self::PREFIX_FAMILY_HISTORY . $hash);
            if ($historyFamily) {
                // Replay attack detected! Revoke the entire compromised family immediately
                $this->revokeFamily($historyFamily);
                throw new TokenReplayDetectedException();
            }

            throw new InvalidTokenException('Refresh token is invalid or expired.');
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data) || !isset($data['user_id'], $data['family_id'])) {
            throw new InvalidTokenException('Corrupted refresh token record.');
        }

        $userId = (int) $data['user_id'];
        $familyId = (string) $data['family_id'];

        $currentActiveHash = Redis::get(self::PREFIX_FAMILY . $familyId);
        if ($currentActiveHash !== $hash) {
            // Replay attack: family active token has already moved forward
            $this->revokeFamily($familyId);
            throw new TokenReplayDetectedException();
        }

        $newHash = $this->hashToken($newRefreshToken);
        $newPayload = json_encode([
            'user_id' => $userId,
            'family_id' => $familyId,
            'created_at' => time(),
        ], JSON_THROW_ON_ERROR);

        Redis::pipeline(function ($pipe) use ($hash, $newHash, $familyId, $newPayload, $ttlSeconds) {
            // Evict old token
            $pipe->del(self::PREFIX_TOKEN . $hash);
            // Record old token hash in history for replay detection
            $pipe->setex(self::PREFIX_FAMILY_HISTORY . $hash, $ttlSeconds, $familyId);
            // Store new token
            $pipe->setex(self::PREFIX_TOKEN . $newHash, $ttlSeconds, $newPayload);
            // Update active family token pointer
            $pipe->setex(self::PREFIX_FAMILY . $familyId, $ttlSeconds, $newHash);
        });

        return [
            'user_id' => $userId,
            'family_id' => $familyId,
        ];
    }

    public function revokeFamily(string $familyId): void
    {
        $activeHash = Redis::get(self::PREFIX_FAMILY . $familyId);
        Redis::pipeline(function ($pipe) use ($familyId, $activeHash) {
            if ($activeHash) {
                $pipe->del(self::PREFIX_TOKEN . $activeHash);
            }
            $pipe->del(self::PREFIX_FAMILY . $familyId);
        });
    }

    public function revokeToken(string $refreshToken): void
    {
        $hash = $this->hashToken($refreshToken);
        $raw = Redis::get(self::PREFIX_TOKEN . $hash);
        if ($raw) {
            $data = json_decode((string) $raw, true);
            if (is_array($data) && isset($data['family_id'])) {
                $this->revokeFamily((string) $data['family_id']);
            }
        }
        Redis::del(self::PREFIX_TOKEN . $hash);
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
