<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'shipping_city',
        'shipping_province',
        'shipping_postal_code',
        'rajaongkir_city_id',
        'rajaongkir_province_id',
        'shipping_courier',
        'shipping_service',
        'shipping_cost',
        'tracking_number',
        'shipping_weight',
        'subtotal',
        'discount_amount',
        'total_amount',
        'payment_method',
        'payment_status',
        'payment_proof',
        'midtrans_token',
        'midtrans_transaction_id',
        'paid_at',
        'status',
        'notes',
        'admin_notes',
        'order_type',
    ];

    protected $casts = [
        'shipping_cost'  => 'decimal:2',
        'subtotal'       => 'decimal:2',
        'discount_amount'=> 'decimal:2',
        'total_amount'   => 'decimal:2',
        'shipping_weight'=> 'integer',
        'paid_at'        => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // Scopes
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus($query, string $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    // Generate unique order number: ORD-YYYYMMDD-XXXXX
    public static function generateOrderNumber(): string
    {
        $date   = now()->format('Ymd');
        $last   = static::whereDate('created_at', today())->count() + 1;
        return 'ORD-' . $date . '-' . str_pad($last, 5, '0', STR_PAD_LEFT);
    }
}
