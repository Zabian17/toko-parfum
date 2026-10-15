<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StockAdjustmentRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(private readonly StockService $service) {}

    public function index(Request $request): JsonResponse
    {
        $movements = $this->service->history($request->only([
            'product_id', 'type', 'date_from', 'date_to', 'per_page',
        ]));

        return response()->json($movements->through(fn($m) => new StockMovementResource($m)));
    }

    public function adjust(StockAdjustmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $movement = $this->service->adjustStock(
            productId : $data['product_id'],
            variantId : $data['product_variant_id'] ?? null,
            type      : $data['type'],
            quantity  : $data['quantity'],
            reference : $data['reference'] ?? null,
            notes     : $data['notes'] ?? null,
        );

        return response()->json(['data' => new StockMovementResource($movement)], 201);
    }

    /**
     * Current stock levels — flat list of products (+ variants) with current stock.
     */
    public function levels(Request $request): JsonResponse
    {
        $search      = $request->input('search');
        $stockFilter = $request->input('stock_filter');
        $perPage     = (int) ($request->input('per_page', 20));
        $rawFilters  = $request->input('filters', []);
        $rawSorters  = $request->input('sorters', []);

        if (is_string($rawFilters)) {
            $decoded = json_decode($rawFilters, true);
            $rawFilters = is_array($decoded) ? $decoded : [];
        }

        if (is_string($rawSorters)) {
            $decoded = json_decode($rawSorters, true);
            $rawSorters = is_array($decoded) ? $decoded : [];
        }

        $query = Product::with('variants.tier')
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($stockFilter === 'low', fn($q) => $q->where('stock', '>', 0)->where('stock', '<=', 5))
            ->when($stockFilter === 'out', fn($q) => $q->where('stock', 0))
            ->orderBy('name');

        foreach ($rawFilters as $filter) {
            $field = $filter['field'] ?? null;
            $value = trim((string) ($filter['value'] ?? ''));

            if (!$field || $value === '') {
                continue;
            }

            if ($field === 'product_name') {
                $query->where('name', 'like', '%' . $value . '%');
            }

            if ($field === 'variant_name') {
                $query->whereHas('variants.tier', fn($t) => $t->where('label', 'like', '%' . $value . '%'));
            }

            if ($field === 'stock' && is_numeric($value)) {
                $query->where('stock', (int) $value);
            }
        }

        if (!empty($rawSorters) && is_array($rawSorters)) {
            $sorter = $rawSorters[0];
            $dir = strtolower((string) ($sorter['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            $field = $sorter['field'] ?? 'product_name';

            if ($field === 'product_name') {
                $query->reorder('name', $dir);
            } elseif ($field === 'stock') {
                $query->reorder('stock', $dir)->orderBy('name');
            }
        }

        $products = $query->paginate($perPage);

        // Flatten: each product row, then each variant row
        $rows = [];
        foreach ($products as $p) {
            $thumb = $p->thumbnail
                ? (str_starts_with($p->thumbnail, 'images/') || str_starts_with($p->thumbnail, 'http')
                    ? '/' . ltrim($p->thumbnail, '/')
                    : '/storage/' . ltrim($p->thumbnail, '/'))
                : null;

            $rows[] = [
                'product_id'   => $p->id,
                'variant_id'   => null,
                'product_name' => $p->name,
                'variant_name' => null,
                'sku'          => null,
                'stock'        => $p->stock,
                'thumbnail'    => $thumb,
            ];
            foreach ($p->variants as $v) {
                $rows[] = [
                    'product_id'   => $p->id,
                    'variant_id'   => $v->id,
                    'product_name' => $p->name,
                    'variant_name' => $v->tier?->label ?? $v->name,
                    'sku'          => null,
                    'stock'        => $v->stock,
                    'thumbnail'    => $thumb,
                ];
            }
        }

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total'       => $products->total(),
                'from'        => $products->firstItem(),
                'to'          => $products->lastItem(),
                'last_page'   => $products->lastPage(),
                'per_page'    => $products->perPage(),
                'current_page'=> $products->currentPage(),
            ],
        ]);
    }
}
