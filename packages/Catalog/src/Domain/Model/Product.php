<?php

namespace FlashCommerce\Catalog\Domain\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property int $price
 * @property int $physical_stock
 * @property int $reserved_stock
 * @property bool $is_active
 * @property-read int $available_stock
 */
class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'sku',
        'name',
        'price',
        'physical_stock',
        'reserved_stock',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'physical_stock' => 'integer',
        'reserved_stock' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Invariant: Available Stock is the physical stock minus active reservation holds.
     */
    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->physical_stock - $this->reserved_stock);
    }

    /**
     * Check if product can satisfy the requested quantity.
     */
    public function hasAvailableStock(int $quantity = 1): bool
    {
        return $this->is_active && ($this->available_stock >= $quantity);
    }
}
