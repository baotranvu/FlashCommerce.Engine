<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Tests\Unit;

use FlashCommerce\Catalog\Domain\Model\Product;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderItemModel;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use FlashCommerce\Ordering\Tests\OrderingTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ReconcileInventoryCommandTest extends OrderingTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushdb();
    }

    public function test_dry_run_inspection_detects_zero_drift_when_inventory_balanced(): void
    {
        $product = Product::query()->create([
            'sku'            => 'TEST-REC-BAL-' . strtoupper(Str::random(6)),
            'name'           => 'Reconcile Balanced Product',
            'price'          => 100000,
            'physical_stock' => 10,
            'reserved_stock' => 0,
            'is_active'      => true,
        ]);

        $stockKey = config('ordering.redis.stock_prefix', 'flash_sale:stock:') . $product->id;
        Redis::set($stockKey, 10);

        $exitCode = Artisan::call('flash-sale:reconcile-inventory', [
            '--product_id' => $product->id,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('10', (string) Redis::get($stockKey));
    }

    public function test_inspection_identifies_and_itemizes_zombie_reservations_without_mutating_data(): void
    {
        $product = Product::query()->create([
            'sku'            => 'TEST-REC-ZOM-' . strtoupper(Str::random(6)),
            'name'           => 'Reconcile Zombie Product',
            'price'          => 200000,
            'physical_stock' => 10,
            'reserved_stock' => 0,
            'is_active'      => true,
        ]);

        $stockKey = config('ordering.redis.stock_prefix', 'flash_sale:stock:') . $product->id;
        Redis::set($stockKey, 8); // 2 units virtual hold in RAM

        // Create an expired RESERVED order that dropped out of Redis ZSET
        $zombieOrder = OrderModel::query()->create([
            'order_no'        => 'ORD-ZOMBIE-' . strtoupper(Str::random(8)),
            'user_id'         => 301,
            'status'          => 'RESERVED',
            'total_amount'    => 400000,
            'hold_expires_at' => now()->subSeconds(400),
        ]);

        OrderItemModel::query()->create([
            'order_id'   => $zombieOrder->id,
            'product_id' => $product->id,
            'quantity'   => 2,
            'unit_price' => 200000,
            'subtotal'   => 400000,
        ]);

        // Dry-run inspection
        $exitCode = Artisan::call('flash-sale:reconcile-inventory', [
            '--product_id' => $product->id,
        ]);

        $this->assertSame(0, $exitCode);

        // Dry-run invariant: Data must NOT be mutated without business approval
        $zombieOrder->refresh();
        $this->assertSame('RESERVED', $zombieOrder->status, 'Dry-run must strictly preserve database records.');
        $this->assertSame('8', (string) Redis::get($stockKey), 'Dry-run must strictly preserve Redis memory.');
    }

    public function test_remediation_is_strictly_blocked_without_ticket_and_authorizer(): void
    {
        $product = Product::query()->create([
            'sku'            => 'TEST-REC-BLK-' . strtoupper(Str::random(6)),
            'name'           => 'Reconcile Blocked Product',
            'price'          => 150000,
            'physical_stock' => 5,
            'reserved_stock' => 0,
            'is_active'      => true,
        ]);

        // Attempt repair WITHOUT ticket/authorizer
        $exitCode = Artisan::call('flash-sale:reconcile-inventory', [
            '--product_id' => $product->id,
            '--repair'     => true,
        ]);

        // Separation of Duties Invariant: Must fail!
        $this->assertSame(1, $exitCode, 'Remediation must be blocked without business sign-off metadata.');
    }

    public function test_approved_remediation_realigns_redis_counter_and_purges_zombies(): void
    {
        $product = Product::query()->create([
            'sku'            => 'TEST-REC-APP-' . strtoupper(Str::random(6)),
            'name'           => 'Reconcile Approved Product',
            'price'          => 250000,
            'physical_stock' => 10,
            'reserved_stock' => 0,
            'is_active'      => true,
        ]);

        $stockKey = config('ordering.redis.stock_prefix', 'flash_sale:stock:') . $product->id;
        Redis::set($stockKey, 6); // Drifted counter (4 units deficit)

        $zombieOrder = OrderModel::query()->create([
            'order_no'        => 'ORD-FIX-' . strtoupper(Str::random(8)),
            'user_id'         => 302,
            'status'          => 'RESERVED',
            'total_amount'    => 500000,
            'hold_expires_at' => now()->subSeconds(450),
        ]);

        OrderItemModel::query()->create([
            'order_id'   => $zombieOrder->id,
            'product_id' => $product->id,
            'quantity'   => 2,
            'unit_price' => 250000,
            'subtotal'   => 500000,
        ]);

        // Execute authorized remediation
        $exitCode = Artisan::call('flash-sale:reconcile-inventory', [
            '--product_id'  => $product->id,
            '--repair'      => true,
            '--ticket'      => 'OPS-202611-99',
            '--approved-by' => 'cfo@flashcommerce.com',
        ]);

        $this->assertSame(0, $exitCode);

        // Assert zombie order purged
        $zombieOrder->refresh();
        $this->assertSame('EXPIRED', $zombieOrder->status);

        // Assert Redis counter realigned to physical ground truth (10)
        $this->assertSame('10', (string) Redis::get($stockKey));
    }
}
