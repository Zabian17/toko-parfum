<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('customer_id')
                  ->nullable()
                  ->constrained('customers')
                  ->nullOnDelete();

            // Customer snapshot (for guest checkout)
            $table->string('customer_name', 150);
            $table->string('customer_email', 200)->nullable();
            $table->string('customer_phone', 20)->nullable();

            // Shipping address
            $table->text('shipping_address');
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_province', 100)->nullable();
            $table->string('shipping_postal_code', 10)->nullable();
            $table->string('rajaongkir_city_id', 10)->nullable();
            $table->string('rajaongkir_province_id', 10)->nullable();

            // Shipping
            $table->string('shipping_courier', 50)->nullable();
            $table->string('shipping_service', 50)->nullable();
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->string('tracking_number', 100)->nullable();
            $table->integer('shipping_weight')->default(0)->comment('total weight in grams');

            // Financials
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            // Payment
            $table->enum('payment_method', ['transfer', 'midtrans', 'cod', 'cash'])->default('transfer');
            $table->enum('payment_status', ['unpaid', 'pending', 'paid', 'failed', 'refunded'])->default('unpaid');
            $table->string('payment_proof')->nullable();
            $table->string('midtrans_token')->nullable();
            $table->string('midtrans_transaction_id')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Order status
            $table->enum('status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();

            // Type: online vs offline (cashier)
            $table->enum('order_type', ['online', 'offline'])->default('online');

            $table->timestamps();

            $table->index('order_number');
            $table->index('status');
            $table->index('payment_status');
            $table->index('customer_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
