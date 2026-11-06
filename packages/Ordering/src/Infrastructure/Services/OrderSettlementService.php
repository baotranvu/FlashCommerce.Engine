<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Infrastructure\Services;

use FlashCommerce\Ordering\Domain\DTOs\NormalizedWebhookPayload;
use FlashCommerce\Ordering\Domain\DTOs\PaymentStatus;
use FlashCommerce\Ordering\Domain\Services\OrderSettlementServiceInterface;
use FlashCommerce\Ordering\Infrastructure\Payments\PaymentManager;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderRefundModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class OrderSettlementService implements OrderSettlementServiceInterface
{
    public function __construct(
        private readonly PaymentManager $paymentManager
    ) {}

    public function settle(NormalizedWebhookPayload $payload): ?OrderModel
    {
        $lockKey = "settle:lock:{$payload->orderNo}";
        $lockToken = (string) Str::uuid();

        // 1. Acquire Redis distributed lock (10s TTL) to prevent parallel race conditions
        $acquired = (bool) Redis::set($lockKey, $lockToken, 'NX', 'EX', 10);
        if (!$acquired) {
            Log::warning("[SETTLEMENT LOCK_CONTENTION] Order {$payload->orderNo} currently locked by another worker.");
            return null;
        }

        try {
            return DB::transaction(function () use ($payload) {
                /** @var OrderModel|null $order */
                $order = OrderModel::query()
                    ->where('order_no', $payload->orderNo)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    Log::error("[SETTLEMENT NOT_FOUND] Order {$payload->orderNo} not found in database.");
                    return null;
                }

                // CASE 1: IDEMPOTENCY & DUPLICATE PAYMENT HANDLING
                if ($order->status === 'PAID') {
                    if ($order->gateway_transaction_id === $payload->gatewayTxnId) {
                        Log::info("[SETTLEMENT IDEMPOTENT] Order {$order->order_no} already settled with txn {$payload->gatewayTxnId}.");
                        return $order;
                    }

                    // Cross-Gateway Double Payment Race!
                    Log::warning("[SETTLEMENT DUPLICATE_PAYMENT] Order {$order->order_no} already PAID via {$order->gateway_provider}. Initiating automated refund on {$payload->gateway}.");
                    $this->triggerCompensatingRefund(
                        order: $order,
                        payload: $payload,
                        reason: 'DUPLICATE_PAYMENT_ACROSS_GATEWAYS'
                    );

                    return $order;
                }

                // CASE 2: GHOST PAYMENT RACE (Hold timed out by Sweeper, Redis stock already returned)
                if ($order->status === 'EXPIRED') {
                    Log::warning("[SETTLEMENT GHOST_PAYMENT] Order {$order->order_no} was EXPIRED prior to payment arrival. REFUSING MySQL stock decrement.");

                    $order->status = 'REFUND_PENDING';
                    $order->save();

                    $this->triggerCompensatingRefund(
                        order: $order,
                        payload: $payload,
                        reason: 'EXPIRED_HOLD_GHOST_PAYMENT'
                    );

                    return $order;
                }

                // CASE 3: PAYMENT FAILED FROM GATEWAY
                if ($payload->status === PaymentStatus::FAILED) {
                    Log::info("[SETTLEMENT PAYMENT_FAILED] Order {$order->order_no} payment failed via gateway {$payload->gateway}.");
                    $order->status = 'FAILED';
                    $order->gateway_provider = $payload->gateway;
                    $order->gateway_transaction_id = $payload->gatewayTxnId;
                    $order->save();

                    // Remove from delayed expiration queue
                    Redis::zrem('flash_sale:expirations', $order->order_no);
                    return $order;
                }

                // CASE 4: NORMAL SETTLEMENT (RESERVED / PENDING -> PAID)
                if (in_array($order->status, ['RESERVED', 'PENDING'], true) && $payload->status === PaymentStatus::SETTLED) {
                    // Atomic physical stock decrement for each line item
                    foreach ($order->items as $item) {
                        $affected = DB::table('products')
                            ->where('id', $item->product_id)
                            ->where('physical_stock', '>=', $item->quantity)
                            ->decrement('physical_stock', $item->quantity);

                        if ($affected === 0) {
                            Log::critical("[SETTLEMENT INVENTORY_DEFICIT] Insufficient physical stock for Product #{$item->product_id} in Order {$order->order_no}. Triggering emergency refund.");
                            $order->status = 'REFUND_PENDING';
                            $order->save();

                            $this->triggerCompensatingRefund(
                                order: $order,
                                payload: $payload,
                                reason: 'INSUFFICIENT_PHYSICAL_STOCK_DEFICIT'
                            );

                            return $order;
                        }
                    }

                    // Settle order status to PAID
                    $order->status = 'PAID';
                    $order->gateway_provider = $payload->gateway;
                    $order->gateway_transaction_id = $payload->gatewayTxnId;
                    $order->paid_at = now();
                    $order->save();

                    // Evict from Redis ZSET delayed expiration queue
                    Redis::zrem('flash_sale:expirations', $order->order_no);

                    Log::info("[SETTLEMENT SUCCESS] Order {$order->order_no} successfully settled and physical stock decremented.");
                    return $order;
                }

                return $order;
            });
        } finally {
            // Release Redis distributed lock via Lua atomic token match
            $lua = <<<'LUA'
                if redis.call("get", KEYS[1]) == ARGV[1] then
                    return redis.call("del", KEYS[1])
                else
                    return 0
                end
            LUA;
            Redis::eval($lua, 1, $lockKey, $lockToken);
        }
    }

    private function triggerCompensatingRefund(OrderModel $order, NormalizedWebhookPayload $payload, string $reason): void
    {
        $refundRecord = OrderRefundModel::query()->create([
            'order_no'               => $order->order_no,
            'gateway_provider'       => $payload->gateway,
            'gateway_transaction_id' => $payload->gatewayTxnId,
            'amount_cents'           => $payload->amountCents,
            'reason'                 => $reason,
            'status'                 => 'PENDING',
        ]);

        try {
            $strategy = $this->paymentManager->driver($payload->gateway);
            $refundResult = $strategy->refund(
                gatewayTxnId: $payload->gatewayTxnId,
                amountCents: $payload->amountCents,
                reason: $reason
            );

            if ($refundResult->success) {
                $refundRecord->update([
                    'status'           => 'SUCCESS',
                    'refund_reference' => $refundResult->refundRef,
                ]);

                $order->status = 'REFUNDED';
                $order->refunded_at = now();
                $order->save();

                Log::info("[REFUND SUCCESS] Order {$order->order_no} refunded via {$payload->gateway} (Ref: {$refundResult->refundRef}).");
            } else {
                $refundRecord->update(['status' => 'FAILED']);
                Log::error("[REFUND FAILED] Gateway returned failure: {$refundResult->message}");
            }
        } catch (\Throwable $e) {
            $refundRecord->update(['status' => 'FAILED']);
            Log::error("[REFUND EXCEPTION] Failed to issue refund for Order {$order->order_no}: {$e->getMessage()}");
        }
    }
}
