<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Http\Controllers;

use FlashCommerce\Identity\Domain\Services\AuthenticationServiceInterface;
use FlashCommerce\Identity\Http\Requests\RefreshTokenRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RefreshTokenController
{
    public function __construct(
        private readonly AuthenticationServiceInterface $authService
    ) {}

    public function __invoke(RefreshTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->refresh($validated['refresh_token']);

        return response()->json([
            'message' => 'Token refreshed successfully.',
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
        ], Response::HTTP_OK);
    }
}
