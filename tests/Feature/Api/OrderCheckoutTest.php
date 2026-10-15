<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductTier;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function setupProductWithTier(int $stock = 10): array
    {
        $category = Category::create([
            'name'      => 'Citrus',
            'slug'      => 'citrus',
            'is_active' => true,
        ]);

        $tier = ProductTier::create([
            'grade'      => 'premium',
            'size_ml'    => 30,
            'price'      => 35000,
            'label'      => 'Premium 30ml',
            'sort_order' => 1,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Citrus Fresh',
            'slug'        => 'citrus-fresh',
            'stock'       => $stock,
            'is_active'   => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'tier_id'    => $tier->id,
            'stock'      => $stock,
            'is_active'  => true,
        ]);

        return [$product, $variant, $tier];
    }

    public function test_checkout_successfully_creates_order_and_deducts_stock(): void
    {
        [$product, $variant] = $this->setupProductWithTier(stock: 10);

        $payload = [
            'customer_name'        => 'Budi Santoso',
            'customer_email'       => 'budi@example.com',
            'customer_phone'       => '08123456789',
            'shipping_address'     => 'Jl. Mawar No. 12',
            'shipping_city'        => 'Jakarta Selatan',
            'shipping_province'    => 'DKI Jakarta',
            'shipping_postal_code' => '12340',
            'shipping_cost'        => 15000,
            'payment_method'       => 'transfer',
            'items'                => [
                [
                    'product_id'         => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity'           => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/checkout', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('data.customer_name', 'Budi Santoso')
                 ->assertJsonPath('data.subtotal', 70000)
                 ->assertJsonPath('data.total_amount', 85000);

        // Verify stock deducted
        $this->assertEquals(8, $variant->fresh()->stock);
        $this->assertEquals(8, $product->fresh()->stock);

        // Verify order in database
        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Budi Santoso',
            'total_amount'  => 85000,
        ]);
    }

    public function test_checkout_fails_when_stock_insufficient(): void
    {
        [$product, $variant] = $this->setupProductWithTier(stock: 1);

        $payload = [
            'customer_name'        => 'Budi Santoso',
            'customer_phone'       => '08123456789',
            'shipping_address'     => 'Jl. Mawar No. 12',
            'shipping_city'        => 'Jakarta Selatan',
            'shipping_province'    => 'DKI Jakarta',
            'shipping_postal_code' => '12340',
            'payment_method'       => 'transfer',
            'items'                => [
                [
                    'product_id'         => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity'           => 5,
                ],
            ],
        ];

        $response = $this->postJson('/api/checkout', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['stock']);

        // Stock remains unchanged
        $this->assertEquals(1, $variant->fresh()->stock);
    }

    public function test_cashier_calculate_and_offline_order(): void
    {
        [$product, $variant] = $this->setupProductWithTier(stock: 20);

        $admin = \App\Models\User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($admin);

        // Calculate
        $calcPayload = [
            'items' => [
                [
                    'product_id'         => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity'           => 3,
                ],
            ],
        ];

        $calcResponse = $this->postJson('/api/admin/cashier/calculate', $calcPayload);
        $calcResponse->assertStatus(200)
                     ->assertJsonPath('data.subtotal', 105000)
                     ->assertJsonPath('data.total_amount', 105000);

        // Cashier walk-in store order
        $orderPayload = [
            'customer_name'  => 'Pelanggan Walk-in',
            'customer_phone' => '08999999999',
            'payment_method' => 'cash',
            'items'          => [
                [
                    'product_id'         => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity'           => 3,
                ],
            ],
        ];

        $orderResponse = $this->postJson('/api/admin/cashier/order', $orderPayload);
        $orderResponse->assertStatus(201)
                      ->assertJsonPath('data.payment_status', 'paid')
                      ->assertJsonPath('data.status', 'confirmed');

        $this->assertEquals(17, $variant->fresh()->stock);
    }

    public function test_updating_order_status_to_delivered_automatically_changes_unpaid_to_paid(): void
    {
        $admin = \App\Models\User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($admin);

        $order = Order::create([
            'order_number'     => 'ORD-TEST-001',
            'order_type'       => 'online',
            'customer_name'    => 'Ahmad',
            'customer_email'   => 'ahmad@example.com',
            'customer_phone'   => '08111111111',
            'shipping_address' => 'Jl. Kenanga No. 10',
            'subtotal'         => 50000,
            'shipping_cost'    => 10000,
            'total_amount'     => 60000,
            'payment_method'   => 'transfer',
            'payment_status'   => 'unpaid',
            'status'           => 'shipped',
        ]);

        $response = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'delivered',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'delivered')
                 ->assertJsonPath('data.payment_status', 'paid');

        $fresh = $order->fresh();
        $this->assertEquals('delivered', $fresh->status);
        $this->assertEquals('paid', $fresh->payment_status);
        $this->assertNotNull($fresh->paid_at);
    }

    public function test_updating_order_status_to_delivered_preserves_existing_paid_at(): void
    {
        $admin = \App\Models\User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($admin);

        $originalPaidAt = now()->subDays(2)->startOfSecond();
        $order = Order::create([
            'order_number'     => 'ORD-TEST-002',
            'order_type'       => 'online',
            'customer_name'    => 'Siti',
            'customer_email'   => 'siti@example.com',
            'customer_phone'   => '08222222222',
            'shipping_address' => 'Jl. Melati No. 5',
            'subtotal'         => 50000,
            'shipping_cost'    => 10000,
            'total_amount'     => 60000,
            'payment_method'   => 'midtrans',
            'payment_status'   => 'paid',
            'paid_at'          => $originalPaidAt,
            'status'           => 'shipped',
        ]);

        $response = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'delivered',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'delivered')
                 ->assertJsonPath('data.payment_status', 'paid');

        $this->assertEquals(
            $originalPaidAt->format('Y-m-d H:i:s'),
            $order->fresh()->paid_at->format('Y-m-d H:i:s')
        );
    }
}
