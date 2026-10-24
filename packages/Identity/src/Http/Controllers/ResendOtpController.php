<?php

namespace FlashCommerce\Identity\Http\Controllers;

use FlashCommerce\Identity\Domain\Repositories\UserRepositoryInterface;
use FlashCommerce\Identity\Http\Requests\ResendOtpRequest;
use FlashCommerce\Identity\Infrastructure\Verification\VerificationStrategyFactory;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ResendOtpController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly VerificationStrategyFactory $verificationFactory
    ) {}

    public function __invoke(ResendOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $this->userRepository->findByEmail($validated['email']);

        if (! $user) {
            return response()->json([
                'title' => 'Not Found',
                'detail' => 'User does not exist.'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($user->isVerified()) {
            return response()->json([
                'message' => 'Email is already verified.',
                'user' => $user->toArray()
            ], Response::HTTP_OK);
        }

        // 🛡️ ZERO ERROR MAPPING BOILERPLATE:
        // CooldownActiveException bubbles up directly to bootstrap/app.php
        $strategy = $this->verificationFactory->make('email_otp');
        $result = $strategy->sendChallenge($user);

        return response()->json([
            'message' => $result->message
        ], Response::HTTP_OK);
    }
}
