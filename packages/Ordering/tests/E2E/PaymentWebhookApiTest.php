<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Tests\E2E;

use FlashCommerce\Ordering\Tests\OrderingTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PaymentWebhookApiTest extends OrderingTestCase
{
    use DatabaseTransactions;

    public function test_webhook_rejects_invalid_mock_signature(): void
    {
        $payload = [
            'order_no'     => 'ORD-NON-EXISTENT',
            'txn_id'       => 'MOCK-TXN-12345',
            'amount_cents' => 100000,
            'status'       => 'SETTLED',
            'signature'    => 'tampered-bad-secret',
        ];

        $response = $this->postJson('/api/v1/payments/webhooks/mock', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'status'  => 'unauthorized',
                'message' => 'Invalid mock webhook signature.',
            ]);
    }

    public function test_webhook_returns_422_when_order_not_found(): void
    {
        $payload = [
            'order_no'     => 'ORD-NON-EXISTENT-404',
            'txn_id'       => 'MOCK-TXN-12345',
            'amount_cents' => 100000,
            'status'       => 'SETTLED',
            'signature'    => 'mock-secret-key',
        ];

        $response = $this->postJson('/api/v1/payments/webhooks/mock', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}
