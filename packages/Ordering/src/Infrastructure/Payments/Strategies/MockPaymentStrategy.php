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
use Illuminate\Support\Str;
use InvalidArgumentException;

class MockPaymentStrategy implements PaymentStrategyInterface
{
    public function __construct(
        private readonly string $secretKey = 'mock-secret-key'
    ) {}

    public function getIdentifier(): string
    {
        return 'mock';
    }

    public function initiatePayment(OrderModel $order): PaymentIntentResult
    {
        $txnId = 'MOCK-TXN-' . strtoupper(Str::random(12));
        $paymentUrl = 'https://pay.flashcommerce.local/mock/' . $txnId;

        return new PaymentIntentResult(
            gatewayTxnId: $txnId,
            paymentUrl: $paymentUrl,
            status: PaymentStatus::PENDING,
            metadata: ['order_no' => $order->order_no]
        );
    }

    public function verifyAndParseWebhook(Request $request): NormalizedWebhookPayload
    {
        $signature = $request->header('X-Mock-Signature') ?? $request->input('signature');

        if ($signature !== $this->secretKey) {
            throw new InvalidWebhookSignatureException('Invalid mock webhook signature.');
        }

        $orderNo = (string) $request->input('order_no');
        $gatewayTxnId = (string) ($request->input('txn_id') ?? 'MOCK-TXN-' . Str::random(8));
        $amountCents = (int) $request->input('amount_cents', 0);
        $statusString = strtoupper((string) $request->input('status', 'SETTLED'));

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
            rawPayload: $request->all()
        );
    }

    public function refund(string $gatewayTxnId, int $amountCents, string $reason): RefundResult
    {
        $refundRef = 'REF-MOCK-' . strtoupper(Str::random(10));

        return new RefundResult(
            success: true,
            refundRef: $refundRef,
            message: "Mock refund executed successfully for txn {$gatewayTxnId}. Reason: {$reason}",
            gateway: $this->getIdentifier()
        );
    }
}
