<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTier;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_products_and_tiers(): void
    {
        $category = Category::create([
            'name'      => 'Floral',
            'slug'      => 'floral',
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
            'name'        => 'Rose Bloom',
            'slug'        => 'rose-bloom',
            'description' => 'A sweet floral scent',
            'stock'       => 10,
            'is_active'   => true,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'tier_id'    => $tier->id,
            'stock'      => 10,
            'is_active'  => true,
        ]);

        // Test list
        $response = $this->getJson('/api/products');
        $response->assertStatus(200)
                 ->assertJsonPath('data.0.name', 'Rose Bloom');

        // Test show by slug
        $response = $this->getJson('/api/products/slug/rose-bloom');
        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'Rose Bloom')
                 ->assertJsonPath('data.variants.0.tier.label', 'Premium 30ml');

        // Test tiers list
        $response = $this->getJson('/api/product-tiers');
        $response->assertStatus(200)
                 ->assertJsonPath('data.0.label', 'Premium 30ml');
    }
}
