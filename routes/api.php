<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'framework' => 'Laravel 11',
        'service' => 'FlashCommerce.Engine',
        'timestamp' => now()->toIso8601String()
    ]);
});
