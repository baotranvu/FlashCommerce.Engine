<?php

namespace FlashCommerce\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use FlashCommerce\Catalog\Domain\Repositories\ProductRepositoryInterface;
use FlashCommerce\Catalog\Http\Resources\ProductResource;

class ProductCatalogController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $cursor = $request->query('cursor') !== null ? (int) $request->query('cursor') : null;
        $limit = min(
            (int) ($request->query('limit', config('catalog.pagination.default_limit', 20))),
            (int) config('catalog.pagination.max_limit', 100)
        );

        $result = $this->productRepository->findKeysetSeek($cursor, $limit);

        return response()->json([
            'data' => ProductResource::collection($result['items']),
            'meta' => [
                'limit' => $limit,
                'next_cursor' => $result['next_cursor'],
                'has_more' => $result['has_more'],
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            return response()->json([
                'type' => 'https://errors.flashcommerce.com/product-not-found',
                'title' => 'Not Found',
                'status' => 404,
                'detail' => "Product with ID {$id} was not found.",
            ], 404);
        }

        return response()->json([
            'data' => new ProductResource($product),
        ]);
    }

    public function demoUnderflow(Request $request): JsonResponse
    {
        $productId = (int) $request->query('product_id', 1);
        $deductQty = (int) $request->query('quantity', 10);

        $product = DB::table('products')->where('id', $productId)->first();
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $caughtException = null;
        $sqlUnsafe = "UPDATE products SET physical_stock = physical_stock - ? WHERE id = ?";
        try {
            DB::update($sqlUnsafe, [$deductQty, $productId]);
        } catch (\Throwable $e) {
            $caughtException = [
                'error_code' => $e->getCode(),
                'message' => $e->getMessage(),
                'sql' => $sqlUnsafe,
            ];

            Log::channel('telemetry')->error('MySQL 1690 Underflow Caught via Demo API', [
                'product_id' => $productId,
                'current_stock' => $product->physical_stock,
                'requested_deduction' => $deductQty,
                'exception' => $caughtException,
            ]);
        }

        // Safe conditional update test
        $sqlSafe = "UPDATE products SET physical_stock = physical_stock - ? WHERE id = ? AND physical_stock >= ?";
        $safeAffected = DB::update($sqlSafe, [$deductQty, $productId, $deductQty]);

        return response()->json([
            'simulation' => 'MySQL 1690 Unsigned Underflow vs Safe Conditional Decrement',
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'current_physical_stock' => $product->physical_stock,
                'attempted_deduction' => $deductQty,
            ],
            'stage_1_naive_deduction' => [
                'query' => $sqlUnsafe,
                'crashed' => $caughtException !== null,
                'mysql_error' => $caughtException,
            ],
            'stage_2_safe_atomic_deduction' => [
                'query' => $sqlSafe,
                'affected_rows' => $safeAffected,
                'zero_oversell_guaranteed' => $safeAffected === 0,
            ],
            'telemetry_log_location' => 'storage/logs/telemetry.log',
        ]);
    }

    public function demoBenchmark(Request $request): JsonResponse
    {
        $offset = (int) $request->query('offset', 9500);
        $limit = (int) $request->query('limit', 20);

        // Offset query
        $startOffset = hrtime(true);
        $offsetResults = DB::select("SELECT id, sku, name, price FROM products ORDER BY id ASC LIMIT {$limit} OFFSET {$offset}");
        $offsetDurationMs = (hrtime(true) - $startOffset) / 1e6;

        // Keyset query
        $startKeyset = hrtime(true);
        $keysetResults = DB::select("SELECT id, sku, name, price FROM products WHERE id > {$offset} ORDER BY id ASC LIMIT {$limit}");
        $keysetDurationMs = (hrtime(true) - $startKeyset) / 1e6;

        $speedup = $keysetDurationMs > 0 ? ($offsetDurationMs / $keysetDurationMs) : 1;

        Log::channel('telemetry')->info('Pagination Strategy Benchmark via API', [
            'offset' => $offset,
            'limit' => $limit,
            'offset_ms' => $offsetDurationMs,
            'keyset_ms' => $keysetDurationMs,
            'speedup' => $speedup,
        ]);

        return response()->json([
            'benchmark' => 'Keyset Seek vs Deep Offset Pagination (10,000 Products)',
            'parameters' => ['offset' => $offset, 'limit' => $limit],
            'deep_offset_method' => [
                'query' => "SELECT id, sku, name, price FROM products ORDER BY id ASC LIMIT {$limit} OFFSET {$offset}",
                'duration_ms' => round($offsetDurationMs, 3),
                'rows_examined' => "{$offset} discarded + {$limit} returned",
            ],
            'keyset_seek_method' => [
                'query' => "SELECT id, sku, name, price FROM products WHERE id > {$offset} ORDER BY id ASC LIMIT {$limit}",
                'duration_ms' => round($keysetDurationMs, 3),
                'rows_examined' => "{$limit} rows (direct B-Tree range scan)",
            ],
            'speedup_factor' => round($speedup, 1) . 'x faster',
            'telemetry_log_location' => 'storage/logs/telemetry.log',
        ]);
    }
}
