<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Domain\DTOs;

final readonly class NormalizedWebhookPayload
{
    /**
     * @param array<string, mixed> $rawPayload
     */
    public function __construct(
        public string $orderNo,
        public string $gatewayTxnId,
        public int $amountCents,
        public PaymentStatus $status,
        public string $gateway,
        public array $rawPayload = []
    ) {}
}
