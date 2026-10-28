<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering;

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
    }
}
