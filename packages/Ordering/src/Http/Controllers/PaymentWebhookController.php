<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Http\Controllers;

use FlashCommerce\Ordering\Domain\Services\OrderSettlementServiceInterface;
use FlashCommerce\Ordering\Infrastructure\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentManager $paymentManager,
        private readonly OrderSettlementServiceInterface $settlementService
    ) {}

    /**
     * Unified polymorphic webhook receiver for all payment gateways.
     * Endpoint: POST /api/v1/payments/webhooks/{gateway}
     */
    public function handle(string $gateway, Request $request): JsonResponse
    {
        // 1. Resolve gateway strategy
        $strategy = $this->paymentManager->driver($gateway);

        // 2. Cryptographic signature verification and payload normalization
        // Signature mismatch bubbles to self-rendering InvalidWebhookSignatureException (RFC 7807 / 401)
        $payload = $strategy->verifyAndParseWebhook($request);

        // 3. Process settlement with race condition guards
        $order = $this->settlementService->settle($payload);

        if (!$order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Order could not be settled or lock contention encountered.',
            ], 422);
        }

        return response()->json([
            'status'       => 'success',
            'order_no'     => $order->order_no,
            'order_status' => $order->status,
        ], 200);
    }
}
