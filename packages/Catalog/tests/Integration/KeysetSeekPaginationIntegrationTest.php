<?php

declare(strict_types=1);

namespace FlashCommerce\Catalog\Tests\Integration;

use FlashCommerce\Catalog\Domain\Model\Product;
use FlashCommerce\Catalog\Domain\Repositories\ProductRepositoryInterface;
use FlashCommerce\Catalog\Tests\CatalogTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class KeysetSeekPaginationIntegrationTest extends CatalogTestCase
{
    use DatabaseTransactions;

    private ProductRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(ProductRepositoryInterface::class);
    }

    /**
     * Invariant 1: First page retrieval respects limit and dynamically calculates cursor.
     */
    public function test_find_keyset_seek_retrieves_first_page_and_binds_cursor_dynamically(): void
    {
        $limit = 5;
        $result = $this->repository->findKeysetSeek(cursor: null, limit: $limit);

        $this->assertNotEmpty($result['items'], 'Expected catalog items to be returned.');
        $this->assertLessThanOrEqual($limit, $result['items']->count());

        // Invariant: All returned items must be active
        foreach ($result['items'] as $item) {
            $this->assertTrue($item->is_active, 'Keyset seek must only return active products.');
        }

        if ($result['has_more']) {
            $expectedCursor = $result['items']->last()->id;
            $this->assertSame(
                $expectedCursor,
                $result['next_cursor'],
                'B-Tree Invariant: next_cursor must strictly equal the ID of the last item in the page.'
            );
        }
    }

    /**
     * Invariant 2: Mathematical progression and zero-overlap across sequential pages.
     * Page1 ∩ Page2 = ∅ and ∀ x ∈ Page2: x.id > Page1.next_cursor.
     */
    public function test_find_keyset_seek_guarantees_strictly_monotonic_progression_and_zero_overlap(): void
    {
        $limit = 3;
        $page1 = $this->repository->findKeysetSeek(cursor: null, limit: $limit);

        if (!$page1['has_more']) {
            $this->markTestSkipped('Not enough products in database to test multi-page progression.');
        }

        $cursor = $page1['next_cursor'];
        $page2 = $this->repository->findKeysetSeek(cursor: $cursor, limit: $limit);

        $this->assertNotEmpty($page2['items'], 'Page 2 must contain items when has_more was true.');

        // 1. Monotonic Ordering Invariant: Every item in page 2 must have id > cursor
        foreach ($page2['items'] as $item) {
            $this->assertGreaterThan(
                $cursor,
                $item->id,
                "B-Tree Invariant Violation: Item ID [{$item->id}] in Page 2 is not greater than cursor [{$cursor}]!"
            );
        }

        // 2. Disjoint Invariant: Page 1 and Page 2 must share zero product IDs
        $page1Ids = $page1['items']->pluck('id')->all();
        $page2Ids = $page2['items']->pluck('id')->all();
        $overlap = array_intersect($page1Ids, $page2Ids);

        $this->assertEmpty(
            $overlap,
            'Pagination Invariant Violation: Pages must be completely disjoint. Duplicate IDs found: ' . implode(',', $overlap)
        );
    }

    /**
     * Invariant 3: Lookup by ID and SKU against real dynamic records.
     */
    public function test_find_by_id_and_sku_dynamically_resolves_existing_product(): void
    {
        $existing = Product::query()->where('is_active', true)->first();
        $this->assertNotNull($existing, 'Test prerequisite: At least one active product must exist in database.');

        $foundById = $this->repository->findById($existing->id);
        $this->assertNotNull($foundById);
        $this->assertSame($existing->id, $foundById->id);
        $this->assertSame($existing->sku, $foundById->sku);

        $foundBySku = $this->repository->findBySku($existing->sku);
        $this->assertNotNull($foundBySku);
        $this->assertSame($existing->id, $foundBySku->id);
    }

    /**
     * Invariant 4: Lookup for non-existent IDs returns null safely without exception.
     */
    public function test_find_by_id_returns_null_for_guaranteed_non_existent_id(): void
    {
        $maxId = (int) (Product::max('id') ?? 0);
        $nonExistentId = $maxId + 1000000;

        $product = $this->repository->findById($nonExistentId);

        $this->assertNull($product, 'Expected non-existent ID to return null.');
    }
}
