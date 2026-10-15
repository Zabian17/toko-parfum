<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RajaOngkirService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function __construct(private readonly RajaOngkirService $rajaOngkir) {}

    public function provinces(): JsonResponse
    {
        $rows = $this->regionRows('provinces', 'shipping_regions_provinces_v1');
        if ($rows === null) {
            return response()->json(['message' => 'Daftar provinsi gagal dimuat.'], 502);
        }

        $provinces = array_map(static fn (array $row): array => [
            'province_id' => (string) ($row['code'] ?? ''),
            'province' => (string) ($row['name'] ?? ''),
        ], $rows);

        return response()->json(['data' => $provinces]);
    }

    public function cities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'province_id' => ['required', 'string', 'regex:/^\\d{2}$/'],
        ]);
        $provinceId = $validated['province_id'];
        $rows = $this->regionRows("regencies/{$provinceId}", "shipping_regions_regencies_v1_{$provinceId}");
        if ($rows === null) {
            return response()->json(['message' => 'Daftar kota/kabupaten gagal dimuat.'], 502);
        }

        $cities = array_map(static fn (array $row): array => [
            'city_id' => (string) ($row['code'] ?? ''),
            'province_id' => $provinceId,
            'city_name' => trim((string) ($row['name'] ?? '')),
            'type' => '',
            'postal_code' => '',
        ], $rows);

        return response()->json(['data' => $cities]);
    }

    private function regionRows(string $path, string $cacheKey): ?array
    {
        try {
            return Cache::remember($cacheKey, now()->addDay(), function () use ($path): ?array {
                $response = Http::acceptJson()->timeout(10)->get("https://wilayah.id/api/{$path}.json");
                $rows = $response->json('data');

                return $response->successful() && is_array($rows) ? $rows : null;
            });
        } catch (\Throwable $e) {
            Log::warning('Gagal memuat data wilayah Indonesia.', ['error' => $e->getMessage()]);

            return null;
        }
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
