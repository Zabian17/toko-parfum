<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'product_id'        => $this->product_id,
            'product_variant_id'=> $this->product_variant_id,
            'product_name'      => $this->product_name,
            'variant_name'      => $this->variant_name,
            'product_sku'       => $this->product_sku,
            'product_thumbnail' => $this->product_thumbnail 
                ? (str_starts_with($this->product_thumbnail, 'images/') || str_starts_with($this->product_thumbnail, 'http')
                    ? '/' . $this->product_thumbnail 
                    : '/storage/' . $this->product_thumbnail) 
                : null,
            'quantity'          => $this->quantity,
            'unit_price'        => (float) $this->unit_price,
            'subtotal'          => (float) $this->subtotal,
            'weight'            => $this->weight,
        ];
    }
}
