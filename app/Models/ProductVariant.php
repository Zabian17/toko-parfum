<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'tier_id',
        'stock',
        'is_active',
    ];

    protected $casts = [
        'stock'     => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(ProductTier::class);
    }

    /**
     * Convenience accessor — price comes from the tier master.
     */
    public function getPriceAttribute(): float
    {
        return $this->tier ? (float) $this->tier->price : 0.0;
    }

    /**
     * Convenience accessor — label from tier.
     */
    public function getNameAttribute(): string
    {
        return $this->tier ? $this->tier->label : '';
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
