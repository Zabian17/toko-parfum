<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isOffline = $this->is('api/admin/cashier/*')
            || $this->is('api/admin/orders/offline');

        return [
            // Customer info
            'customer_name'            => ['required', 'string', 'max:150'],
            'customer_email'           => ['nullable', 'email', 'max:200'],
            'customer_phone'           => ['required', 'string', 'max:20'],

            // Shipping
            'shipping_address'         => [$isOffline ? 'nullable' : 'required', 'string'],
            'shipping_city'            => [$isOffline ? 'nullable' : 'required', 'string', 'max:100'],
            'shipping_province'        => [$isOffline ? 'nullable' : 'required', 'string', 'max:100'],
            'shipping_postal_code'     => [$isOffline ? 'nullable' : 'required', 'string', 'max:10'],
            'rajaongkir_city_id'       => ['nullable', 'string', 'max:10'],
            'rajaongkir_province_id'   => ['nullable', 'string', 'max:10'],
            'shipping_courier'         => ['nullable', 'string', 'max:50'],
            'shipping_service'         => ['nullable', 'string', 'max:50'],
            'shipping_cost'            => ['nullable', 'numeric', 'min:0'],

            // Payment
            'payment_method'           => ['required', $isOffline ? 'in:transfer,midtrans,cod,cash,qris' : 'in:transfer,cod'],
            'notes'                    => ['nullable', 'string', 'max:500'],

            // Cart items
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.product_id'       => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'         => ['required', 'integer', 'min:1'],
        ];
    }
}
