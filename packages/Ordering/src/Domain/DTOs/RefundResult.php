<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Domain\DTOs;

final readonly class RefundResult
{
    public function __construct(
        public bool $success,
        public string $refundRef,
        public string $message,
        public string $gateway
    ) {}
}
