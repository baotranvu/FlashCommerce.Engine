<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Domain\Repositories;

interface InventoryHoldRepositoryInterface
{
    /**
     * Atomically evaluate and hold inventory in memory with a TTL.
     *
     * @return array{success: bool, remaining_stock: int, hold_token?: string, status: string}
     */
    public function hold(int $productId, int $quantity, int $userId, int $ttlSeconds = 300): array;

    /**
     * Atomically release held stock back to the active pool.
     */
    public function release(int $productId, string $holdToken): bool;
}
