<?php

use Illuminate\Support\Facades\Route;
use FlashCommerce\Identity\Http\Controllers\LoginController;
use FlashCommerce\Identity\Http\Controllers\LogoutController;
use FlashCommerce\Identity\Http\Controllers\RefreshTokenController;
use FlashCommerce\Identity\Http\Controllers\RegisterController;
use FlashCommerce\Identity\Http\Controllers\ResendOtpController;
use FlashCommerce\Identity\Http\Controllers\UserProfileController;
use FlashCommerce\Identity\Http\Controllers\VerifyOtpController;
use FlashCommerce\Identity\Http\Middleware\AuthenticateJwt;

Route::middleware('api')->prefix('api/v1/auth')->group(function () {
    Route::post('/register', RegisterController::class);
    Route::post('/email/verify-otp', VerifyOtpController::class);
    Route::post('/email/resend-otp', ResendOtpController::class);
    Route::post('/login', LoginController::class);
    Route::post('/refresh', RefreshTokenController::class);
    Route::post('/logout', LogoutController::class);
    Route::get('/me', UserProfileController::class)->middleware(AuthenticateJwt::class);
});
