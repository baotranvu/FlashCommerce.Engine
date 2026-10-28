<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Infrastructure\Redis;

use FlashCommerce\Catalog\Domain\Model\Product;
use FlashCommerce\Ordering\Domain\Repositories\InventoryHoldRepositoryInterface;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class RedisInventoryHoldRepository implements InventoryHoldRepositoryInterface
{
    private string $stockPrefix;
    private string $holdPrefix;
    private string $holdLuaScript;
    private string $releaseLuaScript;

    public function __construct()
    {
        $this->stockPrefix = (string) config('ordering.redis.stock_prefix', 'flash_sale:stock:');
        $this->holdPrefix = (string) config('ordering.redis.hold_prefix', 'inventory:hold:');

        $this->holdLuaScript = (string) file_get_contents(__DIR__ . '/../../../resources/lua/hold_inventory_atomic.lua');
        $this->releaseLuaScript = (string) file_get_contents(__DIR__ . '/../../../resources/lua/release_inventory_atomic.lua');
    }

    public function hold(int $productId, int $quantity, int $userId, int $ttlSeconds = 300): array
    {
        $stockKey = $this->stockPrefix . $productId;
        $holdToken = 'HLD-' . strtoupper(Str::random(20));
        $holdKey = $this->holdPrefix . $holdToken;

        // Warm-up: If Redis stock key is uninitialized, populate from MySQL
        if (!Redis::exists($stockKey)) {
            $product = Product::query()->find($productId);
            $initialStock = $product ? (int) $product->physical_stock : 0;
            Redis::setnx($stockKey, $initialStock);
        }

        /** @var array{0: int, 1: int, 2: string} $rawResult */
        $rawResult = Redis::eval(
            $this->holdLuaScript,
            2,
            $stockKey,
            $holdKey,
            $quantity,
            $ttlSeconds,
            $userId
        );

        $status = (int) ($rawResult[0] ?? 0);
        $remainingStock = (int) ($rawResult[1] ?? 0);
        $message = (string) ($rawResult[2] ?? 'UNKNOWN');

        if ($status === 1) {
            return [
                'success'         => true,
                'remaining_stock' => $remainingStock,
                'hold_token'      => $holdToken,
                'status'          => $message,
            ];
        }

        return [
            'success'         => false,
            'remaining_stock' => $remainingStock,
            'status'          => $message,
        ];
    }

    public function release(int $productId, string $holdToken): bool
    {
        $stockKey = $this->stockPrefix . $productId;
        $holdKey = $this->holdPrefix . $holdToken;

        $rawResult = Redis::eval(
            $this->releaseLuaScript,
            2,
            $stockKey,
            $holdKey
        );

        return ((int) ($rawResult[0] ?? 0)) === 1;
    }
}
