<?php

declare(strict_types=1);

use FlashCommerce\Ordering\Http\Controllers\OrderCheckoutController;
use FlashCommerce\Ordering\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/orders')->group(function () {
    Route::post('/checkout', [OrderCheckoutController::class, 'checkout']);
});

Route::prefix('api/v1/payments')->group(function () {
    Route::post('/webhooks/{gateway}', [PaymentWebhookController::class, 'handle']);
});
