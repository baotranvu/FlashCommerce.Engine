<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $order_no
 * @property int $user_id
 * @property string $status
 * @property string|null $gateway_provider
 * @property string|null $gateway_transaction_id
 * @property int $total_amount
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $refunded_at
 * @property string|null $hold_token
 * @property \Illuminate\Support\Carbon|null $hold_expires_at
 * @property string|null $idempotency_key
 */
class OrderModel extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'order_no',
        'user_id',
        'status',
        'gateway_provider',
        'gateway_transaction_id',
        'total_amount',
        'paid_at',
        'refunded_at',
        'hold_token',
        'hold_expires_at',
        'idempotency_key',
    ];

    protected $casts = [
        'user_id'         => 'integer',
        'total_amount'    => 'integer',
        'hold_expires_at' => 'datetime',
        'paid_at'         => 'datetime',
        'refunded_at'     => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItemModel::class, 'order_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefundModel::class, 'order_no', 'order_no');
    }
}
