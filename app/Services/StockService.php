<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function adjustStock(
        int $productId,
        ?int $variantId,
        string $type,
        int $quantity,
        ?int $orderId = null,
        ?string $reference = null,
        ?string $notes = null
    ): StockMovement {
        return DB::transaction(function () use ($productId, $variantId, $type, $quantity, $orderId, $reference, $notes) {
            if ($variantId) {
                $target = ProductVariant::lockForUpdate()->findOrFail($variantId);
                $stockBefore = $target->stock;
            } else {
                $target = Product::lockForUpdate()->findOrFail($productId);
                $stockBefore = $target->stock;
            }

            $delta = in_array($type, ['out']) ? -abs($quantity) : abs($quantity);
            if ($type === 'adjustment') {
                $delta = $quantity; // can be negative
            }

            $stockAfter = $stockBefore + $delta;

            $target->update(['stock' => max(0, $stockAfter)]);

            // Keep product stock in sync if variant
            if ($variantId) {
                $product = Product::find($productId);
                $totalStock = $product->variants()->sum('stock');
                $product->update(['stock' => $totalStock]);
            }

            return StockMovement::create([
                'product_id'         => $productId,
                'product_variant_id' => $variantId,
                'order_id'           => $orderId,
                'type'               => $type,
                'quantity'           => $quantity,
                'stock_before'       => $stockBefore,
                'stock_after'        => max(0, $stockAfter),
                'reference'          => $reference,
                'notes'              => $notes,
                'created_by'         => Auth::id(),
            ]);
        });
    }

    public function deductStock(
        int $productId,
        ?int $variantId,
        int $quantity,
        int $orderId,
        string $reference
    ): StockMovement {
        return $this->adjustStock(
            productId : $productId,
            variantId : $variantId,
            type      : 'out',
            quantity  : $quantity,
            orderId   : $orderId,
            reference : $reference,
            notes     : 'Deducted from order ' . $reference,
        );
    }

    public function history(array $filters = []): LengthAwarePaginator
    {
        $query = StockMovement::with(['product', 'variant'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }
}
