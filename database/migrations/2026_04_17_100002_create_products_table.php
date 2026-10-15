<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('slug', 220)->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('compare_price', 15, 2)->nullable()->comment('Original/crossed price');
            $table->string('sku', 100)->unique()->nullable();
            $table->integer('stock')->default(0);
            $table->integer('weight')->default(0)->comment('in grams');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('thumbnail')->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('volume', 50)->nullable()->comment('e.g. 30ml, 50ml');
            $table->string('fragrance_type', 50)->nullable()->comment('EDP, EDT, Parfum, etc.');
            $table->bigInteger('sold_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('is_active');
            $table->index('is_featured');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
