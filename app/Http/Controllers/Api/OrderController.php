<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CheckoutRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->service->list($request->only([
            'status', 'payment_status', 'search',
            'date_from', 'date_to', 'order_type', 'per_page',
            'filters', 'sorters',
        ]));

        return response()->json($orders->through(fn($o) => new OrderResource($o)));
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['items', 'customer']);

        return response()->json(['data' => new OrderResource($order)]);
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $vercelEnvironment = getenv('VERCEL_ENV') ?: '';

        if ($vercelEnvironment === 'production') {
            return response()->json([
                'message' => 'Checkout production sedang dinonaktifkan. Silakan gunakan versi Preview untuk pengujian.',
            ], 503);
        }

        // A Vercel Preview backed by /tmp SQLite cannot preserve orders between
        // serverless instances. Keep local checkout available for development.
        if ($vercelEnvironment === 'preview'
            && DB::connection()->getDriverName() === 'sqlite'
            && str_starts_with(str_replace('\\', '/', DB::connection()->getDatabaseName()), '/tmp/')) {
            return response()->json([
                'message' => 'Checkout Preview belum tersedia karena database uji belum persisten.',
            ], 503);
        }

        $data = $request->validated();
        if ($vercelEnvironment === 'preview') {
            $previewSessionId = (string) $request->header('X-Preview-Session', '');
            if (!\Illuminate\Support\Str::isUuid($previewSessionId)) {
                return response()->json(['message' => 'Sesi preview tidak valid. Muat ulang halaman checkout.'], 422);
            }
            $data['_preview_session_id'] = strtolower($previewSessionId);
        }

        $order = $this->service->checkout($data);

        return response()->json(['data' => new OrderResource($order)], 201);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $order = $this->service->updateStatus(
            $order,
            $request->except('payment_proof'),
            $request->file('payment_proof')
        );

        return response()->json(['data' => new OrderResource($order)]);
    }

    public function offlineOrder(CheckoutRequest $request): JsonResponse
    {
        $data = $request->validated();
        $order = $this->service->createOfflineOrder($data);

        return response()->json(['data' => new OrderResource($order)], 201);
    }

    public function track(string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', trim($orderNumber))->first();

        if (!$order && ctype_digit($orderNumber)) {
            $order = Order::find($orderNumber);
        }

        if (!$order) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }
        $order->load(['items']);
        return response()->json(['data' => new OrderResource($order)]);
    }
}
