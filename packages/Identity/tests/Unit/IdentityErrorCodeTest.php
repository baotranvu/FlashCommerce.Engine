<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\Unit;

use FlashCommerce\Identity\Domain\Enums\IdentityErrorCode;
use PHPUnit\Framework\TestCase;

final class IdentityErrorCodeTest extends TestCase
{
    public function test_all_backed_enum_cases_map_to_expected_string_codes(): void
    {
        $this->assertSame('INVALID_OTP', IdentityErrorCode::InvalidOtp->value);
        $this->assertSame('OTP_EXPIRED', IdentityErrorCode::OtpExpired->value);
        $this->assertSame('MAX_ATTEMPTS_EXCEEDED', IdentityErrorCode::MaxAttemptsExceeded->value);
        $this->assertSame('COOLDOWN_ACTIVE', IdentityErrorCode::CooldownActive->value);
        $this->assertSame('USER_ALREADY_EXISTS', IdentityErrorCode::UserAlreadyExists->value);
        $this->assertSame('USER_NOT_FOUND', IdentityErrorCode::UserNotFound->value);
        $this->assertSame('DATABASE_UNAVAILABLE', IdentityErrorCode::DatabaseUnavailable->value);
    }

    public function test_enum_can_be_instantiated_from_valid_raw_string(): void
    {
        $code = IdentityErrorCode::from('COOLDOWN_ACTIVE');
        $this->assertSame(IdentityErrorCode::CooldownActive, $code);
    }
}
