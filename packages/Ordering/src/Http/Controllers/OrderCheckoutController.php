<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Http\Controllers;

use FlashCommerce\Catalog\Domain\Model\Product;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderItemModel;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class OrderCheckoutController extends Controller
{
    /**
     * Production Checkout Endpoint: POST /api/v1/orders/checkout
     *
     * Stage 1 Baseline: Natural Eloquent checkout without locks or caching.
     * Demonstrates real-world Lost Update and overselling anomaly under high concurrency.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'          => ['required', 'integer'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ]);

        $userId = (int) $validated['user_id'];
        $items = $validated['items'];

        $totalAmount = 0;
        $orderLines = [];

        // 1. Check stock for all items
        foreach ($items as $item) {
            $product = Product::query()->find($item['product_id']);

            if (!$product || $product->physical_stock < $item['quantity']) {
                return response()->json([
                    'type'    => 'https://errors.flashcommerce.com/out-of-stock',
                    'title'   => 'Out of Stock',
                    'status'  => 400,
                    'detail'  => "Product ID {$item['product_id']} has insufficient physical stock.",
                ], 400);
            }

            // Natural Eloquent update (Lost Update pattern: calculated in PHP memory)
            $product->physical_stock = $product->physical_stock - $item['quantity'];
            $product->save();

            $subtotal = $product->price * $item['quantity'];
            $totalAmount += $subtotal;

            $orderLines[] = [
                'product_id' => $product->id,
                'quantity'   => $item['quantity'],
                'unit_price' => $product->price,
                'subtotal'   => $subtotal,
            ];
        }

        // 2. Persist order
        $order = OrderModel::query()->create([
            'order_no'     => 'ORD-' . strtoupper(Str::random(10)),
            'user_id'      => $userId,
            'status'       => 'PENDING',
            'total_amount' => $totalAmount,
        ]);

        foreach ($orderLines as $line) {
            $line['order_id'] = $order->id;
            OrderItemModel::query()->create($line);
        }

        return response()->json([
            'order_no'     => $order->order_no,
            'status'       => $order->status,
            'total_amount' => $order->total_amount,
        ], 201);
    }
}
