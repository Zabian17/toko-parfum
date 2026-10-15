<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTier;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTierApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_cannot_access_admin_tiers(): void
    {
        $this->getJson('/api/admin/tiers')->assertStatus(401);
    }

    public function test_admin_can_list_tiers_with_variants_count(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $category = Category::create([
            'name'      => 'Woody',
            'slug'      => 'woody',
            'is_active' => true,
        ]);

        $tier1 = ProductTier::create([
            'grade'      => 'premium',
            'size_ml'    => 30,
            'price'      => 35000,
            'label'      => 'Premium 30ml',
            'sort_order' => 1,
        ]);

        $tier2 = ProductTier::create([
            'grade'      => 'super_premium',
            'size_ml'    => 50,
            'price'      => 75000,
            'label'      => 'Super Premium 50ml',
            'sort_order' => 2,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Oud Wood',
            'slug'        => 'oud-wood',
            'stock'       => 20,
            'is_active'   => true,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'tier_id'    => $tier1->id,
            'stock'      => 10,
            'is_active'  => true,
        ]);

        $response = $this->getJson('/api/admin/tiers');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonPath('data.0.id', $tier1->id)
                 ->assertJsonPath('data.0.variants_count', 1)
                 ->assertJsonPath('data.1.id', $tier2->id)
                 ->assertJsonPath('data.1.variants_count', 0);
    }

    public function test_admin_can_create_tier(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $payload = [
            'grade'      => 'super_premium',
            'size_ml'    => 100,
            'price'      => 135000,
            'label'      => 'Super Premium 100ml',
            'sort_order' => 5,
        ];

        $response = $this->postJson('/api/admin/tiers', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('data.label', 'Super Premium 100ml')
                 ->assertJsonPath('data.grade', 'super_premium')
                 ->assertJsonPath('data.size_ml', 100)
                 ->assertJsonPath('data.price', 135000);

        $this->assertDatabaseHas('product_tiers', [
            'label'   => 'Super Premium 100ml',
            'grade'   => 'super_premium',
            'size_ml' => 100,
            'price'   => 135000,
        ]);
    }

    public function test_admin_create_tier_validation_fails(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/tiers', [
            'grade'   => 'invalid_grade',
            'size_ml' => -5,
            'price'   => -100,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['grade', 'size_ml', 'price', 'label']);
    }

    public function test_admin_can_update_tier(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $tier = ProductTier::create([
            'grade'      => 'premium',
            'size_ml'    => 30,
            'price'      => 35000,
            'label'      => 'Premium 30ml',
            'sort_order' => 1,
        ]);

        $response = $this->putJson("/api/admin/tiers/{$tier->id}", [
            'price' => 40000,
            'label' => 'Premium 30ml Updated',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.price', 40000)
                 ->assertJsonPath('data.label', 'Premium 30ml Updated');

        $this->assertDatabaseHas('product_tiers', [
            'id'    => $tier->id,
            'price' => 40000,
            'label' => 'Premium 30ml Updated',
        ]);
    }

    public function test_admin_cannot_delete_tier_with_active_variants(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $category = Category::create([
            'name'      => 'Fresh',
            'slug'      => 'fresh',
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
            'name'        => 'Aqua Breeze',
            'slug'        => 'aqua-breeze',
            'stock'       => 15,
            'is_active'   => true,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'tier_id'    => $tier->id,
            'stock'      => 15,
            'is_active'  => true,
        ]);

        $response = $this->deleteJson("/api/admin/tiers/{$tier->id}");

        $response->assertStatus(422)
                 ->assertJson([
                     'message' => 'Tier tidak dapat dihapus karena masih digunakan oleh varian produk.',
                 ]);

        $this->assertDatabaseHas('product_tiers', ['id' => $tier->id]);
    }

    public function test_admin_can_delete_unused_tier(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $tier = ProductTier::create([
            'grade'      => 'super_premium',
            'size_ml'    => 100,
            'price'      => 150000,
            'label'      => 'Super Premium 100ml Unused',
            'sort_order' => 99,
        ]);

        $response = $this->deleteJson("/api/admin/tiers/{$tier->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Tier berhasil dihapus.',
                 ]);

        $this->assertDatabaseMissing('product_tiers', ['id' => $tier->id]);
    }
}
