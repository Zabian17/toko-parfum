<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTier;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_access_protected_endpoints(): void
    {
        $role = Role::create([
            'name'        => 'Super Admin',
            'slug'        => 'super-admin',
            'permissions' => ['*'],
            'is_super'    => true,
        ]);

        $admin = User::create([
            'name'     => 'Admin Test',
            'email'    => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role_id'  => $role->id,
        ]);

        // Login
        $loginResponse = $this->postJson('/api/admin/auth/login', [
            'email'    => 'admin@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200)
                      ->assertJsonStructure(['token', 'user']);

        $token = $loginResponse->json('token');

        // Access protected endpoint with token
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        $response = $this->getJson('/api/admin/dashboard');
        $response->assertStatus(401);
    }

    public function test_admin_can_adjust_stock(): void
    {
        $role = Role::create([
            'name'     => 'Admin',
            'slug'     => 'admin',
            'is_super' => true,
        ]);

        $admin = User::create([
            'name'     => 'Admin Test',
            'email'    => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role_id'  => $role->id,
        ]);

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
            'stock'       => 10,
            'is_active'   => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'tier_id'    => $tier->id,
            'stock'      => 10,
            'is_active'  => true,
        ]);

        $token = $admin->createToken('admin-panel')->plainTextToken;

        // Inbound stock adjustment (+5)
        $adjustResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/admin/stock/adjust', [
                'product_id'         => $product->id,
                'product_variant_id' => $variant->id,
                'type'               => 'in',
                'quantity'           => 5,
                'reference'          => 'PO-001',
                'notes'              => 'Restock batch 1',
            ]);

        $adjustResponse->assertStatus(201)
                       ->assertJsonPath('data.stock_before', 10)
                       ->assertJsonPath('data.stock_after', 15);

        $this->assertEquals(15, $variant->fresh()->stock);
        $this->assertEquals(15, $product->fresh()->stock);
    }
}
