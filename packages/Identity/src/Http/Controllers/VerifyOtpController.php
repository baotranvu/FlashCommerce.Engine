<?php

namespace FlashCommerce\Identity\Http\Controllers;

use FlashCommerce\Identity\Domain\Repositories\UserRepositoryInterface;
use FlashCommerce\Identity\Http\Requests\VerifyOtpRequest;
use FlashCommerce\Identity\Infrastructure\Verification\VerificationStrategyFactory;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class VerifyOtpController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly VerificationStrategyFactory $verificationFactory
    ) {}

    public function __invoke(VerifyOtpRequest $request): JsonResponse
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
        // Any domain failure (Expired, Invalid, Circuit Breaker) throws an IdentityDomainException
        // which automatically bubbles to bootstrap/app.php and renders as RFC 7807 Problem Details!
        $strategy = $this->verificationFactory->make('email_otp');
        $strategy->verify($user, $validated['otp']);

        // Update database
        $this->userRepository->markEmailAsVerified($user->id);
        $freshUser = $this->userRepository->findById($user->id);

        return response()->json([
            'message' => 'Email verified successfully.',
            'user' => $freshUser?->toArray()
        ], Response::HTTP_OK);
    }
}
