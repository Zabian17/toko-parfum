<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'category_id'       => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'name'              => ['sometimes', 'required', 'string', 'max:200'],
            'slug'              => ['nullable', 'string', 'max:220', Rule::unique('products', 'slug')->ignore($productId)],
            'description'       => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'is_active'         => ['boolean'],
            'is_featured'       => ['boolean'],
            'thumbnail'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'images'            => ['nullable', 'array', 'max:10'],
            'images.*'          => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'tags'              => ['nullable', 'array'],
            'tags.*'            => ['string', 'max:100'],

            'variants'               => ['nullable', 'array'],
            'variants.*.tier_id'     => ['required_with:variants', 'integer', 'exists:product_tiers,id'],
            'variants.*.stock'       => ['integer', 'min:0'],
            'variants.*.is_active'   => ['boolean'],
        ];
    }
}
