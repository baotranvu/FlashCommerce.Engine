<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Infrastructure\Redis;

final class IdentityRedisKeys
{
    public static function otp(int|string $userId): string
    {
        return "email_otp:{$userId}";
    }

    public static function cooldown(int|string $userId): string
    {
        return "email_otp_cooldown:{$userId}";
    }

    public static function attempts(int|string $userId): string
    {
        return "email_otp_attempts:{$userId}";
    }
}
