<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restructures product tables for the parfum biang wangi business model.
 *
 * business rules:
 *   - ~180 fragrance names (each = one product)
 *   - 2 grades: Premium / Super Premium
 *   - 2 sizes: 30ml / 50ml
 *   - Price is fixed per grade+size combination (product-tier master)
 *   - Each product can have up to 4 variants (grade × size), each with its own stock
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // 1. Truncate all transactional & product data  (safe to do since
        //    the store is being restructured from scratch)
        // ------------------------------------------------------------------
        if (DB::connection()->getDriverName() === 'pgsql') {
            foreach ([
                'stock_movements', 'order_items', 'orders', 'product_variants',
                'product_images', 'product_tag_pivot', 'products', 'categories',
            ] as $table) {
                DB::table($table)->delete();
            }
        } else {
            Schema::disableForeignKeyConstraints();
            DB::table('stock_movements')->truncate();
            DB::table('order_items')->truncate();
            DB::table('orders')->truncate();
            DB::table('product_variants')->truncate();
            DB::table('product_images')->truncate();
            DB::table('product_tag_pivot')->truncate();
            DB::table('products')->truncate();
            DB::table('categories')->truncate();
            Schema::enableForeignKeyConstraints();
        }

        // ------------------------------------------------------------------
        // 2. Create product_tiers master table
        // ------------------------------------------------------------------
        Schema::create('product_tiers', function (Blueprint $table) {
            $table->id();
            $table->enum('grade', ['premium', 'super_premium']);
            $table->unsignedSmallInteger('size_ml')->comment('e.g. 30, 50');
            $table->decimal('price', 15, 2);
            $table->string('label', 60)->comment('Human-readable e.g. "Premium 30ml"');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['grade', 'size_ml']);
        });

        // ------------------------------------------------------------------
        // 3. Drop obsolete columns from products table
        // ------------------------------------------------------------------
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn([
                'price',
                'compare_price',
                'sku',
                'weight',
                'brand',
                'volume',
                'fragrance_type',
            ]);
        });

        // ------------------------------------------------------------------
        // 4. Restructure product_variants: replace custom price/sku with
        //    a reference to the product_tiers master table
        // ------------------------------------------------------------------
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn(['name', 'sku', 'price', 'compare_price', 'sort_order']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('tier_id')
                  ->after('product_id')
                  ->constrained('product_tiers')
                  ->restrictOnDelete();

            $table->unique(['product_id', 'tier_id']);
        });
    }

    public function down(): void
    {
        // Remove tier_id unique + FK from product_variants
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'tier_id']);
            $table->dropForeign(['tier_id']);
            $table->dropColumn('tier_id');

            // Restore old columns
            $table->string('name', 100)->after('product_id')->default('');
            $table->string('sku', 100)->unique()->nullable()->after('name');
            $table->decimal('price', 15, 2)->after('sku')->default(0);
            $table->decimal('compare_price', 15, 2)->nullable()->after('price');
            $table->integer('sort_order')->default(0);
        });

        // Restore products columns
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->default(0)->after('short_description');
            $table->decimal('compare_price', 15, 2)->nullable()->after('price');
            $table->string('sku', 100)->unique()->nullable()->after('compare_price');
            $table->integer('weight')->default(0)->after('sku');
            $table->string('brand', 100)->nullable();
            $table->string('volume', 50)->nullable();
            $table->string('fragrance_type', 50)->nullable();
        });

        Schema::dropIfExists('product_tiers');
    }
};
