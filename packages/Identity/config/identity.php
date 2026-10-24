<?php

return [
    'otp' => [
        'ttl_seconds' => (int) env('IDENTITY_OTP_TTL', 300),
        'cooldown_seconds' => (int) env('IDENTITY_OTP_COOLDOWN', 60),
        'max_attempts' => (int) env('IDENTITY_OTP_MAX_ATTEMPTS', 5),
    ],
    'verification_channel' => env('IDENTITY_DEFAULT_CHANNEL', 'email_otp'),
];
