<?php

declare(strict_types=1);

namespace FlashCommerce\Catalog\Tests\E2E;

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

    public function test_demo_underflow_endpoint_captures_mysql_1690_and_safe_deduction(): void
    {
        $response = $this->getJson('/api/v1/products/demo/underflow?product_id=1&quantity=1000');

        $response->assertStatus(200)
            ->assertJsonPath('simulation', 'MySQL 1690 Unsigned Underflow vs Safe Conditional Decrement')
            ->assertJsonPath('stage_1_naive_deduction.crashed', true)
            ->assertJsonPath('stage_1_naive_deduction.mysql_error.error_code', '22003')
            ->assertJsonPath('stage_2_safe_atomic_deduction.zero_oversell_guaranteed', true);
    }

    public function test_demo_benchmark_endpoint_compares_keyset_and_offset(): void
    {
        $response = $this->getJson('/api/v1/products/demo/benchmark?offset=5000&limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'benchmark',
                'parameters',
                'deep_offset_method' => ['query', 'duration_ms', 'rows_examined'],
                'keyset_seek_method' => ['query', 'duration_ms', 'rows_examined'],
                'speedup_factor',
                'telemetry_log_location',
            ]);
    }
}
