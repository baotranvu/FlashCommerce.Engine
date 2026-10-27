<?php

declare(strict_types=1);

namespace FlashCommerce\Catalog\Tests\E2E;

use FlashCommerce\Catalog\Domain\Model\Product;
use FlashCommerce\Catalog\Tests\CatalogTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ProductCatalogApiTest extends CatalogTestCase
{
    use DatabaseTransactions;

    /**
     * Invariant: Catalog listing returns schema-compliant Keyset JSON with valid monotonic cursor.
     */
    public function test_get_products_list_returns_keyset_paginated_json(): void
    {
        $limit = 5;
        $response = $this->getJson("/api/v1/products?limit={$limit}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'sku',
                        'name',
                        'price',
                        'physical_stock',
                        'reserved_stock',
                        'available_stock',
                        'is_active',
                    ],
                ],
                'meta' => [
                    'limit',
                    'next_cursor',
                    'has_more',
                ],
            ]);

        $items = $response->json('data');
        $this->assertNotEmpty($items, 'Expected catalog products to be returned.');
        $this->assertLessThanOrEqual($limit, count($items));

        // Invariant: All returned items in public catalog must be active
        foreach ($items as $item) {
            $this->assertTrue($item['is_active'], "Product ID [{$item['id']}] must be active.");
        }

        // Invariant: Keyset cursor equals the last element's ID
        if ($response->json('meta.has_more')) {
            $lastItem = end($items);
            $this->assertSame(
                $lastItem['id'],
                $response->json('meta.next_cursor'),
                'Meta next_cursor must dynamically match the ID of the last item in the page.'
            );
        }
    }

    /**
     * Invariant: Product detail endpoint dynamically retrieves real dynamic record by ID.
     */
    public function test_get_product_detail_returns_single_product(): void
    {
        $target = Product::query()->where('is_active', true)->first();
        $this->assertNotNull($target, 'Database prerequisite: At least one active product must exist.');

        $response = $this->getJson("/api/v1/products/{$target->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.sku', $target->sku)
            ->assertJsonPath('data.price', $target->price)
            ->assertJsonPath('data.available_stock', $target->available_stock);
    }

    /**
     * Invariant: Non-existent product ID returns RFC 7807 404 payload safely.
     */
    public function test_get_non_existent_product_returns_404(): void
    {
        $maxId = (int) (Product::max('id') ?? 0);
        $nonExistentId = $maxId + 1000000;

        $response = $this->getJson("/api/v1/products/{$nonExistentId}");

        $response->assertStatus(404)
            ->assertJsonPath('status', 404)
            ->assertJsonPath('type', 'https://errors.flashcommerce.com/product-not-found');
    }
}
