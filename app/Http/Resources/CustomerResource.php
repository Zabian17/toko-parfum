<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\OrderResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'email'                   => $this->email,
            'phone'                   => $this->phone,
            'address'                 => $this->address,
            'city'                    => $this->city,
            'province'                => $this->province,
            'postal_code'             => $this->postal_code,
            'rajaongkir_city_id'      => $this->rajaongkir_city_id,
            'rajaongkir_province_id'  => $this->rajaongkir_province_id,
            'is_active'               => $this->is_active,
            'orders_count'            => $this->whenCounted('orders'),
            'orders'                  => OrderResource::collection($this->whenLoaded('orders')),
            'created_at'              => $this->created_at?->toDateTimeString(),
        ];
    }
}
