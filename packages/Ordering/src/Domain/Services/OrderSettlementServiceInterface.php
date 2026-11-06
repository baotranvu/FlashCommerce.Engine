<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Domain\Services;

use FlashCommerce\Ordering\Domain\DTOs\NormalizedWebhookPayload;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;

interface OrderSettlementServiceInterface
{
    /**
     * Process an incoming verified payment webhook payload and settle order state.
     */
    public function settle(NormalizedWebhookPayload $payload): ?OrderModel;
}
