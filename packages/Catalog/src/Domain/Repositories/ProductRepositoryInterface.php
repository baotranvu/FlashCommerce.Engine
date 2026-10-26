<?php

namespace FlashCommerce\Catalog\Domain\Repositories;

use FlashCommerce\Catalog\Domain\Model\Product;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface
{
    /**
     * Find product by primary ID.
     */
    public function findById(int $id): ?Product;

    /**
     * Find product by unique SKU.
     */
    public function findBySku(string $sku): ?Product;

    /**
     * Keyset Seek Pagination (O(log N) B-Tree cursor seek).
     *
     * @param int|null $cursor The last seen product ID.
     * @param int $limit Max items per page.
     * @return array{items: Collection<int, Product>, next_cursor: int|null, has_more: bool}
     */
    public function findKeysetSeek(?int $cursor = null, int $limit = 20): array;
}
