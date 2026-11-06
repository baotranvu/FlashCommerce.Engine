<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $order_no
 * @property string $gateway_provider
 * @property string $gateway_transaction_id
 * @property int $amount_cents
 * @property string $reason
 * @property string $status
 * @property string|null $refund_reference
 */
class OrderRefundModel extends Model
{
    protected $table = 'order_refunds';

    protected $fillable = [
        'order_no',
        'gateway_provider',
        'gateway_transaction_id',
        'amount_cents',
        'reason',
        'status',
        'refund_reference',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_no', 'order_no');
    }
}
