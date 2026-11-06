<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Infrastructure\Commands;

use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ReleaseExpiredHoldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'flash-sale:release-expired-holds
                            {--daemon : Run continuously in a daemon loop}
                            {--sleep=1 : Seconds to pause between polling loops}
                            {--grace=30 : Grace period in seconds to mitigate clock drift and in-flight payment races}';

    /**
     * The console command description.
     */
    protected $description = 'Polls the Redis delayed expiration ZSET and safely rolls back expired inventory holds';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDaemon = (bool) $this->option('daemon');
        $sleepSeconds = (int) $this->option('sleep');
        $graceSeconds = (int) $this->option('grace');

        $this->info("Starting FlashCommerce Expired Hold Sweeper (Grace: {$graceSeconds}s, Daemon: " . ($isDaemon ? 'YES' : 'NO') . ")");

        do {
            $processedCount = $this->sweepExpiredHolds($graceSeconds);

            if ($processedCount > 0) {
                $this->info("Cleaned up {$processedCount} expired order holds.");
            }

            if ($isDaemon) {
                sleep(max(1, $sleepSeconds));
            }
        } while ($isDaemon);

        return Command::SUCCESS;
    }

    /**
     * Sweep a single batch of expired holds.
     */
    public function sweepExpiredHolds(int $graceSeconds = 30): int
    {
        // Absolute safe threshold: current timestamp minus grace period
        $threshold = time() - $graceSeconds;

        /** @var array<string> $expiredOrderNos */
        $expiredOrderNos = Redis::zrangebyscore('flash_sale:expirations', '-inf', (string) $threshold, [
            'limit' => [
                'offset' => 0,
                'count'  => 100,
            ],
        ]);

        if (empty($expiredOrderNos)) {
            return 0;
        }

        $processed = 0;

        foreach ($expiredOrderNos as $orderNo) {
            $success = $this->processExpiredOrder((string) $orderNo);
            if ($success) {
                $processed++;
            }
        }

        return $processed;
    }

    private function processExpiredOrder(string $orderNo): bool
    {
        return DB::transaction(function () use ($orderNo) {
            /** @var OrderModel|null $order */
            $order = OrderModel::query()
                ->where('order_no', $orderNo)
                ->lockForUpdate()
                ->first();

            // If order doesn't exist, remove from Redis ZSET to prevent zombie retention
            if (!$order) {
                Redis::zrem('flash_sale:expirations', $orderNo);
                return true;
            }

            // If already settled, simply evict from ZSET
            if ($order->status === 'PAID') {
                Redis::zrem('flash_sale:expirations', $orderNo);
                return true;
            }

            // If still pending or reserved, expire hold and restore Redis virtual stock
            if (in_array($order->status, ['PENDING', 'RESERVED'], true)) {
                $order->status = 'EXPIRED';
                $order->save();

                $stockPrefix = config('ordering.redis.stock_prefix', 'flash_sale:stock:');

                // Return held stock back to Redis high-speed counters
                foreach ($order->items as $item) {
                    $redisStockKey = "{$stockPrefix}{$item->product_id}";
                    Redis::incrby($redisStockKey, $item->quantity);
                    Log::info("[SWEEPER STOCK_RESTORED] Product #{$item->product_id} +{$item->quantity} restored to Redis {$redisStockKey}.");
                }

                Redis::zrem('flash_sale:expirations', $orderNo);

                Log::info("[SWEEPER EXPIRED] Order {$orderNo} marked as EXPIRED. Stock released.");
                return true;
            }

            // For all other terminal states, clean up the ZSET pointer
            Redis::zrem('flash_sale:expirations', $orderNo);
            return true;
        });
    }
}
