<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Domain\Services;

use FlashCommerce\Identity\Domain\Entities\UserEntity;

interface AuthenticationServiceInterface
{
    /**
     * Authenticate user credentials and return dual-token bundle.
     *
     * @return array{user: UserEntity, access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function login(string $email, string $password): array;

    /**
     * Rotate refresh token and issue new dual-token pair.
     *
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function refresh(string $refreshToken): array;

    /**
     * Invalidate user session and revoke refresh token.
     */
    public function logout(string $refreshToken): void;
}
