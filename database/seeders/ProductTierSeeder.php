<?php

namespace Database\Seeders;

use App\Models\ProductTier;
use Illuminate\Database\Seeder;

class ProductTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['grade' => 'premium',       'size_ml' => 30, 'price' => 35000, 'label' => 'Premium 30ml',       'sort_order' => 1],
            ['grade' => 'premium',       'size_ml' => 50, 'price' => 50000, 'label' => 'Premium 50ml',       'sort_order' => 2],
            ['grade' => 'super_premium', 'size_ml' => 30, 'price' => 55000, 'label' => 'Super Premium 30ml', 'sort_order' => 3],
            ['grade' => 'super_premium', 'size_ml' => 50, 'price' => 85000, 'label' => 'Super Premium 50ml', 'sort_order' => 4],
        ];

        foreach ($tiers as $tier) {
            ProductTier::firstOrCreate(
                ['grade' => $tier['grade'], 'size_ml' => $tier['size_ml']],
                $tier
            );
        }
    }
}
