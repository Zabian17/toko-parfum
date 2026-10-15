<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Standard description used for all parfum biang wangi products.
     */
    private const DEFAULT_DESCRIPTION = 'Aroma parfum yang dibuat dari aneka ragam bahan terpilih sehingga tercipta dengan khas wangi Bunga, Kayu, Buah dan Rempah, sehingga melahirkan sebuah aroma yang menyegarkan, sekaligus terapi bagi yang menggunakan.';

    /**
     * Stock per variant for seeded products (demo data).
     */
    private const DEFAULT_STOCK = 0;

    public function run(): void
    {
        $jsonPath = base_path('dummy-data.json');
        if (!file_exists($jsonPath)) {
            $this->command->warn('dummy-data.json not found, skipping ProductSeeder.');
            return;
        }

        $items = json_decode(file_get_contents($jsonPath), true);
        if (!$items) {
            $this->command->warn('dummy-data.json is empty or invalid JSON.');
            return;
        }

        // Load all tiers once
        $tiers = ProductTier::orderBy('sort_order')->get();
        if ($tiers->isEmpty()) {
            $this->command->error('No product tiers found. Run ProductTierSeeder first.');
            return;
        }

        $catCache = [];

        foreach ($items as $item) {
            $catName = $item['category'] ?? 'Parfum Unisex';

            // Resolve category (cache to avoid N+1 queries)
            if (!isset($catCache[$catName])) {
                $catCache[$catName] = Category::where('name', $catName)->first()?->id;
            }
            $categoryId = $catCache[$catName];
            if (!$categoryId) {
                $this->command->warn("Category '{$catName}' not found, skipping: {$item['name']}");
                continue;
            }

            // Generate a unique slug
            $baseSlug = Str::slug($item['name']);
            $slug     = $baseSlug;
            $counter  = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }

            $product = Product::create([
                'category_id'       => $categoryId,
                'name'              => $item['name'],
                'slug'              => $slug,
                'description'       => $item['description'] ?? self::DEFAULT_DESCRIPTION,
                'short_description' => null,
                'stock'             => 0,  // will be updated after variants
                'is_active'         => true,
                'is_featured'       => false,
                'sold_count'        => 0,
            ]);

            // Create all 4 tier variants with default stock
            foreach ($tiers as $tier) {
                $product->variants()->create([
                    'tier_id'   => $tier->id,
                    'stock'     => self::DEFAULT_STOCK,
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Seeded ' . count($items) . ' products with ' . $tiers->count() . ' variants each.');
    }
}
