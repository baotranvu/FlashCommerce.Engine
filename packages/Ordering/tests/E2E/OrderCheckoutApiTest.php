<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Tests\E2E;

use FlashCommerce\Catalog\Domain\Model\Product;
use FlashCommerce\Ordering\Tests\OrderingTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class OrderCheckoutApiTest extends OrderingTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushdb();
    }

    public function test_checkout_single_order_successfully_with_atomic_hold(): void
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
                'hold_token',
                'hold_expires_at',
            ])
            ->assertJsonPath('status', 'RESERVED')
            ->assertJsonPath('total_amount', 400000);

        $this->assertNotNull($response->json('hold_token'));
        $this->assertNotNull($response->json('hold_expires_at'));

        $orderNo = $response->json('order_no');
        $this->assertDatabaseHas('orders', [
            'order_no'     => $orderNo,
            'user_id'      => 101,
            'status'       => 'RESERVED',
            'total_amount' => 400000,
        ]);

        // Phase 3 Invariant Verification: Must be registered in Redis delayed expiration ZSET
        $zscore = Redis::zscore('flash_sale:expirations', $orderNo);
        $this->assertNotNull($zscore, 'Order must be registered in Redis delayed expiration ZSET.');
        $this->assertGreaterThan(time(), (int) $zscore, 'Expiration timestamp must be in the future.');
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

        $response->assertStatus(409)
            ->assertJsonPath('status', 409)
            ->assertJsonPath('title', 'Out of Stock');
    }
}
