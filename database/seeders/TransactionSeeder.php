<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        // Ensure we have products to pick from
        $products = Product::with('variants')->get();
        if ($products->isEmpty()) return;

        // Create a pool of customers
        $customers = [];
        for ($i = 0; $i < 120; $i++) {
            $c = Customer::create([
                'name'  => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'address' => $faker->address,
                'city' => $faker->city,
                'province' => $faker->state,
                'postal_code' => $faker->postcode,
                'is_active' => true,
            ]);
            $customers[] = $c;
        }

        $start = Carbon::parse('2026-04-01');
        $end   = Carbon::today();

        $day = $start->copy();
        while ($day->lte($end)) {
            // create 10-30 orders per day
            $ordersCount = rand(10, 30);
            for ($o = 1; $o <= $ordersCount; $o++) {
                $cust = $faker->randomElement($customers);
                $orderTime = $day->copy()->setTime(rand(8, 20), rand(0,59), rand(0,59));

                $itemsCount = rand(1, 4);
                $subtotal = 0;
                $items = [];

                for ($k = 0; $k < $itemsCount; $k++) {
                    $p = $faker->randomElement($products->all());
                    $variant = null;
                    if ($p->variants && $p->variants->count()) {
                        $variant = $faker->randomElement($p->variants->all());
                    }
                    $unitPrice = $variant ? (float) ($variant->price ?? $p->price) : (float) ($p->price ?? 0);
                    $qty = rand(1, 3);
                    $lineSubtotal = $unitPrice * $qty;
                    $subtotal += $lineSubtotal;

                    $items[] = [
                        'product_id' => $p->id,
                        'product_variant_id' => $variant?->id,
                        'product_name' => $p->name,
                        'variant_name' => $variant?->name,
                        'product_sku' => $variant?->sku ?? $p->sku ?? null,
                        'product_thumbnail' => $p->thumbnail ?? null,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'subtotal' => $lineSubtotal,
                        'weight' => $p->weight ?? 0,
                    ];
                }

                $shipping = rand(0, 20000);
                $total = $subtotal + $shipping;

                $paymentStatus = $faker->randomElement(['paid','paid','paid','pending']); // mostly paid
                $status = $paymentStatus === 'paid' ? 'confirmed' : 'pending';

                $orderNumber = 'ORD-' . $day->format('Ymd') . '-' . str_pad($o, 5, '0', STR_PAD_LEFT);

                $order = Order::create([
                    'order_number' => $orderNumber,
                    'customer_id' => $cust->id,
                    'customer_name' => $cust->name,
                    'customer_email' => $cust->email,
                    'customer_phone' => $cust->phone,
                    'shipping_address' => $cust->address,
                    'shipping_city' => $cust->city,
                    'shipping_province' => $cust->province,
                    'shipping_postal_code' => $cust->postal_code,
                    'shipping_courier' => 'pickup',
                    'shipping_service' => 'Ambil Langsung',
                    'shipping_cost' => $shipping,
                    'shipping_weight' => array_sum(array_column($items, 'weight')),
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'total_amount' => $total,
                    'payment_method' => $faker->randomElement(['transfer','midtrans','cod','cash']),
                    'payment_status' => $paymentStatus,
                    'paid_at' => $paymentStatus === 'paid' ? $orderTime->toDateTimeString() : null,
                    'status' => $status,
                    'order_type' => $faker->randomElement(['online', 'offline']),
                    'notes' => null,
                    'created_at' => $orderTime->toDateTimeString(),
                    'updated_at' => $orderTime->toDateTimeString(),
                ]);

                foreach ($items as $it) {
                    $it['order_id'] = $order->id;
                    $createdItem = OrderItem::create($it);

                    // Decrement variant stock if this item uses a variant
                    if (!empty($it['product_variant_id'])) {
                        $variant = ProductVariant::find($it['product_variant_id']);
                        if ($variant) {
                            $variant->stock = max(0, ($variant->stock ?? 0) - ($it['quantity'] ?? 0));
                            $variant->save();
                        }
                    }

                    // Also decrement the parent product stock
                    $product = Product::find($it['product_id']);
                    if ($product) {
                        $product->stock = max(0, ($product->stock ?? 0) - ($it['quantity'] ?? 0));
                        $product->save();
                    }
                }
            }

            $day->addDay();
        }
    }
}
