<?php

use Illuminate\Support\Facades\Route;
use FlashCommerce\Identity\Http\Controllers\RegisterController;
use FlashCommerce\Identity\Http\Controllers\VerifyOtpController;
use FlashCommerce\Identity\Http\Controllers\ResendOtpController;

Route::middleware('api')->prefix('api/v1/auth')->group(function () {
    Route::post('/register', RegisterController::class);
    Route::post('/email/verify-otp', VerifyOtpController::class);
    Route::post('/email/resend-otp', ResendOtpController::class);
});
