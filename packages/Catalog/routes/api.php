<?php

use Illuminate\Support\Facades\Route;
use FlashCommerce\Catalog\Http\Controllers\ProductCatalogController;

Route::prefix('api/v1/products')->group(function () {
    // 🧪 Chaos & Failure Demonstrations (Must be before {id})
    Route::get('/demo/underflow', [ProductCatalogController::class, 'demoUnderflow']);
    Route::get('/demo/benchmark', [ProductCatalogController::class, 'demoBenchmark']);

    // Standard REST Endpoints
    Route::get('/', [ProductCatalogController::class, 'index']);
    Route::get('/{id}', [ProductCatalogController::class, 'show'])->whereNumber('id');
});
