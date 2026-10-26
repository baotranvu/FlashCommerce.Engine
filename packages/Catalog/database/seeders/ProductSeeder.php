<?php

namespace FlashCommerce\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds 10,000 realistic products in batches of 1,000 for sub-2-second performance.
     */
    public function run(): void
    {
        $categories = [
            'TECH' => [
                'prefix' => 'TECH',
                'models' => ['iPhone 15 Pro Max', 'MacBook Pro M3', 'Sony WH-1000XM5', 'Dell XPS 15', 'Samsung S24 Ultra', 'iPad Air 11-inch', 'AirPods Pro 2', 'Logitech MX Master 3S', 'Keychron Q1 Pro', 'LG UltraFine 4K Monitor'],
                'min_price' => 1500000,
                'max_price' => 38000000,
            ],
            'FASH' => [
                'prefix' => 'FASH',
                'models' => ['Nike Air Max 90', 'Adidas Ultraboost Light', 'Levi 501 Original Jeans', 'Uniqlo AIRism Oversized Tee', 'The North Face Rain Jacket', 'New Balance 990v6', 'Puma Suede Classic', 'Zara Slim Fit Chinos'],
                'min_price' => 250000,
                'max_price' => 4800000,
            ],
            'HOME' => [
                'prefix' => 'HOME',
                'models' => ['Dyson V12 Detect Slim', 'Philips XXL Air Fryer', 'Nespresso Vertuo Pop Machine', 'DeLonghi Dedica Espresso', 'Xiaomi Robot Vacuum X10', 'LocknLock Boroseal Glassware Set', 'Panasonic Inverter Microwave'],
                'min_price' => 350000,
                'max_price' => 18000000,
            ],
            'BOOK' => [
                'prefix' => 'BOOK',
                'models' => ['Designing Data-Intensive Applications', 'Clean Architecture (Robert Martin)', 'System Design Interview Vol 1 & 2', 'Refactoring (Martin Fowler)', 'Building Microservices 2nd Ed', 'Database Internals (Alex Petrov)', 'Site Reliability Engineering (Google)'],
                'min_price' => 180000,
                'max_price' => 950000,
            ],
        ];

        $totalRecords = 10000;
        $batchSize = 1000;
        $records = [];
        $now = now();
        $catKeys = array_keys($categories);

        for ($i = 1; $i <= $totalRecords; $i++) {
            $catKey = $catKeys[$i % 4];
            $cat = $categories[$catKey];
            $baseName = $cat['models'][$i % count($cat['models'])];
            $variantCode = sprintf('%04d', ($i % 1000) + 1);
            $sku = sprintf('%s-%s-%05d', $cat['prefix'], strtoupper(substr(md5((string)$i), 0, 4)), $i);
            $name = sprintf('%s (Edition #%s)', $baseName, $variantCode);

            // Realistic pricing in smallest integer currency (VND)
            $price = (int) (round(rand($cat['min_price'], $cat['max_price']) / 10000) * 10000);

            // Realistic inventory distribution:
            // 80% healthy stock (50 - 500 units)
            // 15% flash-sale scarce stock (1 - 5 units for concurrency testing)
            // 5% completely out-of-stock (0 units)
            $stockRoll = rand(1, 100);
            if ($stockRoll <= 5) {
                $physicalStock = 0;
            } elseif ($stockRoll <= 20) {
                $physicalStock = rand(1, 5);
            } else {
                $physicalStock = rand(50, 500);
            }

            // 98% active, 2% archived/inactive
            $isActive = ($i % 50 !== 0);

            $records[] = [
                'sku' => $sku,
                'name' => $name,
                'price' => $price,
                'physical_stock' => $physicalStock,
                'reserved_stock' => 0,
                'is_active' => $isActive,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($records) === $batchSize) {
                DB::table('products')->insert($records);
                $records = [];
            }
        }

        if (!empty($records)) {
            DB::table('products')->insert($records);
        }
    }
}
