<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Domain\Contracts;

use FlashCommerce\Ordering\Domain\DTOs\NormalizedWebhookPayload;
use FlashCommerce\Ordering\Domain\DTOs\PaymentIntentResult;
use FlashCommerce\Ordering\Domain\DTOs\RefundResult;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use Illuminate\Http\Request;

interface PaymentStrategyInterface
{
    /**
     * Get unique identifier of the payment strategy (e.g. 'mock', 'payengine', 'stripe', 'vnpay').
     */
    public function getIdentifier(): string;

    /**
     * Initiate a payment intent with the external gateway provider.
     */
    public function initiatePayment(OrderModel $order): PaymentIntentResult;

    /**
     * Verify authenticity (HMAC signature, token) and parse the incoming gateway webhook into a normalized DTO.
     *
     * @throws \InvalidArgumentException if signature verification fails.
     */
    public function verifyAndParseWebhook(Request $request): NormalizedWebhookPayload;

    /**
     * Issue an atomic compensating refund directly back to the customer's payment source.
     */
    public function refund(string $gatewayTxnId, int $amountCents, string $reason): RefundResult;
}
