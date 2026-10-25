<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Domain\Tokens;

interface RefreshTokenRepositoryInterface
{
    /**
     * Store a refresh token associated with a user and family.
     */
    public function store(int $userId, string $refreshToken, string $familyId, int $ttlSeconds): void;

    /**
     * Validate an incoming refresh token and perform rotation.
     * Throws TokenReplayDetectedException if token reuse is detected.
     *
     * @return array{user_id: int, family_id: string}
     */
    public function validateAndRotate(string $refreshToken, string $newRefreshToken, int $ttlSeconds): array;

    /**
     * Revoke all tokens belonging to a family (in response to replay attack or logout).
     */
    public function revokeFamily(string $familyId): void;

    /**
     * Revoke a single refresh token (e.g. on logout).
     */
    public function revokeToken(string $refreshToken): void;
}
