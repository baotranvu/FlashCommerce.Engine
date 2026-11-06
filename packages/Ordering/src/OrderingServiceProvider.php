<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering;

use FlashCommerce\Ordering\Domain\Contracts\PaymentStrategyInterface;
use FlashCommerce\Ordering\Domain\Repositories\InventoryHoldRepositoryInterface;
use FlashCommerce\Ordering\Domain\Services\OrderSettlementServiceInterface;
use FlashCommerce\Ordering\Infrastructure\Commands\ReleaseExpiredHoldsCommand;
use FlashCommerce\Ordering\Infrastructure\Payments\PaymentManager;
use FlashCommerce\Ordering\Infrastructure\Redis\RedisInventoryHoldRepository;
use FlashCommerce\Ordering\Infrastructure\Services\OrderSettlementService;
use Illuminate\Support\ServiceProvider;

class OrderingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 1. Merge package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/ordering.php', 'ordering');

        // 2. Bind Domain Repository Interfaces to Infrastructure Implementations (Phase 1)
        $this->app->bind(
            InventoryHoldRepositoryInterface::class,
            RedisInventoryHoldRepository::class
        );

        // 3. Bind PaymentManager as singleton (Phase 2)
        $this->app->singleton(PaymentManager::class, function ($app) {
            return new PaymentManager($app);
        });

        // 4. Bind default PaymentStrategyInterface resolution
        $this->app->bind(PaymentStrategyInterface::class, function ($app) {
            return $app->make(PaymentManager::class)->driver();
        });

        $this->app->alias(PaymentManager::class, 'ordering.payment_manager');

        // 5. Bind OrderSettlementServiceInterface
        $this->app->bind(OrderSettlementServiceInterface::class, OrderSettlementService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. 🗄️ Auto-load local package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // 2. 🛣️ Auto-load local package API routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        // 3. ⌨️ Register console commands (Phase 3)
        if ($this->app->runningInConsole()) {
            $this->commands([
                ReleaseExpiredHoldsCommand::class,
            ]);
        }
    }
}
