<?php

namespace FlashCommerce\Catalog;

use Illuminate\Support\ServiceProvider;
use FlashCommerce\Catalog\Domain\Repositories\ProductRepositoryInterface;
use FlashCommerce\Catalog\Infrastructure\Repositories\EloquentProductRepository;

class CatalogServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 1. Merge package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/catalog.php', 'catalog');

        // 2. Bind Domain Repository Interface to Eloquent Implementation
        $this->app->bind(
            ProductRepositoryInterface::class,
            EloquentProductRepository::class
        );
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
