<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class RajaOngkirService
{
    private string $apiKey;
    private string $baseUrl;
    private bool $cacheEnabled;
    private int $cacheTtlSeconds;

    public function __construct()
    {
        $this->apiKey  = (string) (config('services.rajaongkir.api_key') ?? '');
        $this->baseUrl = rtrim(config('services.rajaongkir.base_url'), '/');
        $this->cacheEnabled = (bool) config('services.rajaongkir.cache_enabled', false);
        $this->cacheTtlSeconds = (int) config('services.rajaongkir.cache_ttl', 3600 * 24);
    }

    private function headers(): array
    {
        return [
            'key' => $this->apiKey,
            'Accept' => 'application/json',
        ];
    }

    private function withCache(string $key, \Closure $callback): array
    {
        if (!$this->cacheEnabled) {
            return $callback();
        }

        return Cache::remember($key, $this->cacheTtlSeconds, $callback);
    }

    private function extractData(Response $response): array
    {
        if (!$response->successful()) {
            return [];
        }

        return $response->json('data', []);
    }

    public function getProvinces(): array
    {
        return $this->withCache('rajaongkir_v2_provinces', function () {
            $response = Http::withHeaders($this->headers())
                ->timeout(20)
                ->get("{$this->baseUrl}/destination/province");

            $rows = $this->extractData($response);

            return array_map(static function (array $row): array {
                return [
                    'province_id' => (string) ($row['id'] ?? ''),
                    'province' => (string) ($row['name'] ?? ''),
                ];
            }, $rows);
        });
    }

    public function getCities(?string $provinceId = null): array
    {
        if (empty($provinceId)) {
            return [];
        }

        $cacheKey = 'rajaongkir_v2_cities_' . $provinceId;

        return $this->withCache($cacheKey, function () use ($provinceId) {
            $response = Http::withHeaders($this->headers())
                ->timeout(20)
                ->get("{$this->baseUrl}/destination/city/{$provinceId}");

            $rows = $this->extractData($response);

            return array_map(static function (array $row) use ($provinceId): array {
                return [
                    'city_id' => (string) ($row['id'] ?? ''),
                    'province_id' => (string) $provinceId,
                    'city_name' => (string) ($row['name'] ?? ''),
                    'type' => '',
                    'postal_code' => (string) ($row['zip_code'] ?? ''),
                ];
            }, $rows);
        });
    }

    public function searchDestinations(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $cacheKey = 'rajaongkir_destinations_v2_' . sha1(mb_strtolower($search));

        return $this->withCache($cacheKey, function () use ($search) {
            $response = Http::withHeaders($this->headers())
                ->timeout(15)
                ->get("{$this->baseUrl}/destination/domestic-destination", [
                    'search' => $search,
                    'limit' => 100,
                    'offset' => 0,
                ]);

            if (!$response->successful()) {
                throw new \RuntimeException('RajaOngkir destination search returned HTTP ' . $response->status());
            }

            return $this->extractData($response);
        });
    }

    public function getCost(
        string $originCityId,
        string $destinationCityId,
        int    $weight,
        string $courier
    ): array {
        try {
            $response = Http::withHeaders($this->headers())
                ->asForm()
                ->timeout(15)
                ->post("{$this->baseUrl}/calculate/domestic-cost", [
                    'origin'      => $originCityId,
                    'destination' => $destinationCityId,
                    'weight'      => $weight,
                    'courier'     => strtolower($courier),
                    'price'       => 'lowest',
                ]);

            return $this->extractData($response);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('RajaOngkir getCost error: ' . $e->getMessage());
            return [];
        }
    }

    public function isEnabled(): bool
    {
        return $this->hasApiKey()
            && Setting::get('rajaongkir_enabled', true);
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }

    public function getDefaultOriginId(): ?string
    {
        $origin = Setting::get('rajaongkir_origin');

        if (empty($origin)) {
            $origin = config('services.rajaongkir.default_origin');
        }

        return empty($origin) ? null : (string) $origin;
    }
}
