<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // --- General ---
            [
                'key'   => 'store_name',
                'value' => 'Toko Albi Parfum',
                'group' => 'general',
                'label' => 'Nama Toko',
                'type'  => 'text',
            ],
            [
                'key'   => 'store_phone',
                'value' => '',
                'group' => 'general',
                'label' => 'Nomor Telepon Toko',
                'type'  => 'text',
            ],
            [
                'key'   => 'store_address',
                'value' => '',
                'group' => 'general',
                'label' => 'Alamat Toko',
                'type'  => 'text',
            ],
            [
                'key'   => 'store_city_id',
                'value' => '136',
                'group' => 'general',
                'label' => 'ID Kota Asal (RajaOngkir)',
                'type'  => 'text',
            ],
            [
                'key'   => 'rajaongkir_origin',
                'value' => '136',
                'group' => 'shipping',
                'label' => 'Kota Asal Pengiriman (RajaOngkir)',
                'type'  => 'text',
            ],
            // --- Shipping ---
            [
                'key'   => 'rajaongkir_enabled',
                'value' => '1',
                'group' => 'shipping',
                'label' => 'Aktifkan RajaOngkir',
                'type'  => 'boolean',
            ],
            [
                'key'   => 'default_shipping_cost',
                'value' => '20000',
                'group' => 'shipping',
                'label' => 'Tarif Pengiriman Tetap',
                'type'  => 'integer',
            ],
            [
                'key'   => 'default_couriers',
                'value' => 'jne,jnt,pos',
                'group' => 'shipping',
                'label' => 'Kurir Default (pisahkan koma)',
                'type'  => 'text',
            ],
            // --- Payment ---
            [
                'key'   => 'midtrans_enabled',
                'value' => '1',
                'group' => 'payment',
                'label' => 'Aktifkan Midtrans',
                'type'  => 'boolean',
            ],
            [
                'key'   => 'cod_enabled',
                'value' => '1',
                'group' => 'payment',
                'label' => 'Aktifkan COD',
                'type'  => 'boolean',
            ],
            [
                'key'   => 'bank_transfer_info',
                'value' => '',
                'group' => 'payment',
                'label' => 'Informasi Rekening Bank',
                'type'  => 'text',
            ],
            // --- Notification ---
            [
                'key'   => 'email_notification_enabled',
                'value' => '1',
                'group' => 'notification',
                'label' => 'Aktifkan Email Notifikasi',
                'type'  => 'boolean',
            ],
            [
                'key'   => 'low_stock_threshold',
                'value' => '5',
                'group' => 'notification',
                'label' => 'Batas Stok Rendah',
                'type'  => 'integer',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
