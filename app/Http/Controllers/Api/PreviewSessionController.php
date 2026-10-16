<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PreviewSessionController extends Controller
{
    public function reset(Request $request): JsonResponse
    {
        if ((getenv('VERCEL_ENV') ?: '') !== 'preview') {
            abort(404);
        }

        $data = $request->validate(['session_id' => ['required', 'uuid']]);
        $sessionId = strtolower($data['session_id']);
        $marker = '[preview-session:' . $sessionId . ']';

        $deletedOrders = DB::transaction(function () use ($marker) {
            $orders = Order::query()
                ->where('admin_notes', 'like', $marker . '%')
                ->with('items')
                ->lockForUpdate()
                ->get();

            $newCustomerIds = [];
            foreach ($orders as $order) {
                if (preg_match('/\[preview-customer:(\d+)\]/', (string) $order->admin_notes, $match)) {
                    $newCustomerIds[] = (int) $match[1];
                }

                foreach ($order->items as $item) {
                    $quantity = max(0, (int) $item->quantity);
                    if ($quantity === 0) {
                        continue;
                    }

                    $variant = $item->product_variant_id
                        ? ProductVariant::query()->lockForUpdate()->find($item->product_variant_id)
                        : null;
                    if ($variant) {
                        $variant->increment('stock', $quantity);
                        $product = Product::query()->lockForUpdate()->find($item->product_id);
                        if ($product) {
                            $product->update(['stock' => $product->variants()->sum('stock')]);
                        }
                        continue;
                    }

                    Product::query()->lockForUpdate()->find($item->product_id)?->increment('stock', $quantity);
                }

                StockMovement::query()->where('order_id', $order->id)->delete();
                $order->delete();
            }

            foreach (array_unique($newCustomerIds) as $customerId) {
                Customer::query()->whereKey($customerId)->whereDoesntHave('orders')->delete();
            }

            return $orders->count();
        });

        return response()->json(['data' => ['deleted_orders' => $deletedOrders]]);
    }
}
