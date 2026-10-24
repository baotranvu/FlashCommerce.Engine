<?php

namespace FlashCommerce\Identity;

use Illuminate\Support\ServiceProvider;
use FlashCommerce\Identity\Domain\Repositories\UserRepositoryInterface;
use FlashCommerce\Identity\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use FlashCommerce\Identity\Infrastructure\Verification\EmailOtpVerificationStrategy;
use FlashCommerce\Identity\Infrastructure\Verification\VerificationStrategyFactory;

class IdentityServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/identity.php', 'identity');

        // Bind the UserRepositoryInterface to Eloquent implementation
        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );

        // Bind the Verification Strategy Factory as a singleton
        $this->app->singleton(VerificationStrategyFactory::class);
        $this->app->singleton(EmailOtpVerificationStrategy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load package API routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }
}
