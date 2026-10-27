<?php

use Illuminate\Support\Facades\Route;
use FlashCommerce\Catalog\Http\Controllers\ProductCatalogController;

Route::prefix('api/v1/products')->group(function () {
    Route::get('/', [ProductCatalogController::class, 'index']);
    Route::get('/{id}', [ProductCatalogController::class, 'show'])->whereNumber('id');
});
