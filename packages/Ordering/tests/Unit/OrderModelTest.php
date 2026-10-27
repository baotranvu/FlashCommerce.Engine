<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Tests\Unit;

use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
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
            'total_amount',
            'hold_token',
            'hold_expires_at',
            'idempotency_key',
        ];

        $this->assertSame($expectedFillable, $order->getFillable());
        $this->assertSame('orders', $order->getTable());
    }
}
