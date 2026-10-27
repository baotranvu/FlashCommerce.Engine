<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Infrastructure\Commands;

use FlashCommerce\Ordering\Infrastructure\Services\NaiveCheckoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class NaiveCheckoutCommand extends Command
{
    protected $signature = 'ordering:naive-checkout {productId} {userId} {--barrier}';
    protected $description = 'Execute a vulnerable naive check-then-act checkout for concurrency chaos testing';

    public function handle(NaiveCheckoutService $service): int
    {
        $productId = (int) $this->argument('productId');
        $userId = (int) $this->argument('userId');

        if ($this->option('barrier')) {
            // 1. Signal process readiness
            Redis::incr('chaos:barrier:ready');

            // 2. Wait at the barrier starting gate until parent sounds the buzzer ('GO')
            $start = microtime(true);
            while (Redis::get('chaos:barrier:signal') !== 'GO') {
                usleep(500); // 0.5ms tight spin
                if ((microtime(true) - $start) > 10.0) {
                    break; // Safety timeout
                }
            }
        }

        $result = $service->checkout($userId, $productId, 1);

        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return $result['success'] ? 0 : 1;
    }
}
