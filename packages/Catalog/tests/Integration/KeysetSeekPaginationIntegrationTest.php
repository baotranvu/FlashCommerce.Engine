<?php

declare(strict_types=1);

namespace FlashCommerce\Catalog\Tests\Integration;

use FlashCommerce\Catalog\Domain\Repositories\ProductRepositoryInterface;
use FlashCommerce\Catalog\Tests\CatalogTestCase;

class KeysetSeekPaginationIntegrationTest extends CatalogTestCase
{
    private ProductRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(ProductRepositoryInterface::class);
    }

    public function test_find_keyset_seek_retrieves_first_page_correctly(): void
    {
        $result = $this->repository->findKeysetSeek(cursor: null, limit: 5);

        $this->assertCount(5, $result['items']);
        $this->assertTrue($result['has_more']);
        $this->assertNotNull($result['next_cursor']);
        $this->assertSame(5, $result['next_cursor']);
    }

    public function test_find_keyset_seek_progresses_forward_with_cursor(): void
    {
        $page1 = $this->repository->findKeysetSeek(cursor: null, limit: 3);
        $this->assertCount(3, $page1['items']);
        $lastIdPage1 = $page1['items']->last()->id;

        $page2 = $this->repository->findKeysetSeek(cursor: $lastIdPage1, limit: 3);
        $this->assertCount(3, $page2['items']);
        $firstIdPage2 = $page2['items']->first()->id;

        $this->assertGreaterThan($lastIdPage1, $firstIdPage2);
    }

    public function test_find_by_id_returns_product_when_exists(): void
    {
        $product = $this->repository->findById(1);

        $this->assertNotNull($product);
        $this->assertSame(1, $product->id);
    }

    public function test_find_by_id_returns_null_for_non_existent(): void
    {
        $product = $this->repository->findById(999999);

        $this->assertNull($product);
    }
}
