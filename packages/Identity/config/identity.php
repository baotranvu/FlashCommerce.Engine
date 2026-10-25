<?php

return [
    'otp' => [
        'ttl_seconds' => (int) env('IDENTITY_OTP_TTL', 300),
        'cooldown_seconds' => (int) env('IDENTITY_OTP_COOLDOWN', 60),
        'max_attempts' => (int) env('IDENTITY_OTP_MAX_ATTEMPTS', 5),
    ],
    'tokens' => [
        'secret' => env('JWT_SECRET', env('APP_KEY', 'base64:FlashCommerceSuperSecretKey32Bytes!')),
        'access_ttl_seconds' => (int) env('IDENTITY_ACCESS_TOKEN_TTL', 900),
        'refresh_ttl_seconds' => (int) env('IDENTITY_REFRESH_TOKEN_TTL', 2592000),
    ],
    'verification_channel' => env('IDENTITY_DEFAULT_CHANNEL', 'email_otp'),
];
