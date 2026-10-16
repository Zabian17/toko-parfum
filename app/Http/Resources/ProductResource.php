<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $thumbnail = $this->thumbnail;

        if (!$thumbnail) {
            $fallbackNames = [
                'chanel-no5-ori' => 'chanel_no5',
                'dior-sauvage-ori' => 'dior_sauvage',
                'luxury-gift-set-3in1' => 'gift_set',
            ];
            $imageName = $fallbackNames[$this->slug] ?? str_replace('-', '_', $this->slug);
            $fallback = 'images/products/' . $imageName . '.png';

            if (file_exists(public_path($fallback))) {
                $thumbnail = $fallback;
            }
        }

        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'description'       => $this->description,
            'short_description' => $this->short_description,
            'stock'             => $this->stock,
            'weight'            => (int) $this->weight,
            'is_active'         => $this->is_active,
            'is_featured'       => $this->is_featured,
            'thumbnail'         => $thumbnail
                ? (str_starts_with($thumbnail, 'images/') || str_starts_with($thumbnail, 'http')
                    ? '/' . $thumbnail
                    : '/storage/' . $thumbnail)
                : null,
            'sold_count'        => $this->sold_count,
            // Minimum price across active variants (for display in catalog)
            'min_price'         => $this->whenLoaded('variants', function () {
                $prices = $this->variants
                    ->where('is_active', true)
                    ->map(fn($v) => $v->tier?->price)
                    ->filter()
                    ->values();
                return $prices->min() ? (float) $prices->min() : null;
            }),
            'category'          => new CategoryResource($this->whenLoaded('category')),
            'images'            => ProductImageResource::collection($this->whenLoaded('images')),
            'variants'          => ProductVariantResource::collection($this->whenLoaded('variants')),
            'tags'              => ProductTagResource::collection($this->whenLoaded('tags')),
            'created_at'        => $this->created_at?->toDateTimeString(),
            'updated_at'        => $this->updated_at?->toDateTimeString(),
        ];
    }
}
