<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id'       => ['required', 'integer', 'exists:categories,id'],
            'name'              => ['required', 'string', 'max:200'],
            'slug'              => ['nullable', 'string', 'max:220', 'unique:products,slug'],
            'description'       => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'is_active'         => ['boolean'],
            'is_featured'       => ['boolean'],
            'thumbnail'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // Images
            'images'            => ['nullable', 'array', 'max:10'],
            'images.*'          => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // Tags
            'tags'              => ['nullable', 'array'],
            'tags.*'            => ['string', 'max:100'],

            // Variants — each entry: tier_id + stock + is_active
            'variants'               => ['nullable', 'array'],
            'variants.*.tier_id'     => ['required_with:variants', 'integer', 'exists:product_tiers,id'],
            'variants.*.stock'       => ['integer', 'min:0'],
            'variants.*.is_active'   => ['boolean'],
        ];
    }
}
