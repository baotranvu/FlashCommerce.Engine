<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Http\Controllers;

use FlashCommerce\Identity\Domain\Services\AuthenticationServiceInterface;
use FlashCommerce\Identity\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LoginController
{
    public function __construct(
        private readonly AuthenticationServiceInterface $authService
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->login(
            email: $validated['email'],
            password: $validated['password']
        );

        return response()->json([
            'message' => 'Login successful.',
            'user' => $result['user']->toArray(),
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
        ], Response::HTTP_OK);
    }
}
