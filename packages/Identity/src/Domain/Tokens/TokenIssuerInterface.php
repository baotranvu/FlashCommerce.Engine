<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Domain\Tokens;

use FlashCommerce\Identity\Domain\Entities\UserEntity;

interface TokenIssuerInterface
{
    /**
     * Issue a cryptographically signed stateless Access Token.
     */
    public function createAccessToken(UserEntity $user): string;

    /**
     * Issue an opaque Refresh Token bound to a token family.
     *
     * @return array{token: string, family_id: string, expires_in: int}
     */
    public function createRefreshToken(UserEntity $user, ?string $familyId = null): array;
}
