<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly MidtransService $midtrans) {}

    /**
     * Create a Snap token for a given order (public, triggered by customer).
     */
    public function createSnapToken(string $orderNumber): JsonResponse
    {
        if (!$this->midtrans->isEnabled()) {
            return response()->json(['message' => 'Midtrans is disabled.'], 503);
        }

        $order = Order::where('order_number', $orderNumber)
            ->where('payment_method', 'midtrans')
            ->where('payment_status', '!=', 'paid')
            ->firstOrFail();

        $result = $this->midtrans->createSnapToken($order);

        if (!empty($result['error'])) {
            return response()->json(['message' => $result['message']], 502);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Midtrans webhook notification handler.
     * This endpoint must be excluded from CSRF (already excluded in API).
     */
    public function notification(Request $request): JsonResponse
    {
        $payload = $request->all();

        $order = $this->midtrans->handleNotification($payload);

        if (!$order) {
            return response()->json(['message' => 'Ignored.'], 200);
        }

        return response()->json(['message' => 'Notification processed.']);
    }

    /**
     * Get Midtrans client config (for frontend Snap.js).
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'data' => [
                'client_key'           => $this->midtrans->getClientKey(),
                'is_production'        => $this->midtrans->isProduction(),
                'snap_url'             => $this->midtrans->isProduction()
                    ? 'https://app.midtrans.com/snap/snap.js'
                    : 'https://app.sandbox.midtrans.com/snap/snap.js',
                'midtrans_enabled'     => (bool) Setting::get('midtrans_enabled', true),
                'cod_enabled'          => (bool) Setting::get('cod_enabled', true),
                'bank_transfer_enabled'=> (bool) Setting::get('bank_transfer_enabled', true),
                'bank_transfer_info'   => Setting::get('bank_transfer_info', ''),
                'rajaongkir_enabled'   => (bool) Setting::get('rajaongkir_enabled', true),
                'default_shipping_cost'=> (int) Setting::get('default_shipping_cost', 0),
            ],
        ]);
    }
}
