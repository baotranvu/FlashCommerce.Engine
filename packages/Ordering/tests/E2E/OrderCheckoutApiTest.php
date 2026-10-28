<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Tests\E2E;

use FlashCommerce\Catalog\Domain\Model\Product;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderItemModel;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use FlashCommerce\Ordering\Tests\OrderingTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

class OrderCheckoutApiTest extends OrderingTestCase
{
    use DatabaseTransactions;

    public function test_checkout_single_order_successfully(): void
    {
        $product = Product::query()->create([
            'sku'            => 'TEST-CHK-' . strtoupper(Str::random(6)),
            'name'           => 'Checkout Test Product',
            'price'          => 200000,
            'physical_stock' => 10,
            'reserved_stock' => 0,
            'is_active'      => true,
        ]);

        $payload = [
            'user_id' => 101,
            'items'   => [
                [
                    'product_id' => $product->id,
                    'quantity'   => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/orders/checkout', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'order_no',
                'status',
                'total_amount',
            ])
            ->assertJsonPath('status', 'PENDING')
            ->assertJsonPath('total_amount', 400000);

        $orderNo = $response->json('order_no');
        $this->assertDatabaseHas('orders', [
            'order_no'     => $orderNo,
            'user_id'      => 101,
            'total_amount' => 400000,
        ]);

        $product->refresh();
        $this->assertSame(8, $product->physical_stock);
    }

    public function test_checkout_fails_when_product_out_of_stock(): void
    {
        $product = Product::query()->create([
            'sku'            => 'TEST-OOS-' . strtoupper(Str::random(6)),
            'name'           => 'Out of Stock Product',
            'price'          => 150000,
            'physical_stock' => 0,
            'reserved_stock' => 0,
            'is_active'      => true,
        ]);

        $payload = [
            'user_id' => 102,
            'items'   => [
                [
                    'product_id' => $product->id,
                    'quantity'   => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/orders/checkout', $payload);

        $response->assertStatus(400)
            ->assertJsonPath('status', 400)
            ->assertJsonPath('title', 'Out of Stock');
    }
}
