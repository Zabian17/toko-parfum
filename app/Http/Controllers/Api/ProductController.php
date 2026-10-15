<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductTierResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductTier;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $service) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->service->list($request->only([
            'search', 'category_id', 'is_active', 'is_featured',
            'tag', 'sort', 'order', 'per_page', 'in_stock',
            'grade', 'category_ids', 'filters', 'sorters',
        ]));

        return response()->json($products->through(fn($p) => new ProductResource($p)));
    }

    public function tiers(): JsonResponse
    {
        $tiers = ProductTier::orderBy('sort_order')->get();
        return response()->json(['data' => ProductTierResource::collection($tiers)]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'images', 'variants.tier', 'tags']);

        return response()->json(['data' => new ProductResource($product)]);
    }

    public function showBySlug(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)
            ->with(['category', 'images', 'variants.tier', 'tags'])
            ->firstOrFail();

        return response()->json(['data' => new ProductResource($product)]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->service->create(
            $request->except(['thumbnail', 'images']),
            $request->file('thumbnail'),
            $request->file('images') ?? []
        );

        return response()->json(['data' => new ProductResource($product)], 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->service->update(
            $product,
            $request->except(['thumbnail', 'images']),
            $request->file('thumbnail'),
            $request->file('images') ?? []
        );

        return response()->json(['data' => new ProductResource($product)]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->service->delete($product);

        return response()->json(['message' => 'Product deleted.']);
    }

    public function deleteImage(ProductImage $image): JsonResponse
    {
        $this->service->deleteImage($image);

        return response()->json(['message' => 'Image deleted.']);
    }
}
