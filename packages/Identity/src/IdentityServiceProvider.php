<?php

namespace FlashCommerce\Identity;

use Illuminate\Support\ServiceProvider;
use FlashCommerce\Identity\Domain\Repositories\UserRepositoryInterface;
use FlashCommerce\Identity\Domain\Services\AuthenticationServiceInterface;
use FlashCommerce\Identity\Domain\Tokens\RefreshTokenRepositoryInterface;
use FlashCommerce\Identity\Domain\Tokens\TokenIssuerInterface;
use FlashCommerce\Identity\Domain\Tokens\TokenVerifierInterface;
use FlashCommerce\Identity\Infrastructure\Commands\FlushVerifiedUsersCommand;
use FlashCommerce\Identity\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use FlashCommerce\Identity\Infrastructure\Services\AuthenticationService;
use FlashCommerce\Identity\Infrastructure\Tokens\JwtTokenService;
use FlashCommerce\Identity\Infrastructure\Tokens\RedisRefreshTokenRepository;
use FlashCommerce\Identity\Infrastructure\Verification\EmailOtpVerificationStrategy;
use FlashCommerce\Identity\Infrastructure\Verification\VerificationStrategyFactory;
use FlashCommerce\Identity\Infrastructure\Verification\WriteDeferredVerificationStrategy;

class IdentityServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/identity.php', 'identity');

        // Bind UserRepositoryInterface to Eloquent implementation
        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );

        // Bind Verification Strategy Factory & Strategies
        $this->app->singleton(VerificationStrategyFactory::class);
        $this->app->singleton(EmailOtpVerificationStrategy::class);
        $this->app->singleton(WriteDeferredVerificationStrategy::class);

        // Bind Token Services
        $this->app->singleton(JwtTokenService::class, function ($app) {
            $config = $app['config']->get('identity.tokens', []);
            return new JwtTokenService(
                secret: $config['secret'] ?? 'fallback-secret-for-jwt-signing-key-32',
                accessTtlSeconds: (int) ($config['access_ttl_seconds'] ?? 900),
                refreshTtlSeconds: (int) ($config['refresh_ttl_seconds'] ?? 2592000)
            );
        });

        $this->app->bind(TokenIssuerInterface::class, JwtTokenService::class);
        $this->app->bind(TokenVerifierInterface::class, JwtTokenService::class);
        $this->app->bind(RefreshTokenRepositoryInterface::class, RedisRefreshTokenRepository::class);

        // Bind Authentication Service
        $this->app->singleton(AuthenticationService::class, function ($app) {
            $config = $app['config']->get('identity.tokens', []);
            return new AuthenticationService(
                userRepository: $app->make(UserRepositoryInterface::class),
                tokenIssuer: $app->make(TokenIssuerInterface::class),
                refreshTokenRepository: $app->make(RefreshTokenRepositoryInterface::class),
                refreshTtlSeconds: (int) ($config['refresh_ttl_seconds'] ?? 2592000)
            );
        });
        $this->app->bind(AuthenticationServiceInterface::class, AuthenticationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load package API routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                FlushVerifiedUsersCommand::class,
            ]);
        }
    }
}
