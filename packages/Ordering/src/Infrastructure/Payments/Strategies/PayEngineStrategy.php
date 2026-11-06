<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Infrastructure\Payments\Strategies;

use FlashCommerce\Ordering\Domain\Contracts\PaymentStrategyInterface;
use FlashCommerce\Ordering\Domain\DTOs\NormalizedWebhookPayload;
use FlashCommerce\Ordering\Domain\DTOs\PaymentIntentResult;
use FlashCommerce\Ordering\Domain\DTOs\PaymentStatus;
use FlashCommerce\Ordering\Domain\DTOs\RefundResult;
use FlashCommerce\Ordering\Domain\Exceptions\InvalidWebhookSignatureException;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class PayEngineStrategy implements PaymentStrategyInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $hmacSecret,
        private readonly int $timeoutSeconds = 5
    ) {}

    public function getIdentifier(): string
    {
        return 'payengine';
    }

    public function initiatePayment(OrderModel $order): PaymentIntentResult
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders([
                'X-Client-Id' => 'flashcommerce-ordering-v3',
                'Accept'      => 'application/json',
            ])
            ->post("{$this->baseUrl}/api/v1/payments/intents", [
                'order_no'     => $order->order_no,
                'amount_cents' => $order->total_amount,
                'currency'     => 'VND',
                'callback_url' => url('/api/v1/payments/webhooks/payengine'),
            ]);

        if (!$response->successful()) {
            Log::error("[PAYENGINE INITIATE FAILED] Order {$order->order_no} status: {$response->status()}", [
                'body' => $response->body(),
            ]);
            throw new RuntimeException("Failed to initiate payment with PayEngine: {$response->body()}");
        }

        $data = $response->json();
        $txnId = (string) ($data['txn_id'] ?? $data['id'] ?? '');
        $paymentUrl = (string) ($data['checkout_url'] ?? "{$this->baseUrl}/checkout/{$txnId}");

        return new PaymentIntentResult(
            gatewayTxnId: $txnId,
            paymentUrl: $paymentUrl,
            status: PaymentStatus::PENDING,
            metadata: $data ?? []
        );
    }

    public function verifyAndParseWebhook(Request $request): NormalizedWebhookPayload
    {
        $signature = (string) $request->header('X-PayEngine-Signature', '');
        $payloadRaw = (string) $request->getContent();

        $expected = hash_hmac('sha256', $payloadRaw, $this->hmacSecret);

        if (!hash_equals($expected, $signature)) {
            Log::warning("[PAYENGINE WEBHOOK REJECTED] HMAC mismatch. Expected: {$expected}, Received: {$signature}");
            throw new InvalidWebhookSignatureException('Invalid PayEngine HMAC-SHA256 signature.');
        }

        $data = $request->json()->all();
        $orderNo = (string) ($data['order_no'] ?? '');
        $gatewayTxnId = (string) ($data['txn_id'] ?? '');
        $amountCents = (int) ($data['amount_cents'] ?? 0);
        $statusString = strtoupper((string) ($data['status'] ?? ''));

        $status = match ($statusString) {
            'SETTLED', 'SUCCESS', 'PAID' => PaymentStatus::SETTLED,
            'FAILED'                     => PaymentStatus::FAILED,
            'REFUNDED'                   => PaymentStatus::REFUNDED,
            default                      => PaymentStatus::PENDING,
        };

        return new NormalizedWebhookPayload(
            orderNo: $orderNo,
            gatewayTxnId: $gatewayTxnId,
            amountCents: $amountCents,
            status: $status,
            gateway: $this->getIdentifier(),
            rawPayload: $data
        );
    }

    public function refund(string $gatewayTxnId, int $amountCents, string $reason): RefundResult
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders([
                'X-Client-Id' => 'flashcommerce-ordering-v3',
                'Accept'      => 'application/json',
            ])
            ->post("{$this->baseUrl}/api/v1/payments/refunds", [
                'original_txn_id' => $gatewayTxnId,
                'amount_cents'    => $amountCents,
                'reason'          => $reason,
            ]);

        if (!$response->successful()) {
            Log::error("[PAYENGINE REFUND FAILED] Txn {$gatewayTxnId} status: {$response->status()}", [
                'body' => $response->body(),
            ]);

            return new RefundResult(
                success: false,
                refundRef: '',
                message: "PayEngine refund API call failed: {$response->body()}",
                gateway: $this->getIdentifier()
            );
        }

        $data = $response->json();
        $refundRef = (string) ($data['refund_ref'] ?? $data['id'] ?? 'REF-PAYENGINE-ACK');

        return new RefundResult(
            success: true,
            refundRef: $refundRef,
            message: 'Compensating refund settled by PayEngine',
            gateway: $this->getIdentifier()
        );
    }
}
