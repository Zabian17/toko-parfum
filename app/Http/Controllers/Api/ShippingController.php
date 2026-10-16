<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
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

    public function destinations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        if (!$this->rajaOngkir->hasApiKey()) {
            return response()->json(['message' => 'API Key RajaOngkir belum diatur.'], 503);
        }

        try {
            $destinations = $this->rajaOngkir->searchDestinations($validated['search']);
        } catch (\Throwable $error) {
            Log::warning('RajaOngkir destination search failed.', ['error' => $error->getMessage()]);

            return response()->json([
                'message' => 'RajaOngkir gagal mencari area. Periksa API key Shipping Cost dan koneksi server.',
            ], 502);
        }

        if (empty($destinations)) {
            return response()->json(['message' => 'Area tidak ditemukan. Coba cari dengan kode pos atau nama kecamatan.'], 404);
        }

        return response()->json(['data' => $destinations]);
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
        $validated = $request->validate([
            'destination_id' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if (!$this->rajaOngkir->isEnabled()) {
            return response()->json(['message' => 'RajaOngkir is disabled.'], 503);
        }

        $origin = $this->rajaOngkir->getDefaultOriginId();
        if (!$origin) {
            return response()->json(['message' => 'Atur ID area asal toko di Pengaturan > Pengiriman.'], 422);
        }

        $variantIds = collect($validated['items'])->pluck('product_variant_id')->unique();
        $variants = \App\Models\ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id');
        $weight = collect($validated['items'])->sum(function (array $item) use ($variants) {
            $variant = $variants[$item['product_variant_id']] ?? null;
            if (!$variant || (int) $variant->product_id !== (int) $item['product_id']) {
                abort(422, 'Varian ukuran produk tidak valid.');
            }
            return (int) $variant->weight * (int) $item['quantity'];
        });

        if ($weight < 1) {
            return response()->json(['message' => 'Berat produk belum diatur. Isi berat dalam gram di menu Produk terlebih dahulu.'], 422);
        }

        $couriers = collect(explode(',', (string) Setting::get('default_couriers', 'jne,jnt,pos')))
            ->map(fn ($courier) => strtolower(trim($courier)))
            ->filter(fn ($courier) => preg_match('/^[a-z0-9]+$/', $courier))
            ->unique()
            ->implode(':');

        if ($couriers === '') {
            return response()->json(['message' => 'Atur minimal satu kurir di Pengaturan > Pengiriman.'], 422);
        }

        $results = $this->rajaOngkir->getCost(
            $origin,
            (string) $validated['destination_id'],
            (int) $weight,
            $couriers
        );

        if (empty($results)) {
            return response()->json(['message' => 'Tarif tidak tersedia. Periksa area asal, tujuan, dan kurir RajaOngkir.'], 422);
        }

        return response()->json(['data' => $results]);
    }
}
