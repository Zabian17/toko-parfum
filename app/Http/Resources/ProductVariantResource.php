<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// ProductTierResource is in the same namespace

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'tier_id'   => $this->tier_id,
            'tier'      => new ProductTierResource($this->whenLoaded('tier')),
            // Convenience accessors (from tier)
            'name'      => $this->tier?->label ?? '',
            'grade'     => $this->tier?->grade,
            'size_ml'   => $this->tier?->size_ml,
            'weight'    => (int) $this->weight,
            'price'     => $this->tier ? (float) $this->tier->price : 0.0,
            'stock'     => $this->stock,
            'is_active' => $this->is_active,
        ];
    }
}
