<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\Unit;

use FlashCommerce\Identity\Domain\Enums\IdentityErrorCode;
use FlashCommerce\Identity\Domain\Exceptions\AccountUnverifiedException;
use FlashCommerce\Identity\Domain\Exceptions\InvalidCredentialsException;
use FlashCommerce\Identity\Domain\Exceptions\InvalidTokenException;
use FlashCommerce\Identity\Domain\Exceptions\TokenReplayDetectedException;
use PHPUnit\Framework\TestCase;

final class IdentityAuthDomainExceptionsTest extends TestCase
{
    public function test_invalid_credentials_exception_maps_to_401_problem_details(): void
    {
        $exception = new InvalidCredentialsException();

        $this->assertSame(401, $exception->getStatusCode());
        $this->assertSame(IdentityErrorCode::InvalidCredentials->value, $exception->getErrorCode());
        $this->assertSame('Invalid Credentials', $exception->getTitle());
        $this->assertSame('Invalid credentials.', $exception->getDetail());
    }

    public function test_account_unverified_exception_maps_to_403_problem_details(): void
    {
        $exception = new AccountUnverifiedException();

        $this->assertSame(403, $exception->getStatusCode());
        $this->assertSame(IdentityErrorCode::AccountUnverified->value, $exception->getErrorCode());
        $this->assertSame('Account Not Verified', $exception->getTitle());
        $this->assertSame('Email verification is required before logging in.', $exception->getDetail());
    }

    public function test_invalid_token_exception_maps_to_401_problem_details(): void
    {
        $exception = new InvalidTokenException();

        $this->assertSame(401, $exception->getStatusCode());
        $this->assertSame(IdentityErrorCode::InvalidToken->value, $exception->getErrorCode());
        $this->assertSame('Unauthorized', $exception->getTitle());
        $this->assertSame('Authentication token is missing, invalid, or expired.', $exception->getDetail());
    }

    public function test_token_replay_detected_exception_maps_to_401_problem_details(): void
    {
        $exception = new TokenReplayDetectedException();

        $this->assertSame(401, $exception->getStatusCode());
        $this->assertSame(IdentityErrorCode::TokenReplayDetected->value, $exception->getErrorCode());
        $this->assertSame('Unauthorized', $exception->getTitle());
        $this->assertSame('Token reuse detected. All sessions in this family have been terminated.', $exception->getDetail());
    }
}
