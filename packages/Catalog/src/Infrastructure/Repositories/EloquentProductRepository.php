<?php

namespace FlashCommerce\Catalog\Infrastructure\Repositories;

use FlashCommerce\Catalog\Domain\Model\Product;
use FlashCommerce\Catalog\Domain\Repositories\ProductRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findById(int $id): ?Product
    {
        return Product::find($id);
    }

    public function findBySku(string $sku): ?Product
    {
        return Product::where('sku', $sku)->first();
    }

    /**
     * 🚀 Keyset Seek Pagination (Seek Method / Keyset Cursor Pagination)
     * 
     * Invariant:
     * Uses composite B-Tree index `idx_products_keyset (is_active, id, price)`.
     * Bypasses OFFSET entirely, ensuring O(log N) lookup time (< 2ms)
     * regardless of whether we are reading page 1 or page 5,000.
     */
    public function findKeysetSeek(?int $cursor = null, int $limit = 20): array
    {
        $query = Product::query()
            ->where('is_active', true);

        if ($cursor !== null && $cursor > 0) {
            $query->where('id', '>', $cursor);
        }

        // 💡 Staff Pattern: Fetch limit + 1 to eliminate expensive COUNT(*) queries
        $items = $query->orderBy('id', 'asc')
            ->limit($limit + 1)
            ->get();

        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items = $items->slice(0, $limit);
        }

        $nextCursor = $hasMore ? $items->last()?->id : null;

        return [
            'items' => $items,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }
}
