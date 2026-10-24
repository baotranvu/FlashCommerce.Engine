<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Domain\Enums;

enum IdentityErrorCode: string
{
    case InvalidOtp = 'INVALID_OTP';
    case OtpExpired = 'OTP_EXPIRED';
    case MaxAttemptsExceeded = 'MAX_ATTEMPTS_EXCEEDED';
    case CooldownActive = 'COOLDOWN_ACTIVE';
    case UserAlreadyExists = 'USER_ALREADY_EXISTS';
    case UserNotFound = 'USER_NOT_FOUND';
    case DatabaseUnavailable = 'DATABASE_UNAVAILABLE';
}
