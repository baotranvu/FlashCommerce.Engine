<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Tests\Unit;

use FlashCommerce\Ordering\Domain\DTOs\NormalizedWebhookPayload;
use FlashCommerce\Ordering\Domain\DTOs\PaymentStatus;
use FlashCommerce\Ordering\Domain\DTOs\RefundResult;
use FlashCommerce\Ordering\Domain\Contracts\PaymentStrategyInterface;
use FlashCommerce\Ordering\Infrastructure\Payments\PaymentManager;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderRefundModel;
use FlashCommerce\Ordering\Infrastructure\Services\OrderSettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\TestCase;

class OrderSettlementServiceTest extends TestCase
{
    public function test_service_instantiation(): void
    {
        $manager = $this->createMock(PaymentManager::class);
        $service = new OrderSettlementService($manager);

        $this->assertInstanceOf(OrderSettlementService::class, $service);
    }

    public function test_normalized_webhook_payload_structure(): void
    {
        $payload = new NormalizedWebhookPayload(
            orderNo: 'ORD-TEST-123',
            gatewayTxnId: 'TXN-ABC-999',
            amountCents: 500000,
            status: PaymentStatus::SETTLED,
            gateway: 'payengine',
            rawPayload: ['foo' => 'bar']
        );

        $this->assertSame('ORD-TEST-123', $payload->orderNo);
        $this->assertSame('TXN-ABC-999', $payload->gatewayTxnId);
        $this->assertSame(500000, $payload->amountCents);
        $this->assertSame(PaymentStatus::SETTLED, $payload->status);
        $this->assertSame('payengine', $payload->gateway);
        $this->assertSame(['foo' => 'bar'], $payload->rawPayload);
    }

    public function test_refund_result_dto_structure(): void
    {
        $result = new RefundResult(
            success: true,
            refundRef: 'REF-001',
            message: 'Settled',
            gateway: 'mock'
        );

        $this->assertTrue($result->success);
        $this->assertSame('REF-001', $result->refundRef);
        $this->assertSame('Settled', $result->message);
        $this->assertSame('mock', $result->gateway);
    }
}
