<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Tests\Unit;

use FlashCommerce\Catalog\Domain\Model\Product;
use FlashCommerce\Ordering\Domain\DTOs\NormalizedWebhookPayload;
use FlashCommerce\Ordering\Domain\DTOs\PaymentStatus;
use FlashCommerce\Ordering\Domain\Services\OrderSettlementServiceInterface;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderItemModel;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderRefundModel;
use FlashCommerce\Ordering\Tests\OrderingTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ReleaseExpiredHoldsCommandTest extends OrderingTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushdb();
    }

    public function test_unexpired_holds_are_not_released_within_grace_window(): void
    {
        $product = Product::query()->create([
            'sku'            => 'TEST-SWP-' . strtoupper(Str::random(6)),
            'name'           => 'Sweeper Product Active',
            'price'          => 100000,
            'physical_stock' => 10,
            'reserved_stock' => 0,
            'is_active'      => true,
        ]);

        $stockKey = config('ordering.redis.stock_prefix', 'flash_sale:stock:') . $product->id;
        Redis::set($stockKey, 8); // 2 units currently held

        $order = OrderModel::query()->create([
            'order_no'        => 'ORD-ACTIVE-' . strtoupper(Str::random(8)),
            'user_id'         => 201,
            'status'          => 'RESERVED',
            'total_amount'    => 200000,
            'hold_expires_at' => now()->addSeconds(60),
        ]);

        OrderItemModel::query()->create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 2,
            'unit_price' => 100000,
            'subtotal'   => 200000,
        ]);

        // Register in ZSET with future expiration
        Redis::zadd('flash_sale:expirations', time() + 60, $order->order_no);

        // Run Sweeper with 30s grace window
        $exitCode = Artisan::call('flash-sale:release-expired-holds', ['--grace' => 30]);
        $this->assertSame(0, $exitCode);

        // Assert hold remains intact
        $order->refresh();
        $this->assertSame('RESERVED', $order->status);
        $this->assertSame('8', (string) Redis::get($stockKey));
        $this->assertNotNull(Redis::zscore('flash_sale:expirations', $order->order_no));
    }

    public function test_expired_holds_restore_redis_stock_and_mark_order_expired(): void
    {
        $product = Product::query()->create([
            'sku'            => 'TEST-SWP-' . strtoupper(Str::random(6)),
            'name'           => 'Sweeper Product Expired',
            'price'          => 150000,
            'physical_stock' => 10,
            'reserved_stock' => 0,
            'is_active'      => true,
        ]);

        $stockKey = config('ordering.redis.stock_prefix', 'flash_sale:stock:') . $product->id;
        Redis::set($stockKey, 7); // 3 units held

        $order = OrderModel::query()->create([
            'order_no'        => 'ORD-EXP-' . strtoupper(Str::random(8)),
            'user_id'         => 202,
            'status'          => 'RESERVED',
            'total_amount'    => 450000,
            'hold_expires_at' => now()->subSeconds(60),
        ]);

        OrderItemModel::query()->create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 3,
            'unit_price' => 150000,
            'subtotal'   => 450000,
        ]);

        // Register in ZSET with past expiration epoch (time - 60s)
        Redis::zadd('flash_sale:expirations', time() - 60, $order->order_no);

        // Run Sweeper (Threshold = time - 30s, so time - 60s will be swept)
        $exitCode = Artisan::call('flash-sale:release-expired-holds', ['--grace' => 30]);
        $this->assertSame(0, $exitCode);

        // Assert hold expired and in-memory stock restored
        $order->refresh();
        $this->assertSame('EXPIRED', $order->status);
        $this->assertSame('10', (string) Redis::get($stockKey)); // 7 + 3 restored to 10
        $this->assertFalse(Redis::zscore('flash_sale:expirations', $order->order_no));
    }

    public function test_settled_paid_orders_are_evicted_from_zset_without_stock_change(): void
    {
        $product = Product::query()->create([
            'sku'            => 'TEST-SWP-' . strtoupper(Str::random(6)),
            'name'           => 'Sweeper Product Paid',
            'price'          => 120000,
            'physical_stock' => 10,
            'reserved_stock' => 0,
            'is_active'      => true,
        ]);

        $stockKey = config('ordering.redis.stock_prefix', 'flash_sale:stock:') . $product->id;
        Redis::set($stockKey, 6);

        $order = OrderModel::query()->create([
            'order_no'        => 'ORD-PAID-' . strtoupper(Str::random(8)),
            'user_id'         => 203,
            'status'          => 'PAID',
            'total_amount'    => 240000,
            'hold_expires_at' => now()->subSeconds(60),
            'paid_at'         => now(),
        ]);

        OrderItemModel::query()->create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 2,
            'unit_price' => 120000,
            'subtotal'   => 240000,
        ]);

        Redis::zadd('flash_sale:expirations', time() - 60, $order->order_no);

        $exitCode = Artisan::call('flash-sale:release-expired-holds', ['--grace' => 30]);
        $this->assertSame(0, $exitCode);

        $order->refresh();
        $this->assertSame('PAID', $order->status);
        $this->assertSame('6', (string) Redis::get($stockKey)); // Stock untouched!
        $this->assertFalse(Redis::zscore('flash_sale:expirations', $order->order_no));
    }

    public function test_ghost_payment_on_expired_order_triggers_compensating_refund_and_preserves_physical_stock(): void
    {
        $product = Product::query()->create([
            'sku'            => 'TEST-GHOST-' . strtoupper(Str::random(6)),
            'name'           => 'Ghost Payment Product',
            'price'          => 500000,
            'physical_stock' => 5,
            'reserved_stock' => 0,
            'is_active'      => true,
        ]);

        $order = OrderModel::query()->create([
            'order_no'        => 'ORD-GHOST-' . strtoupper(Str::random(8)),
            'user_id'         => 204,
            'status'          => 'EXPIRED', // Already swept
            'total_amount'    => 500000,
            'hold_expires_at' => now()->subSeconds(120),
        ]);

        OrderItemModel::query()->create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit_price' => 500000,
            'subtotal'   => 500000,
        ]);

        /** @var OrderSettlementServiceInterface $settlementService */
        $settlementService = app(OrderSettlementServiceInterface::class);

        $payload = new NormalizedWebhookPayload(
            orderNo: $order->order_no,
            gatewayTxnId: 'TXN-GHOST-MOCK-999',
            amountCents: 500000,
            status: PaymentStatus::SETTLED,
            gateway: 'mock',
            rawPayload: ['mock' => true]
        );

        $settledOrder = $settlementService->settle($payload);

        $this->assertNotNull($settledOrder);
        $settledOrder->refresh();

        // 1. Invariant: Order must be REFUNDED (or REFUND_PENDING)
        $this->assertContains($settledOrder->status, ['REFUND_PENDING', 'REFUNDED']);

        // 2. Invariant: MySQL Physical stock MUST NOT be decremented!
        $product->refresh();
        $this->assertSame(5, $product->physical_stock, 'Physical MySQL stock must remain strictly unchanged on ghost payment.');

        // 3. Invariant: Audit record in order_refunds must exist
        $this->assertDatabaseHas('order_refunds', [
            'order_no'               => $order->order_no,
            'gateway_provider'       => 'mock',
            'gateway_transaction_id' => 'TXN-GHOST-MOCK-999',
            'reason'                 => 'EXPIRED_HOLD_GHOST_PAYMENT',
            'status'                 => 'SUCCESS',
        ]);
    }
}
