<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Http\Controllers;

use FlashCommerce\Catalog\Domain\Model\Product;
use FlashCommerce\Ordering\Domain\Repositories\InventoryHoldRepositoryInterface;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderItemModel;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class OrderCheckoutController extends Controller
{
    public function __construct(
        private readonly InventoryHoldRepositoryInterface $inventoryHoldRepository
    ) {}

    /**
     * Production Checkout Endpoint: POST /api/v1/orders/checkout
     *
     * Stage 2 Fix: Two-Phase Inventory Hold via atomic Redis Lua Script.
     * Guarantees 100% Zero-Oversell under massive concurrent flash sale traffic.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'            => ['required', 'integer'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ]);

        $userId = (int) $validated['user_id'];
        $items = $validated['items'];

        $totalAmount = 0;
        $orderLines = [];
        $successfulHolds = [];

        // Phase 1: Atomic Two-Phase Inventory Hold in RAM
        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];

            $product = Product::query()->find($productId);

            if (!$product) {
                $this->rollbackHolds($successfulHolds);
                return response()->json([
                    'type'   => 'https://errors.flashcommerce.com/product-not-found',
                    'title'  => 'Not Found',
                    'status' => 404,
                    'detail' => "Product ID {$productId} was not found.",
                ], 404);
            }

            $holdResult = $this->inventoryHoldRepository->hold(
                $productId,
                $quantity,
                $userId,
                (int) config('ordering.hold_ttl_seconds', 300)
            );

            if (!$holdResult['success']) {
                $this->rollbackHolds($successfulHolds);
                return response()->json([
                    'type'   => 'https://errors.flashcommerce.com/out-of-stock',
                    'title'  => 'Out of Stock',
                    'status' => 409,
                    'detail' => "Product ID {$productId} has insufficient flash sale stock.",
                ], 409);
            }

            $successfulHolds[] = [
                'product_id' => $productId,
                'hold_token' => $holdResult['hold_token'],
            ];

            $subtotal = $product->price * $quantity;
            $totalAmount += $subtotal;

            $orderLines[] = [
                'product_id' => $productId,
                'quantity'   => $quantity,
                'unit_price' => $product->price,
                'subtotal'   => $subtotal,
            ];
        }

        // Primary hold token representing the checkout session
        $primaryHold = $successfulHolds[0] ?? null;

        // Phase 2: Persist RESERVED order in MySQL
        $order = OrderModel::query()->create([
            'order_no'        => 'ORD-' . strtoupper(Str::random(10)),
            'user_id'         => $userId,
            'status'          => 'RESERVED',
            'total_amount'    => $totalAmount,
            'hold_token'      => $primaryHold['hold_token'] ?? null,
            'hold_expires_at' => now()->addSeconds((int) config('ordering.hold_ttl_seconds', 300)),
        ]);

        foreach ($orderLines as $line) {
            $line['order_id'] = $order->id;
            OrderItemModel::query()->create($line);
        }

        // Phase 3: Register delayed expiration in Redis ZSET for Active Sweeper
        if ($order->hold_expires_at) {
            Redis::zadd('flash_sale:expirations', $order->hold_expires_at->getTimestamp(), $order->order_no);
        }

        return response()->json([
            'order_no'        => $order->order_no,
            'status'          => $order->status,
            'total_amount'    => $order->total_amount,
            'hold_token'      => $order->hold_token,
            'hold_expires_at' => $order->hold_expires_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Compensating action: Release already acquired holds if a subsequent item fails.
     *
     * @param array<int, array{product_id: int, hold_token: string}> $holds
     */
    private function rollbackHolds(array $holds): void
    {
        foreach ($holds as $hold) {
            $this->inventoryHoldRepository->release($hold['product_id'], $hold['hold_token']);
        }
    }
}
