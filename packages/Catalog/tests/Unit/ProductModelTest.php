<?php

declare(strict_types=1);

namespace FlashCommerce\Catalog\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FlashCommerce\Catalog\Domain\Model\Product;

class ProductModelTest extends TestCase
{
    public function test_available_stock_calculates_difference_correctly(): void
    {
        $product = new Product();
        $product->physical_stock = 100;
        $product->reserved_stock = 25;

        $this->assertSame(75, $product->available_stock);
    }

    public function test_available_stock_clamps_to_zero_when_reserved_exceeds_physical(): void
    {
        $product = new Product();
        $product->physical_stock = 10;
        $product->reserved_stock = 15;

        $this->assertSame(0, $product->available_stock);
    }

    public function test_has_available_stock_returns_true_when_active_and_sufficient_stock(): void
    {
        $product = new Product();
        $product->physical_stock = 50;
        $product->reserved_stock = 10;
        $product->is_active = true;

        $this->assertTrue($product->hasAvailableStock(40));
        $this->assertFalse($product->hasAvailableStock(41));
    }

    public function test_has_available_stock_returns_false_when_product_inactive(): void
    {
        $product = new Product();
        $product->physical_stock = 100;
        $product->reserved_stock = 0;
        $product->is_active = false;

        $this->assertFalse($product->hasAvailableStock(1));
    }
}
