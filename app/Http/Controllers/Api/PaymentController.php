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

    public function transactionDetails(Request $request, string $orderNumber): JsonResponse
    {
        if (!$this->midtrans->isEnabled()) {
            return response()->json(['message' => 'Midtrans is disabled.'], 503);
        }

        $order = Order::where('order_number', $orderNumber)
            ->where('payment_method', 'midtrans')
            ->firstOrFail();

        $snapToken = (string) $request->query('snap_token', '');
        $transactionId = (string) $request->query('transaction_id', '');
        if ($transactionId !== '' && !preg_match('/^[a-f0-9-]{20,64}$/i', $transactionId)) {
            return response()->json(['message' => 'Invalid transaction reference.'], 422);
        }
        $tokenMatches = $order->midtrans_token && hash_equals((string) $order->midtrans_token, $snapToken);
        $transactionMatches = $order->midtrans_transaction_id && $transactionId
            && hash_equals((string) $order->midtrans_transaction_id, $transactionId);
        if (!$tokenMatches && !$transactionMatches) {
            return response()->json(['message' => 'Invalid payment reference.'], 403);
        }

        $details = $this->midtrans->getTransactionDetails($order, $transactionId ?: null);
        $transactionStatus = $details['transaction_status'] ?? '';
        $fraudStatus = $details['fraud_status'] ?? null;

        if ($transactionStatus === 'settlement'
            || ($transactionStatus === 'capture' && in_array($fraudStatus, ['accept', null], true))) {
            $order->update([
                'midtrans_transaction_id' => ($details['transaction_id'] ?? null) ?: $order->midtrans_transaction_id,
                'payment_status' => 'paid',
                'status' => $order->status === 'pending' ? 'confirmed' : $order->status,
                'paid_at' => $order->paid_at ?: now(),
            ]);
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'], true)) {
            $order->update([
                'midtrans_transaction_id' => ($details['transaction_id'] ?? null) ?: $order->midtrans_transaction_id,
                'payment_status' => 'failed',
            ]);
        }

        return response()->json(['data' => $details]);
    }

    public function createSandboxQris(Request $request, string $orderNumber): JsonResponse
    {
        if (!$this->midtrans->isEnabled() || $this->midtrans->isProduction()) {
            return response()->json(['message' => 'QRIS Sandbox is unavailable.'], 503);
        }

        $order = Order::where('order_number', $orderNumber)
            ->where('payment_method', 'midtrans')
            ->where('payment_status', '!=', 'paid')
            ->firstOrFail();

        $paymentReference = (string) $request->input('payment_reference', '');
        if (!$order->midtrans_token || !hash_equals((string) $order->midtrans_token, $paymentReference)) {
            return response()->json(['message' => 'Invalid payment reference.'], 403);
        }

        if ($order->midtrans_transaction_id) {
            $existing = $this->midtrans->getTransactionDetails($order, $order->midtrans_transaction_id);
            $existingQr = collect($existing['actions'] ?? [])->firstWhere('name', 'generate-qr-code-v2')
                ?? collect($existing['actions'] ?? [])->firstWhere('name', 'generate-qr-code');
            if ($existingQr) {
                return response()->json(['data' => [
                    'transaction_id' => $order->midtrans_transaction_id,
                    'qr_url' => $existingQr['url'],
                ]]);
            }
            return response()->json(['message' => 'Transaksi Midtrans untuk pesanan ini sudah dibuat.'], 409);
        }

        $result = $this->midtrans->createSandboxQris($order);
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
                'midtrans_enabled'     => $this->midtrans->isEnabled(),
                'cod_enabled'          => (bool) Setting::get('cod_enabled', true),
                'bank_transfer_enabled'=> (bool) Setting::get('bank_transfer_enabled', true),
                'bank_transfer_info'   => Setting::get('bank_transfer_info', ''),
                'default_shipping_cost'=> (int) Setting::get('default_shipping_cost', 20000),
            ],
        ]);
    }
}
