<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Tests\Unit;

use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderRefundModel;
use PHPUnit\Framework\TestCase;

class OrderModelTest extends TestCase
{
    public function test_order_model_has_expected_fillable_attributes(): void
    {
        $order = new OrderModel();

        $expectedFillable = [
            'order_no',
            'user_id',
            'status',
            'gateway_provider',
            'gateway_transaction_id',
            'total_amount',
            'paid_at',
            'refunded_at',
            'hold_token',
            'hold_expires_at',
            'idempotency_key',
        ];

        $this->assertSame($expectedFillable, $order->getFillable());
        $this->assertSame('orders', $order->getTable());
    }

    public function test_order_refund_model_has_expected_fillable_attributes(): void
    {
        $refund = new OrderRefundModel();

        $expectedFillable = [
            'order_no',
            'gateway_provider',
            'gateway_transaction_id',
            'amount_cents',
            'reason',
            'status',
            'refund_reference',
        ];

        $this->assertSame($expectedFillable, $refund->getFillable());
        $this->assertSame('order_refunds', $refund->getTable());
    }
}
