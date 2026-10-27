<?php

namespace FlashCommerce\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
}
