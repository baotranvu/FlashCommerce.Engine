<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Domain\Tokens;

interface TokenVerifierInterface
{
    /**
     * Verify and decode a stateless Access Token.
     *
     * @return array{sub: int, email: string, email_verified: bool, exp: int, iat: int}
     * @throws \FlashCommerce\Identity\Domain\Exceptions\InvalidTokenException
     */
    public function verifyAccessToken(string $token): array;
}
