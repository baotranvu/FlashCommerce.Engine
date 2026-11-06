<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Two-Phase Inventory Hold TTL (Seconds)
    |--------------------------------------------------------------------------
    | Duration that an inventory reservation key remains active in Redis
    | before expiring and returning back to the available stock pool.
    */
    'hold_ttl_seconds' => (int) env('ORDER_HOLD_TTL_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Order Constraints
    |--------------------------------------------------------------------------
    */
    'max_items_per_order' => 10,
    'max_quantity_per_item' => 5,

    /*
    |--------------------------------------------------------------------------
    | Redis Key Prefixes
    |--------------------------------------------------------------------------
    */
    'redis' => [
        'stock_prefix' => 'flash_sale:stock:',
        'hold_prefix'  => 'inventory:hold:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Strategy Configuration
    |--------------------------------------------------------------------------
    | Configures multi-gateway settlement strategies using Strategy Pattern.
    | Supports 'mock' (instantaneous local test) and 'payengine' (.NET 8 ISO20022).
    */
    'default_payment_gateway' => env('ORDER_PAYMENT_GATEWAY', 'mock'),

    'gateways' => [
        'mock' => [
            'secret' => env('PAYMENT_MOCK_SECRET', 'mock-secret-key'),
        ],
        'payengine' => [
            'base_url'        => env('PAYENGINE_BASE_URL', 'http://127.0.0.1:5000'),
            'hmac_secret'     => env('PAYENGINE_HMAC_SECRET', 'payengine-hmac-secret-invariable'),
            'timeout_seconds' => (int) env('PAYENGINE_TIMEOUT_SECONDS', 5),
        ],
    ],
];
