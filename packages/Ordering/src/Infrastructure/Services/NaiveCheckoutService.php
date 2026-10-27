<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Infrastructure\Services;

use FlashCommerce\Catalog\Domain\Model\Product;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderItemModel;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use Illuminate\Support\Str;

class NaiveCheckoutService
{
    /**
     * Vulnerable Check-Then-Act Checkout Anti-Pattern.
     * Demonstrates high-concurrency race condition and oversell vulnerability.
     *
     * @return array{success: bool, order_id?: int, error?: string}
     */
    public function checkout(int $userId, int $productId, int $quantity): array
    {
        // 1. Read phase: Check stock in MySQL without distributed or row lock
        $product = Product::query()->find($productId);

        if (!$product || $product->physical_stock < $quantity) {
            return [
                'success' => false,
                'error'   => 'OUT_OF_STOCK',
            ];
        }

        // 2. Race condition window: simulate business logic, pricing calc, network latency
        usleep(15000); // 15ms window

        // 3. Act phase: Decrement MySQL stock directly
        try {
            $product->decrement('physical_stock', $quantity);
        } catch (\Throwable $e) {
            // MySQL Error 1690 (UNSIGNED underflow) or collision
            return [
                'success' => false,
                'error'   => 'UNDERFLOW_COLLISION: ' . $e->getMessage(),
            ];
        }

        // 4. Create Order
        $order = OrderModel::query()->create([
            'order_no'     => 'NAIVE-' . strtoupper(Str::random(10)),
            'user_id'      => $userId,
            'status'       => 'PENDING',
            'total_amount' => $product->price * $quantity,
        ]);

        OrderItemModel::query()->create([
            'order_id'   => $order->id,
            'product_id' => $productId,
            'quantity'   => $quantity,
            'unit_price' => $product->price,
            'subtotal'   => $product->price * $quantity,
        ]);

        return [
            'success'  => true,
            'order_id' => $order->id,
        ];
    }
}
