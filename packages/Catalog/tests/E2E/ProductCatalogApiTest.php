<?php

declare(strict_types=1);

namespace FlashCommerce\Catalog\Tests\E2E;

use Illuminate\Support\Facades\Artisan;
use FlashCommerce\Catalog\Tests\CatalogTestCase;

class ProductCatalogApiTest extends CatalogTestCase
{
    public function test_get_products_list_returns_keyset_paginated_json(): void
    {
        $response = $this->getJson('/api/v1/products?limit=5');

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

        $this->assertCount(5, $response->json('data'));
    }

    public function test_get_product_detail_returns_single_product(): void
    {
        $response = $this->getJson('/api/v1/products/1');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', 1);
    }

    public function test_get_non_existent_product_returns_404(): void
    {
        $response = $this->getJson('/api/v1/products/999999');

        $response->assertStatus(404)
            ->assertJsonPath('status', 404);
    }

    public function test_artisan_demo_underflow_command_executes_safely(): void
    {
        $exitCode = Artisan::call('catalog:demo-underflow', [
            '--product-id' => 1,
            '--quantity' => 10,
        ]);

        $this->assertSame(0, $exitCode);
    }

    public function test_artisan_benchmark_pagination_command_executes_safely(): void
    {
        $exitCode = Artisan::call('catalog:benchmark-pagination', [
            '--offset' => 5000,
            '--limit' => 10,
        ]);

        $this->assertSame(0, $exitCode);
    }
}
