<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RajaOngkirService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function __construct(private readonly RajaOngkirService $rajaOngkir) {}

    public function provinces(): JsonResponse
    {
        if (!$this->rajaOngkir->isEnabled()) {
            return response()->json(['message' => 'RajaOngkir is disabled.'], 503);
        }

        $provinces = $this->rajaOngkir->getProvinces();

        return response()->json(['data' => $provinces]);
    }

    public function cities(Request $request): JsonResponse
    {
        if (!$this->rajaOngkir->isEnabled()) {
            return response()->json(['message' => 'RajaOngkir is disabled.'], 503);
        }

        $cities = $this->rajaOngkir->getCities($request->query('province_id'));

        return response()->json(['data' => $cities]);
    }

    public function cost(Request $request): JsonResponse
    {
        $request->validate([
            'origin'      => ['nullable', 'string'],
            'destination' => ['required', 'string'],
            'weight'      => ['required', 'integer', 'min:1'],
            'courier'     => ['required', 'string', 'max:255'],
        ]);

        if (!$this->rajaOngkir->isEnabled()) {
            return response()->json(['message' => 'RajaOngkir is disabled.'], 503);
        }

        $origin = $request->origin ?: ($this->rajaOngkir->getDefaultOriginId() ?: '136');

        $results = $this->rajaOngkir->getCost(
            $origin,
            $request->destination,
            (int) $request->weight,
            $request->courier
        );

        if (empty($results)) {
            $defaultCost = (int) \App\Models\Setting::get('default_shipping_cost', 20000);
            if ($defaultCost <= 0) $defaultCost = 20000;
            $results = [
                [
                    'name'        => 'Kurir Reguler (Tarif Standar)',
                    'code'        => 'reguler',
                    'service'     => 'REG',
                    'description' => 'Pengiriman Standar',
                    'cost'        => $defaultCost,
                    'etd'         => '2-4 hari',
                ]
            ];
        }

        return response()->json(['data' => $results]);
    }
}
