<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

class MidtransService
{
    private ?string $serverKey;
    private ?string $clientKey;
    private bool    $isProduction;
    private string  $snapUrl;

    public function __construct()
    {
        $this->serverKey    = config('services.midtrans.server_key') ?: null;
        $this->clientKey    = config('services.midtrans.client_key') ?: null;
        $this->isProduction = (bool) config('services.midtrans.is_production', false);
        $this->snapUrl      = $this->isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    public function isEnabled(): bool
    {
        return !empty($this->serverKey)
            && \App\Models\Setting::get('midtrans_enabled', true);
    }

    public function getClientKey(): string
    {
        return $this->clientKey ?? '';
    }

    public function isProduction(): bool
    {
        return $this->isProduction;
    }

    /**
     * Create a Snap payment token for the given order.
     */
    public function createSnapToken(Order $order): array
    {
        $order->load('items');

        $itemDetails = $order->items->map(fn($item) => [
            'id'       => (string) $item->product_id,
            'price'    => (int) $item->unit_price,
            'quantity' => $item->quantity,
            'name'     => mb_substr($item->product_name . ($item->variant_name ? " ({$item->variant_name})" : ''), 0, 50),
        ])->toArray();

        // Add shipping as an item
        if ($order->shipping_cost > 0) {
            $itemDetails[] = [
                'id'       => 'SHIPPING',
                'price'    => (int) $order->shipping_cost,
                'quantity' => 1,
                'name'     => 'Shipping Cost',
            ];
        }

        $payload = [
            'transaction_details' => [
                'order_id'     => $order->order_number,
                'gross_amount' => (int) $order->total_amount,
            ],
            'item_details'  => $itemDetails,
            'customer_details' => [
                'first_name' => $order->customer_name,
                'email'      => $order->customer_email,
                'phone'      => $order->customer_phone,
                'shipping_address' => [
                    'first_name'   => $order->customer_name,
                    'phone'        => $order->customer_phone,
                    'address'      => $order->shipping_address,
                    'city'         => $order->shipping_city,
                    'postal_code'  => $order->shipping_postal_code,
                    'country_code' => 'IDN',
                ],
            ],
            'callbacks' => [
                'finish' => url('/'),
            ],
        ];

        $response = Http::withBasicAuth($this->serverKey, '')
            ->post($this->snapUrl, $payload);

        if ($response->successful()) {
            $data = $response->json();

            // Store token on order
            $order->update([
                'midtrans_token'  => $data['token'],
                'payment_status'  => 'pending',
            ]);

            return [
                'token'        => $data['token'],
                'redirect_url' => $data['redirect_url'],
                'client_key'   => $this->clientKey,
                'is_production'=> $this->isProduction,
            ];
        }

        return [
            'error'   => true,
            'message' => $response->json('error_messages', ['Payment gateway error'])[0] ?? 'Payment gateway error',
        ];
    }

    /**
     * Handle Midtrans webhook notification.
     */
    public function handleNotification(array $payload): Order|null
    {
        $orderId           = $payload['order_id'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus       = $payload['fraud_status'] ?? null;

        if (!$orderId) {
            return null;
        }

        $order = Order::where('order_number', $orderId)->first();

        if (!$order) {
            return null;
        }

        // Verify signature
        $signatureKey = hash('sha512',
            $payload['order_id'] .
            $payload['status_code'] .
            $payload['gross_amount'] .
            $this->serverKey
        );

        if ($signatureKey !== ($payload['signature_key'] ?? '')) {
            return null; // Invalid signature
        }

        $order->update(['midtrans_transaction_id' => $payload['transaction_id'] ?? null]);

        if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {
            if ($fraudStatus === 'accept' || $fraudStatus === null) {
                $order->update([
                    'payment_status' => 'paid',
                    'status'         => 'confirmed',
                    'paid_at'        => now(),
                ]);
            }
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $order->update(['payment_status' => 'failed']);
        } elseif ($transactionStatus === 'pending') {
            $order->update(['payment_status' => 'pending']);
        }

        return $order;
    }
}
