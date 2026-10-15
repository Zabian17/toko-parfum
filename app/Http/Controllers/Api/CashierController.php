<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashierController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * Calculate order total WITHOUT creating an order.
     * Used by frontend cashier to preview total before confirming.
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.product_id'       => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'         => ['required', 'integer', 'min:1'],
            'shipping_cost'            => ['nullable', 'numeric', 'min:0'],
        ]);

        $subtotal    = 0;
        $totalWeight = 0;
        $lines       = [];

        foreach ($request->items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $variant = !empty($item['product_variant_id'])
                ? ProductVariant::with('tier')->findOrFail($item['product_variant_id'])
                : null;

            // Price always comes from the tier master
            $price        = $variant ? (float) $variant->tier->price : 0.0;
            $qty          = (int) $item['quantity'];
            $itemSubtotal = $price * $qty;
            $subtotal    += $itemSubtotal;

            $lines[] = [
                'product_id'         => $product->id,
                'product_name'       => $product->name,
                'variant_id'         => $variant?->id,
                'variant_name'       => $variant?->tier?->label,
                'tier'               => $variant?->tier,
                'unit_price'         => $price,
                'quantity'           => $qty,
                'subtotal'           => $itemSubtotal,
                'available_stock'    => $variant ? $variant->stock : 0,
            ];
        }

        $shippingCost = (float) ($request->shipping_cost ?? 0);
        $totalAmount  = $subtotal + $shippingCost;

        return response()->json([
            'data' => [
                'lines'         => $lines,
                'subtotal'      => $subtotal,
                'shipping_cost' => $shippingCost,
                'total_amount'  => $totalAmount,
                'total_weight'  => $totalWeight,
                'items_count'   => array_sum(array_column($request->items, 'quantity')),
            ],
        ]);
    }

    /**
     * Create an offline / cashier order.
     * shipping_cost defaults to 0 for walk-in customers.
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $data                   = $request->validated();
        $data['order_type']     = 'offline';
        $data['payment_method'] = $data['payment_method'] ?? 'cash';
        $data['payment_status'] = 'paid';
        $data['status']         = 'confirmed';

        // Default in-store shipping fields if not provided
        $data['shipping_courier'] = $data['shipping_courier'] ?? 'pickup';
        $data['shipping_service'] = $data['shipping_service'] ?? 'Ambil Langsung';
        $data['shipping_cost']    = $data['shipping_cost'] ?? 0;

        $order = $this->orderService->checkout($data);

        return response()->json(['data' => new OrderResource($order)], 201);
    }
}
