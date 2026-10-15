<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTier;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        /* ──────────────────────────────────────────────
         |  Admin user
         ────────────────────────────────────────────── */
        $role = Role::where('slug', 'super_admin')->first();
        $admin = User::updateOrCreate(
            ['email' => 'admin@albiparfum.com'],
            [
                'name'     => 'Admin Albi',
                'email'    => 'admin@albiparfum.com',
                'password' => Hash::make('admin123'),
                'role_id'  => $role?->id,
            ]
        );

        \Illuminate\Support\Facades\DB::table('personal_access_tokens')->updateOrInsert(
            ['id' => 1],
            [
                'tokenable_type' => User::class,
                'tokenable_id'   => $admin->id,
                'name'           => 'admin-panel',
                'token'          => hash('sha256', 'AlbiParfumMasterKey2026'),
                'abilities'      => '["*"]',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]
        );

        /* ──────────────────────────────────────────────
         |  Categories
         ────────────────────────────────────────────── */
        $categories = [
            ['name' => 'Parfum Pria',    'slug' => 'parfum-pria',    'sort_order' => 1],
            ['name' => 'Parfum Wanita',  'slug' => 'parfum-wanita',  'sort_order' => 2],
            ['name' => 'Parfum Unisex',  'slug' => 'parfum-unisex',  'sort_order' => 3],
            ['name' => 'Parfum Anak',    'slug' => 'parfum-anak',    'sort_order' => 4],
            ['name' => 'Gift Set',       'slug' => 'gift-set',       'sort_order' => 5],
            ['name' => 'Parfum Import',  'slug' => 'parfum-import',  'sort_order' => 6],
        ];

        $createdCategories = [];
        foreach ($categories as $cat) {
            $createdCategories[$cat['slug']] = Category::updateOrCreate(
                ['slug' => $cat['slug']],
                array_merge($cat, ['is_active' => true])
            );
        }

        /* ──────────────────────────────────────────────
         |  Products
         ────────────────────────────────────────────── */
        $tiers = ProductTier::orderBy('sort_order')->get();

        $products = [
            [
                'name'           => 'Midnight Oud',
                'slug'           => 'midnight-oud',
                'category_slug'  => 'parfum-pria',
                'description'    => 'Aroma oud yang dalam dan maskulin, cocok untuk malam hari. Dibuat dengan bahan-bahan pilihan dari Timur Tengah.',
                'is_featured'    => true,
                'stock'          => 47,
                'thumbnail'      => 'images/products/midnight_oud.png',
                'variant_stocks' => [12, 15, 10, 10],
            ],
            [
                'name'           => 'Rose de Paris',
                'slug'           => 'rose-de-paris',
                'category_slug'  => 'parfum-wanita',
                'description'    => 'Keharuman mawar Prancis yang elegan dan feminin. Parfum dengan ketahanan seharian yang sempurna.',
                'is_featured'    => true,
                'stock'          => 63,
                'thumbnail'      => 'images/products/rose_de_paris.png',
                'variant_stocks' => [18, 15, 18, 12],
            ],
            [
                'name'           => 'Ocean Breeze',
                'slug'           => 'ocean-breeze',
                'category_slug'  => 'parfum-unisex',
                'description'    => 'Aroma segar laut yang menyegarkan, cocok untuk pria dan wanita. Sempurna untuk aktivitas sehari-hari.',
                'is_featured'    => false,
                'stock'          => 88,
                'thumbnail'      => 'images/products/ocean_breeze.png',
                'variant_stocks' => [25, 22, 22, 19],
            ],
            [
                'name'           => 'Vanilla Dream',
                'slug'           => 'vanilla-dream',
                'category_slug'  => 'parfum-wanita',
                'description'    => 'Hangatnya aroma vanilla yang manis dan lembut. Parfum dengan kesan mewah yang terjangkau.',
                'is_featured'    => true,
                'stock'          => 52,
                'thumbnail'      => 'images/products/vanilla_dream.png',
                'variant_stocks' => [15, 13, 13, 11],
            ],
            [
                'name'           => 'Black Knight',
                'slug'           => 'black-knight',
                'category_slug'  => 'parfum-pria',
                'description'    => 'Aroma maskulin yang berani dan penuh percaya diri. Memadukan notes kayu cedar dengan sentuhan spicy.',
                'is_featured'    => false,
                'stock'          => 3,
                'thumbnail'      => 'images/products/black_knight.png',
                'variant_stocks' => [1, 1, 1, 0],
            ],
            [
                'name'           => 'Citrus Burst',
                'slug'           => 'citrus-burst',
                'category_slug'  => 'parfum-unisex',
                'description'    => 'Energi citrus yang segar dan bersemangat. Cocok untuk pagi hari dan aktivitas outdoor.',
                'is_featured'    => false,
                'stock'          => 115,
                'thumbnail'      => 'images/products/citrus_burst.png',
                'variant_stocks' => [30, 30, 28, 27],
            ],
            [
                'name'           => 'Princess Pink',
                'slug'           => 'princess-pink',
                'category_slug'  => 'parfum-anak',
                'description'    => 'Parfum lembut dan ringan khusus anak-anak. Formula bebas alkohol yang aman dan menyenangkan.',
                'is_featured'    => false,
                'stock'          => 70,
                'thumbnail'      => 'images/products/princess_pink.png',
                'variant_stocks' => [20, 18, 17, 15],
            ],
            [
                'name'           => 'Luxury Gift Set (3 in 1)',
                'slug'           => 'luxury-gift-set-3in1',
                'category_slug'  => 'gift-set',
                'description'    => 'Paket hadiah mewah berisi 3 mini parfum: Midnight Oud, Rose de Paris, dan Ocean Breeze. Dikemas dalam kotak premium.',
                'is_featured'    => true,
                'stock'          => 25,
                'thumbnail'      => 'images/products/gift_set.png',
                'variant_stocks' => [7, 6, 7, 5],
            ],
            [
                'name'           => 'Chanel No.5 Ori',
                'slug'           => 'chanel-no5-ori',
                'category_slug'  => 'parfum-import',
                'description'    => 'Parfum legendaris Chanel No.5 original. Aroma floral aldehyde yang ikonik dan timeless.',
                'is_featured'    => true,
                'stock'          => 8,
                'thumbnail'      => 'images/products/chanel_no5.png',
                'variant_stocks' => [2, 2, 2, 2],
            ],
            [
                'name'           => 'Dior Sauvage Ori',
                'slug'           => 'dior-sauvage-ori',
                'category_slug'  => 'parfum-import',
                'description'    => 'Dior Sauvage original — aroma fresh spicy yang maskulin dan modern. Salah satu parfum terlaris di dunia.',
                'is_featured'    => false,
                'stock'          => 0,
                'thumbnail'      => 'images/products/dior_sauvage.png',
                'variant_stocks' => [0, 0, 0, 0],
            ],
        ];

        foreach ($products as $productData) {
            $variantStocks = $productData['variant_stocks'] ?? [];
            $categorySlug  = $productData['category_slug'];
            unset($productData['variant_stocks'], $productData['category_slug']);

            $productData['category_id'] = $createdCategories[$categorySlug]->id ?? null;
            $productData['is_active']   = true;

            $product = Product::updateOrCreate(
                ['slug' => $productData['slug']],
                $productData
            );

            foreach ($tiers as $i => $tier) {
                ProductVariant::updateOrCreate(
                    ['product_id' => $product->id, 'tier_id' => $tier->id],
                    [
                        'product_id' => $product->id,
                        'tier_id'    => $tier->id,
                        'stock'      => $variantStocks[$i] ?? 0,
                        'is_active'  => true,
                    ]
                );
            }
        }
    }
}
