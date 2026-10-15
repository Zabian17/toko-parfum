<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'product_id'         => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'order_id'           => $this->order_id,
            'type'               => $this->type,
            'quantity'           => $this->quantity,
            'stock_before'       => $this->stock_before,
            'stock_after'        => $this->stock_after,
            'reference'          => $this->reference,
            'notes'              => $this->notes,
            'created_by'         => $this->created_by,
            'product'            => new ProductResource($this->whenLoaded('product')),
            'variant'            => new ProductVariantResource($this->whenLoaded('variant')),
            'created_at'         => $this->created_at?->toDateTimeString(),
        ];
    }
}
