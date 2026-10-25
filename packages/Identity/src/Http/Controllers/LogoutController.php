<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Http\Controllers;

use FlashCommerce\Identity\Domain\Services\AuthenticationServiceInterface;
use FlashCommerce\Identity\Http\Requests\LogoutRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LogoutController
{
    public function __construct(
        private readonly AuthenticationServiceInterface $authService
    ) {}

    public function __invoke(LogoutRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->authService->logout($validated['refresh_token']);

        return response()->json([
            'message' => 'Successfully logged out.',
        ], Response::HTTP_OK);
    }
}
