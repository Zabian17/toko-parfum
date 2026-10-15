<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'grade'       => $this->grade,
            'grade_label' => $this->grade === 'premium' ? 'Premium' : 'Super Premium',
            'size_ml'     => $this->size_ml,
            'price'       => (float) $this->price,
            'label'       => $this->label,
            'sort_order'  => $this->sort_order,
            'variants_count' => (int) ($this->variants_count ?? 0),
        ];
    }
}
