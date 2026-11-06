<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Domain\DTOs;

enum PaymentStatus: string
{
    case PENDING  = 'PENDING';
    case SETTLED  = 'SETTLED';
    case FAILED   = 'FAILED';
    case REFUNDED = 'REFUNDED';
}
