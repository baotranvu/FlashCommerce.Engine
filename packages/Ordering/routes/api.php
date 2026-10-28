<?php

declare(strict_types=1);

use FlashCommerce\Ordering\Http\Controllers\OrderCheckoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/orders')->group(function () {
    Route::post('/checkout', [OrderCheckoutController::class, 'checkout']);
});
