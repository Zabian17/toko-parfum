<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShippingCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_provinces(): void
    {
        Http::fake([
            '*/destination/province' => Http::response([
                'meta' => ['code' => 200, 'status' => 'success'],
                'data' => [
                    ['id' => 10, 'name' => 'DKI JAKARTA'],
                    ['id' => 11, 'name' => 'JAWA BARAT'],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/shipping/provinces');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.province_id', '10')
                 ->assertJsonPath('data.0.province', 'DKI JAKARTA');
    }

    public function test_can_fetch_cities_by_province(): void
    {
        Http::fake([
            '*/destination/city/10' => Http::response([
                'meta' => ['code' => 200, 'status' => 'success'],
                'data' => [
                    ['id' => 136, 'name' => 'JAKARTA SELATAN', 'zip_code' => '12110'],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/shipping/cities?province_id=10');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.city_id', '136')
                 ->assertJsonPath('data.0.city_name', 'JAKARTA SELATAN');
    }

    public function test_can_calculate_shipping_cost_with_default_origin(): void
    {
        Setting::set('store_city_id', '136');
        Setting::set('rajaongkir_origin', '136');

        Http::fake([
            '*/calculate/district/domestic-cost' => Http::response([
                'meta' => ['code' => 200, 'status' => 'success'],
                'data' => [
                    [
                        'name'    => 'Jalur Nugraha Ekakurir (JNE)',
                        'code'    => 'jne',
                        'service' => 'REG',
                        'cost'    => 15000,
                        'etd'     => '1-2 day',
                    ],
                ],
            ], 200),
        ]);

        $payload = [
            'destination' => '135',
            'weight'      => 1000,
            'courier'     => 'jne',
        ];

        $response = $this->postJson('/api/shipping/cost', $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.code', 'jne')
                 ->assertJsonPath('data.0.cost', 15000);
    }

    public function test_shipping_cost_fallback_when_external_api_fails(): void
    {
        Setting::set('default_shipping_cost', 20000);

        Http::fake([
            '*/calculate/district/domestic-cost' => Http::response(null, 500),
        ]);

        $payload = [
            'destination' => '999',
            'weight'      => 1000,
            'courier'     => 'jne',
        ];

        $response = $this->postJson('/api/shipping/cost', $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.code', 'reguler')
                 ->assertJsonPath('data.0.cost', 20000);
    }
}
