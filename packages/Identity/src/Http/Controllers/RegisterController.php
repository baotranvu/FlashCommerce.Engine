<?php

namespace FlashCommerce\Identity\Http\Controllers;

use FlashCommerce\Identity\Domain\Repositories\UserRepositoryInterface;
use FlashCommerce\Identity\Http\Requests\RegisterRequest;
use FlashCommerce\Identity\Infrastructure\Verification\VerificationStrategyFactory;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RegisterController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly VerificationStrategyFactory $verificationFactory
    ) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // 🛡️ Zero try-catch pollution in Controller:
        // Handled cleanly via UserRepositoryInterface -> Eloquent/DB -> Global Handler in bootstrap/app.php
        $user = $this->userRepository->create($validated);

        // Automatically dispatch initial verification challenge (Strategy/Factory pattern)
        rescue(function () use ($user) {
            $this->verificationFactory->make('email_otp')->sendChallenge($user);
        }, report: true);

        return response()->json([
            'message' => 'User registered successfully via Identity Package (Repository Pattern). Verification code dispatched.',
            'user' => $user->toArray(),
        ], Response::HTTP_CREATED);
    }
}
