<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $service) {}

    public function index(Request $request): JsonResponse
    {
        $categories = $this->service->list($request->only([
            'search', 'is_active', 'parent_id', 'per_page', 'filters', 'sorters',
        ]));

        if ($categories instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            return response()->json(CategoryResource::collection($categories));
        }

        return response()->json([
            'data' => CategoryResource::collection($categories),
        ]);
    }

    public function show(Category $category): JsonResponse
    {
        $category->load(['parent', 'children', 'products']);

        return response()->json(['data' => new CategoryResource($category)]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->service->create(
            $request->except('image'),
            $request->file('image')
        );

        return response()->json(['data' => new CategoryResource($category)], 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->service->update(
            $category,
            $request->except('image'),
            $request->file('image')
        );

        return response()->json(['data' => new CategoryResource($category)]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->service->delete($category);

        return response()->json(['message' => 'Category deleted.']);
    }
}
