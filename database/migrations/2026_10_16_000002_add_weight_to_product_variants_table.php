<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_variants') || Schema::hasColumn('product_variants', 'weight')) {
            return;
        }

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('weight')->default(0)->after('tier_id')->comment('Shipping weight in grams, including bottle and packaging');
        });

        DB::table('product_variants')
            ->join('product_tiers', 'product_tiers.id', '=', 'product_variants.tier_id')
            ->where('product_variants.weight', 0)
            ->update([
                'product_variants.weight' => DB::raw("CASE
                    WHEN product_tiers.size_ml <= 30 AND product_tiers.grade = 'premium' THEN 120
                    WHEN product_tiers.size_ml <= 30 THEN 135
                    WHEN product_tiers.grade = 'premium' THEN 175
                    ELSE 195
                END"),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('product_variants') && Schema::hasColumn('product_variants', 'weight')) {
            Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('weight'));
        }
    }
};
