<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Infrastructure\Payments;

use FlashCommerce\Ordering\Domain\Contracts\PaymentStrategyInterface;
use FlashCommerce\Ordering\Infrastructure\Payments\Strategies\MockPaymentStrategy;
use FlashCommerce\Ordering\Infrastructure\Payments\Strategies\PayEngineStrategy;
use Illuminate\Support\Manager;
use InvalidArgumentException;

class PaymentManager extends Manager
{
    /**
     * Get the default payment driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('ordering.default_payment_gateway', 'mock');
    }

    /**
     * Resolve a driver instance and guarantee PaymentStrategyInterface contract.
     *
     * @param string|null $driver
     * @return PaymentStrategyInterface
     */
    public function driver($driver = null): PaymentStrategyInterface
    {
        $instance = parent::driver($driver);

        if (!$instance instanceof PaymentStrategyInterface) {
            throw new InvalidArgumentException(
                sprintf('Driver [%s] must implement [%s].', $driver ?? $this->getDefaultDriver(), PaymentStrategyInterface::class)
            );
        }

        return $instance;
    }

    /**
     * Create an instance of the Mock payment driver.
     */
    protected function createMockDriver(): PaymentStrategyInterface
    {
        $secret = (string) $this->config->get('ordering.gateways.mock.secret', 'mock-secret-key');

        return new MockPaymentStrategy($secret);
    }

    /**
     * Create an instance of the PayEngine (C# .NET 8) payment driver.
     */
    protected function createPayengineDriver(): PaymentStrategyInterface
    {
        $config = $this->config->get('ordering.gateways.payengine', []);

        $baseUrl = (string) ($config['base_url'] ?? 'http://127.0.0.1:5000');
        $hmacSecret = (string) ($config['hmac_secret'] ?? 'payengine-hmac-secret-invariable');
        $timeout = (int) ($config['timeout_seconds'] ?? 5);

        return new PayEngineStrategy($baseUrl, $hmacSecret, $timeout);
    }
}
