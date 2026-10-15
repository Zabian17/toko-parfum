<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Parfum Pria',   'description' => 'Koleksi aroma maskulin dan segar untuk pria.'],
            ['name' => 'Parfum Wanita', 'description' => 'Koleksi aroma feminin dan elegan untuk wanita.'],
            ['name' => 'Parfum Unisex', 'description' => 'Koleksi aroma netral yang cocok untuk semua kalangan.'],
            ['name' => 'Parfum Anak',   'description' => 'Aroma lembut dan aman untuk anak-anak.'],
            ['name' => 'Gift Set',      'description' => 'Paket parfum spesial untuk hadiah.'],
            ['name' => 'Parfum Import', 'description' => 'Koleksi parfum dengan inspirasi aroma internasional.'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => Str::slug($cat['name'])],
                [
                    'name'        => $cat['name'],
                    'slug'        => Str::slug($cat['name']),
                    'description' => $cat['description'],
                    'is_active'   => true,
                ]
            );
        }
    }
}
