<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductTier extends Model
{
    protected $fillable = ['grade', 'size_ml', 'price', 'label', 'sort_order'];

    protected $casts = [
        'price'      => 'decimal:2',
        'size_ml'    => 'integer',
        'sort_order' => 'integer',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'tier_id');
    }

    /**
     * Human-readable grade label.
     */
    public function getGradeLabelAttribute(): string
    {
        return $this->grade === 'premium' ? 'Premium' : 'Super Premium';
    }
}
