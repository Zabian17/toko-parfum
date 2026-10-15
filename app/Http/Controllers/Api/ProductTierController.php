<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductTierResource;
use App\Models\ProductTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductTierController extends Controller
{
    public function index(): JsonResponse
    {
        $tiers = ProductTier::withCount('variants')
            ->orderBy('sort_order')
            ->orderBy('grade')
            ->orderBy('size_ml')
            ->get();

        return response()->json(['data' => ProductTierResource::collection($tiers)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grade'      => ['required', Rule::in(['premium', 'super_premium'])],
            'size_ml'    => ['required', 'integer', 'min:1'],
            'price'      => ['required', 'numeric', 'min:0'],
            'label'      => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $tier = ProductTier::create($data);

        return response()->json(['data' => new ProductTierResource($tier)], 201);
    }

    public function update(Request $request, ProductTier $tier): JsonResponse
    {
        $data = $request->validate([
            'grade'      => ['sometimes', 'required', Rule::in(['premium', 'super_premium'])],
            'size_ml'    => ['sometimes', 'required', 'integer', 'min:1'],
            'price'      => ['sometimes', 'required', 'numeric', 'min:0'],
            'label'      => ['sometimes', 'required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $tier->update($data);

        return response()->json(['data' => new ProductTierResource($tier)]);
    }

    public function destroy(ProductTier $tier): JsonResponse
    {
        if ($tier->variants()->exists()) {
            return response()->json([
                'message' => 'Tier tidak dapat dihapus karena masih digunakan oleh varian produk.',
            ], 422);
        }

        $tier->delete();

        return response()->json(['message' => 'Tier berhasil dihapus.']);
    }
}
