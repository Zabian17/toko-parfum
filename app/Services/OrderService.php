<?php

namespace App\Services;

use App\Mail\OrderCreatedMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class OrderService
{
    public function __construct(
        private readonly StockService $stockService
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Order::with(['customer', 'items'])
            ->withCount('items');

        $columnFilters = $this->normalizePayloadArray($filters['filters'] ?? []);
        $sorters       = $this->normalizePayloadArray($filters['sorters'] ?? []);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('order_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('customer_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('customer_email', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('customer_phone', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['order_type'])) {
            $query->where('order_type', $filters['order_type']);
        }

        foreach ($columnFilters as $filter) {
            $field = $filter['field'] ?? null;
            $value = trim((string) ($filter['value'] ?? ''));

            if (!$field || $value === '') {
                continue;
            }

            if (in_array($field, ['order_number', 'customer_name', 'payment_method'], true)) {
                $query->where($field, 'like', '%' . $value . '%');
                continue;
            }

            if ($field === 'status') {
                $query->where('status', $value);
                continue;
            }

            if ($field === 'total_amount' && is_numeric($value)) {
                $query->where('total_amount', (float) $value);
                continue;
            }

            if ($field === 'created_at') {
                $query->whereDate('created_at', $value);
            }
        }

        if (!empty($sorters)) {
            $sorter = $sorters[0];
            $field = (string) ($sorter['field'] ?? 'created_at');
            $dir = strtolower((string) ($sorter['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

            if (in_array($field, ['order_number', 'customer_name', 'payment_method', 'status', 'total_amount', 'created_at'], true)) {
                $query->orderBy($field, $dir);
            } else {
                $query->orderBy('created_at', 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    private function normalizePayloadArray(mixed $payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }

    public function checkout(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $items       = $data['items'];
            $subtotal    = 0;
            $totalWeight = 0;
            $orderItems  = [];

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $variant = !empty($item['product_variant_id'])
                    ? ProductVariant::with('tier')->findOrFail($item['product_variant_id'])
                    : null;

                // Price always comes from the tier master
                if (!$variant || !$variant->tier) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator: validator([], []),
                        response: response()->json([
                            'message' => "Pilih ukuran/tier untuk produk {$product->name}.",
                            'errors'  => ['variant' => ["Pilih ukuran/tier untuk produk {$product->name}."]],
                        ], 422)
                    );
                }

                $price = (float) $variant->tier->price;
                $qty   = (int) $item['quantity'];

                // Stock validation
                $available = (int) $variant->stock;
                if ($available < $qty) {
                    $label = $product->name . " ({$variant->tier->label})";
                    throw new \Illuminate\Validation\ValidationException(
                        validator: validator([], []),
                        response: response()->json([
                            'message' => "Stok {$label} tidak mencukupi. Tersedia: {$available}",
                            'errors'  => ['stock' => ["Stok {$label} tidak mencukupi. Tersedia: {$available}"]],
                        ], 422)
                    );
                }

                $itemSubtotal = $price * $qty;
                $subtotal    += $itemSubtotal;

                $orderItems[] = [
                    'product_id'          => $product->id,
                    'product_variant_id'  => $variant->id,
                    'product_name'        => $product->name,
                    'variant_name'        => $variant->tier->label,
                    'product_sku'         => null,
                    'product_thumbnail'   => $product->thumbnail,
                    'quantity'            => $qty,
                    'unit_price'          => $price,
                    'subtotal'            => $itemSubtotal,
                    'weight'              => 0,
                ];
            }

            $shippingCost = (float) ($data['shipping_cost'] ?? 0);
            $totalAmount  = $subtotal + $shippingCost;

            // Find or create customer
            $customer = null;
            if (!empty($data['customer_phone'])) {
                $customer = \App\Models\Customer::where('phone', $data['customer_phone'])->first();
            }
            if (!$customer && !empty($data['customer_email'])) {
                $customer = \App\Models\Customer::where('email', $data['customer_email'])->first();
            }
            if (!$customer) {
                $customer = \App\Models\Customer::create([
                    'name'        => $data['customer_name'],
                    'email'       => $data['customer_email'] ?? null,
                    'phone'       => $data['customer_phone'],
                    'address'     => $data['shipping_address'] ?? null,
                    'city'        => $data['shipping_city'] ?? null,
                    'province'    => $data['shipping_province'] ?? null,
                    'postal_code' => $data['shipping_postal_code'] ?? null,
                    'is_active'   => true,
                ]);
            }

            $order = Order::create([
                'order_number'           => Order::generateOrderNumber(),
                'customer_id'            => $customer->id,
                'customer_name'          => $data['customer_name'],
                'customer_email'         => $data['customer_email'] ?? null,
                'customer_phone'         => $data['customer_phone'],
                'shipping_address'       => $data['shipping_address'] ?? '-',
                'shipping_city'          => $data['shipping_city'] ?? null,
                'shipping_province'      => $data['shipping_province'] ?? null,
                'shipping_postal_code'   => $data['shipping_postal_code'] ?? null,
                'rajaongkir_city_id'     => $data['rajaongkir_city_id'] ?? $data['shipping_city_id'] ?? null,
                'rajaongkir_province_id' => $data['rajaongkir_province_id'] ?? null,
                'shipping_courier'       => $data['shipping_courier'] ?? null,
                'shipping_service'       => $data['shipping_service'] ?? null,
                'shipping_cost'          => $shippingCost,
                'shipping_weight'        => $totalWeight,
                'subtotal'               => $subtotal,
                'discount_amount'        => 0,
                'total_amount'           => $totalAmount,
                'payment_method'         => $data['payment_method'],
                'payment_status'         => $data['payment_status'] ?? 'unpaid',
                'status'                 => $data['status'] ?? 'pending',
                'notes'                  => $data['notes'] ?? null,
                'order_type'             => $data['order_type'] ?? 'online',
            ]);

            foreach ($orderItems as $item) {
                $order->items()->create($item);

                // Deduct stock
                $this->stockService->deductStock(
                    productId        : $item['product_id'],
                    variantId        : $item['product_variant_id'],
                    quantity         : $item['quantity'],
                    orderId          : $order->id,
                    reference        : $order->order_number,
                );
            }

            // Send email notification
            if ($order->customer_email) {
                Mail::to($order->customer_email)
                    ->queue(new OrderCreatedMail($order->load('items')));
            }

            return $order->load(['items']);
        });
    }

    public function updateStatus(Order $order, array $data, ?UploadedFile $paymentProof = null): Order
    {
        if ($paymentProof) {
            if ($order->payment_proof) {
                Storage::disk('public')->delete($order->payment_proof);
            }
            $data['payment_proof'] = $paymentProof->store('payment_proofs', 'public');
        }

        if (($data['status'] ?? $order->status) === 'delivered') {
            if (empty($data['payment_status']) || in_array($data['payment_status'], ['unpaid', 'pending'])) {
                $data['payment_status'] = 'paid';
            }
        }

        if (!empty($data['payment_status']) && $data['payment_status'] === 'paid' && !$order->paid_at) {
            $data['paid_at'] = now();
        }

        $oldStatus = $order->status;
        $order->update($data);
        $order->refresh();

        if ($order->customer_email && $oldStatus !== $order->status) {
            Mail::to($order->customer_email)
                ->queue(new OrderStatusUpdatedMail($order));
        }

        return $order->load(['items', 'customer']);
    }

    public function createOfflineOrder(array $data): Order
    {
        $data['order_type']    = 'offline';
        $data['payment_method'] = $data['payment_method'] ?? 'cash';

        return $this->checkout($data);
    }
}
