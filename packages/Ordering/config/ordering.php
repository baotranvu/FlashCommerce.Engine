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
];
