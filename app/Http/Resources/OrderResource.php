<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'order_number'             => $this->order_number,
            'order_type'               => $this->order_type,
            'customer_id'              => $this->customer_id,
            'customer_name'            => $this->customer_name,
            'customer_email'           => $this->customer_email,
            'customer_phone'           => $this->customer_phone,
            'shipping_address'         => $this->shipping_address,
            'shipping_city'            => $this->shipping_city,
            'shipping_province'        => $this->shipping_province,
            'shipping_postal_code'     => $this->shipping_postal_code,
            'rajaongkir_city_id'       => $this->rajaongkir_city_id,
            'rajaongkir_province_id'   => $this->rajaongkir_province_id,
            'shipping_courier'         => $this->shipping_courier,
            'shipping_service'         => $this->shipping_service,
            'shipping_cost'            => (float) $this->shipping_cost,
            'tracking_number'          => $this->tracking_number,
            'shipping_weight'          => $this->shipping_weight,
            'subtotal'                 => (float) $this->subtotal,
            'discount_amount'          => (float) $this->discount_amount,
            'total_amount'             => (float) $this->total_amount,
            'payment_method'           => $this->payment_method,
            'payment_status'           => $this->payment_status,
            'payment_proof'            => $this->payment_proof ? '/storage/' . $this->payment_proof : null,
            'midtrans_token'           => $this->midtrans_token,
            'paid_at'                  => $this->paid_at?->toDateTimeString(),
            'status'                   => $this->status,
            'notes'                    => $this->notes,
            'admin_notes'              => str_starts_with((string) $this->admin_notes, '[preview-session:')
                ? null
                : $this->admin_notes,
            'items'                    => OrderItemResource::collection($this->whenLoaded('items')),
            'customer'                 => new CustomerResource($this->whenLoaded('customer')),
            'created_at'               => $this->created_at?->toDateTimeString(),
            'updated_at'               => $this->updated_at?->toDateTimeString(),
        ];
    }
}
