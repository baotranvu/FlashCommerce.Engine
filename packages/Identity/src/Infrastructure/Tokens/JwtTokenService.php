<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Infrastructure\Tokens;

use FlashCommerce\Identity\Domain\Entities\UserEntity;
use FlashCommerce\Identity\Domain\Exceptions\InvalidTokenException;
use FlashCommerce\Identity\Domain\Tokens\TokenIssuerInterface;
use FlashCommerce\Identity\Domain\Tokens\TokenVerifierInterface;
use JsonException;

class JwtTokenService implements TokenIssuerInterface, TokenVerifierInterface
{
    public function __construct(
        private readonly string $secret,
        private readonly int $accessTtlSeconds = 900,
        private readonly int $refreshTtlSeconds = 2592000
    ) {}

    public function createAccessToken(UserEntity $user): string
    {
        $now = time();
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'sub' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'email_verified' => $user->isVerified(),
            'iat' => $now,
            'exp' => $now + $this->accessTtlSeconds,
            'jti' => bin2hex(random_bytes(16)),
        ];

        $b64Header = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $b64Payload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', "{$b64Header}.{$b64Payload}", $this->secret, true);
        $b64Sig = $this->base64UrlEncode($signature);

        return "{$b64Header}.{$b64Payload}.{$b64Sig}";
    }

    public function createRefreshToken(UserEntity $user, ?string $familyId = null): array
    {
        return [
            'token' => bin2hex(random_bytes(32)),
            'family_id' => $familyId ?? bin2hex(random_bytes(16)),
            'expires_in' => $this->refreshTtlSeconds,
        ];
    }

    public function verifyAccessToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidTokenException('Malformed token structure.');
        }

        [$b64Header, $b64Payload, $b64Sig] = $parts;
        $expectedSignature = hash_hmac('sha256', "{$b64Header}.{$b64Payload}", $this->secret, true);
        $givenSignature = $this->base64UrlDecode($b64Sig);

        if (!hash_equals($expectedSignature, $givenSignature)) {
            throw new InvalidTokenException('Invalid token signature.');
        }

        try {
            $payload = json_decode($this->base64UrlDecode($b64Payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidTokenException('Invalid token payload.');
        }

        if (!is_array($payload) || !isset($payload['sub'], $payload['exp'])) {
            throw new InvalidTokenException('Missing required token claims.');
        }

        if (time() >= (int) $payload['exp']) {
            throw new InvalidTokenException('Token has expired.');
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
