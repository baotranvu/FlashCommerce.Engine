<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Tests\Unit;

use FlashCommerce\Ordering\Domain\Contracts\PaymentStrategyInterface;
use FlashCommerce\Ordering\Domain\DTOs\NormalizedWebhookPayload;
use FlashCommerce\Ordering\Domain\DTOs\PaymentIntentResult;
use FlashCommerce\Ordering\Domain\DTOs\PaymentStatus;
use FlashCommerce\Ordering\Domain\DTOs\RefundResult;
use FlashCommerce\Ordering\Infrastructure\Payments\PaymentManager;
use FlashCommerce\Ordering\Infrastructure\Payments\Strategies\MockPaymentStrategy;
use FlashCommerce\Ordering\Infrastructure\Payments\Strategies\PayEngineStrategy;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PaymentManagerTest extends TestCase
{
    private Container $container;
    private PaymentManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        $this->container->instance('config', new Repository([
            'ordering' => [
                'default_payment_gateway' => 'mock',
                'gateways' => [
                    'mock' => [
                        'secret' => 'unit-test-mock-secret',
                    ],
                    'payengine' => [
                        'base_url'        => 'http://127.0.0.1:5000',
                        'hmac_secret'     => 'unit-test-payengine-secret',
                        'timeout_seconds' => 3,
                    ],
                ],
            ],
        ]));

        $this->manager = new PaymentManager($this->container);
    }

    public function test_payment_manager_resolves_default_driver_as_mock_strategy(): void
    {
        $this->assertSame('mock', $this->manager->getDefaultDriver());

        $driver = $this->manager->driver();

        $this->assertInstanceOf(PaymentStrategyInterface::class, $driver);
        $this->assertInstanceOf(MockPaymentStrategy::class, $driver);
        $this->assertSame('mock', $driver->getIdentifier());
    }

    public function test_payment_manager_resolves_payengine_driver(): void
    {
        $driver = $this->manager->driver('payengine');

        $this->assertInstanceOf(PaymentStrategyInterface::class, $driver);
        $this->assertInstanceOf(PayEngineStrategy::class, $driver);
        $this->assertSame('payengine', $driver->getIdentifier());
    }

    public function test_mock_strategy_initiates_payment_intent(): void
    {
        $order = new OrderModel([
            'order_no'     => 'ORD-MOCK-001',
            'user_id'      => 42,
            'total_amount' => 350000,
            'status'       => 'PENDING',
        ]);

        $strategy = $this->manager->driver('mock');
        $result = $strategy->initiatePayment($order);

        $this->assertInstanceOf(PaymentIntentResult::class, $result);
        $this->assertSame(PaymentStatus::PENDING, $result->status);
        $this->assertStringStartsWith('MOCK-TXN-', $result->gatewayTxnId);
        $this->assertStringContainsString($result->gatewayTxnId, $result->paymentUrl);
        $this->assertSame('ORD-MOCK-001', $result->metadata['order_no']);
    }

    public function test_mock_strategy_verifies_and_parses_valid_webhook(): void
    {
        $strategy = $this->manager->driver('mock');

        $request = Request::create(
            '/api/v1/payments/webhooks/mock',
            'POST',
            [
                'order_no'     => 'ORD-MOCK-002',
                'txn_id'       => 'MOCK-TXN-ABC123XYZ',
                'amount_cents' => 350000,
                'status'       => 'SETTLED',
                'signature'    => 'unit-test-mock-secret',
            ]
        );

        $payload = $strategy->verifyAndParseWebhook($request);

        $this->assertInstanceOf(NormalizedWebhookPayload::class, $payload);
        $this->assertSame('ORD-MOCK-002', $payload->orderNo);
        $this->assertSame('MOCK-TXN-ABC123XYZ', $payload->gatewayTxnId);
        $this->assertSame(350000, $payload->amountCents);
        $this->assertSame(PaymentStatus::SETTLED, $payload->status);
        $this->assertSame('mock', $payload->gateway);
    }

    public function test_mock_strategy_rejects_invalid_webhook_signature(): void
    {
        $strategy = $this->manager->driver('mock');

        $request = Request::create(
            '/api/v1/payments/webhooks/mock',
            'POST',
            [
                'order_no'  => 'ORD-MOCK-003',
                'signature' => 'invalid-tampered-secret',
            ]
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mock webhook signature.');

        $strategy->verifyAndParseWebhook($request);
    }

    public function test_mock_strategy_processes_compensating_refund(): void
    {
        $strategy = $this->manager->driver('mock');

        $refundResult = $strategy->refund(
            gatewayTxnId: 'MOCK-TXN-REFUND-ME',
            amountCents: 150000,
            reason: 'Hold expired before payment settlement'
        );

        $this->assertInstanceOf(RefundResult::class, $refundResult);
        $this->assertTrue($refundResult->success);
        $this->assertStringStartsWith('REF-MOCK-', $refundResult->refundRef);
        $this->assertStringContainsString('MOCK-TXN-REFUND-ME', $refundResult->message);
        $this->assertSame('mock', $refundResult->gateway);
    }

    public function test_payment_manager_supports_custom_driver_extension(): void
    {
        $customStrategy = new class implements PaymentStrategyInterface {
            public function getIdentifier(): string { return 'stripe'; }
            public function initiatePayment(OrderModel $order): PaymentIntentResult {
                return new PaymentIntentResult('STRIPE-1', 'https://stripe.com', PaymentStatus::PENDING);
            }
            public function verifyAndParseWebhook(Request $request): NormalizedWebhookPayload {
                return new NormalizedWebhookPayload('ORD-1', 'STRIPE-1', 100, PaymentStatus::SETTLED, 'stripe');
            }
            public function refund(string $gatewayTxnId, int $amountCents, string $reason): RefundResult {
                return new RefundResult(true, 'REF-STRIPE-1', 'Refunded', 'stripe');
            }
        };

        $this->manager->extend('stripe', fn () => $customStrategy);

        $resolved = $this->manager->driver('stripe');

        $this->assertSame('stripe', $resolved->getIdentifier());
        $this->assertSame($customStrategy, $resolved);
    }
}
