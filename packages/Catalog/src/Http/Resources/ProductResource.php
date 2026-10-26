<?php

namespace FlashCommerce\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'price' => $this->price,
            'physical_stock' => $this->physical_stock,
            'reserved_stock' => $this->reserved_stock,
            'available_stock' => $this->available_stock,
            'is_active' => $this->is_active,
        ];
    }
}
