<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Http\Middleware;

use Closure;
use FlashCommerce\Identity\Domain\Exceptions\InvalidTokenException;
use FlashCommerce\Identity\Domain\Repositories\UserRepositoryInterface;
use FlashCommerce\Identity\Domain\Tokens\TokenVerifierInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function __construct(
        private readonly TokenVerifierInterface $tokenVerifier,
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new InvalidTokenException('Authorization Bearer token is required.');
        }

        $token = substr($authHeader, 7);
        $payload = $this->tokenVerifier->verifyAccessToken($token);

        $userId = (int) $payload['sub'];
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw new InvalidTokenException('User associated with token no longer exists.');
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
