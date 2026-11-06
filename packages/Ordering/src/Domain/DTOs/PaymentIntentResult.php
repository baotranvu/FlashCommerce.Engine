<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Domain\DTOs;

final readonly class PaymentIntentResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $gatewayTxnId,
        public string $paymentUrl,
        public PaymentStatus $status,
        public array $metadata = []
    ) {}
}
