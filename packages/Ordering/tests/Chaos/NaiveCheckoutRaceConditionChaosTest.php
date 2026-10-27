<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Tests\Chaos;

use FlashCommerce\Catalog\Domain\Model\Product;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderItemModel;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use FlashCommerce\Ordering\Tests\OrderingTestCase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class NaiveCheckoutRaceConditionChaosTest extends OrderingTestCase
{
    /**
     * Chaos Demonstration FM-ORD-01:
     * Concurrently firing 10 checkout requests against 5 physical stock items
     * using the naive Check-Then-Act anti-pattern reproduces race condition
     * collisions, causing either severe overselling or MySQL 1690 underflows.
     */
    public function test_naive_checkout_causes_race_condition_under_concurrency(): void
    {
        // 1. Arrange: Create a flash sale product with strictly 5 physical units
        $initialStock = 5;
        $product = Product::query()->create([
            'sku'            => 'CHAOS-NAIVE-' . strtoupper(Str::random(6)),
            'name'           => 'Chaos Flash Item Limited Edition',
            'price'          => 150000,
            'physical_stock' => $initialStock,
            'reserved_stock' => 0,
            'is_active'      => true,
        ]);

        $concurrency = 10;
        $artisanPath = base_path('artisan');

        // Reset barrier keys in Redis
        Redis::del(['chaos:barrier:ready', 'chaos:barrier:signal']);

        $processes = [];
        $pipes = [];

        // 2. Act: Spawn 10 concurrent OS processes, all waiting at the starting gate
        for ($i = 1; $i <= $concurrency; $i++) {
            $userId = 2000 + $i;
            $cmd = "php {$artisanPath} ordering:naive-checkout {$product->id} {$userId} --barrier";

            $proc = proc_open($cmd, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w'], // stderr
            ], $pipes[$i]);

            $this->assertIsResource($proc, "Failed to spawn concurrent process #{$i}");
            $processes[$i] = $proc;
        }

        // 3. Barrier Wait: Wait until all 10 processes are warm and at the starting gate
        $waitStart = microtime(true);
        while ((int) Redis::get('chaos:barrier:ready') < $concurrency) {
            usleep(1000); // 1ms poll
            if ((microtime(true) - $waitStart) > 10.0) {
                break;
            }
        }

        // 💥 FIRE! Release all 10 processes simultaneously in the exact same microsecond!
        Redis::set('chaos:barrier:signal', 'GO');

        // 4. Await all 10 concurrent processes and collect results
        $results = [];
        for ($i = 1; $i <= $concurrency; $i++) {
            $stdout = stream_get_contents($pipes[$i][1]);
            $stderr = stream_get_contents($pipes[$i][2]);
            fclose($pipes[$i][0]);
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);
            $exitCode = proc_close($processes[$i]);

            $decoded = json_decode(trim((string) $stdout), true);
            $results[] = [
                'exit_code' => $exitCode,
                'data'      => $decoded,
                'stderr'    => $stderr,
            ];
        }

        // 5. Analyze Concurrency Collision Evidence
        $successfulOrders = array_filter($results, static fn (array $r): bool => ($r['data']['success'] ?? false) === true);
        $underflowCrashes = array_filter($results, static fn (array $r): bool => str_contains($r['data']['error'] ?? '', 'UNDERFLOW_COLLISION'));
        $ordersInDb = OrderItemModel::query()->where('product_id', $product->id)->count();

        $successCount = count($successfulOrders);
        $crashCount = count($underflowCrashes);

        // Telemetry logging for empirical proof
        fwrite(STDERR, sprintf(
            "\n💥 [Chaos FM-ORD-01 Empirical Evidence] Requests: %d | Initial Stock: %d | Successful Orders: %d | Underflow Crashes: %d | DB Orders: %d\n",
            $concurrency,
            $initialStock,
            $successCount,
            $crashCount,
            $ordersInDb
        ));

        // Invariant Failure Proof:
        // Under naive check-then-act, concurrent workers strike the race window simultaneously.
        // The system will either:
        // A) Suffer from Overselling ($ordersInDb > $initialStock)
        // OR
        // B) Suffer from MySQL Underflow collisions ($crashCount > 0)
        // BOTH prove that Naive Check-Then-Act is fatal for flash sales!
        $this->assertTrue(
            $ordersInDb > $initialStock || $crashCount > 0,
            "Expected race condition to cause either overselling (> {$initialStock} orders) or MySQL underflow crashes, but none occurred."
        );

        // Clean up created test orders and barrier keys
        OrderItemModel::query()->where('product_id', $product->id)->delete();
        $product->delete();
        Redis::del(['chaos:barrier:ready', 'chaos:barrier:signal']);
    }
}
