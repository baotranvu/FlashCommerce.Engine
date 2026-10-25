<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Infrastructure\Services;

use FlashCommerce\Identity\Domain\Entities\UserEntity;
use FlashCommerce\Identity\Domain\Exceptions\AccountUnverifiedException;
use FlashCommerce\Identity\Domain\Exceptions\InvalidCredentialsException;
use FlashCommerce\Identity\Domain\Exceptions\InvalidTokenException;
use FlashCommerce\Identity\Domain\Repositories\UserRepositoryInterface;
use FlashCommerce\Identity\Domain\Services\AuthenticationServiceInterface;
use FlashCommerce\Identity\Domain\Tokens\RefreshTokenRepositoryInterface;
use FlashCommerce\Identity\Domain\Tokens\TokenIssuerInterface;

class AuthenticationService implements AuthenticationServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TokenIssuerInterface $tokenIssuer,
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly int $refreshTtlSeconds = 2592000
    ) {}

    public function login(string $email, string $password): array
    {
        $user = $this->userRepository->verifyCredentials($email, $password);

        if ($user === null) {
            throw new InvalidCredentialsException();
        }

        if (!$user->isVerified()) {
            throw new AccountUnverifiedException();
        }

        $accessToken = $this->tokenIssuer->createAccessToken($user);
        $refreshData = $this->tokenIssuer->createRefreshToken($user);

        $this->refreshTokenRepository->store(
            userId: $user->id,
            refreshToken: $refreshData['token'],
            familyId: $refreshData['family_id'],
            ttlSeconds: $refreshData['expires_in']
        );

        return [
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshData['token'],
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ];
    }

    public function refresh(string $refreshToken): array
    {
        $newRefreshToken = bin2hex(random_bytes(32));
        $rotated = $this->refreshTokenRepository->validateAndRotate(
            refreshToken: $refreshToken,
            newRefreshToken: $newRefreshToken,
            ttlSeconds: $this->refreshTtlSeconds
        );

        $user = $this->userRepository->findById($rotated['user_id']);
        if ($user === null) {
            throw new InvalidTokenException('User account no longer exists.');
        }

        $accessToken = $this->tokenIssuer->createAccessToken($user);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ];
    }

    public function logout(string $refreshToken): void
    {
        $this->refreshTokenRepository->revokeToken($refreshToken);
    }
}
