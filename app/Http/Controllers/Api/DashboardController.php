<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $lowStockThreshold = (int) Setting::get('low_stock_threshold', 5);

        // --- Summary stats ---
        // Total sales now measured as total quantity sold (sum of order_items.quantity) for paid or delivered orders
        $totalSales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where(function($q) {
                $q->where('orders.payment_status', 'paid')
                  ->orWhere('orders.status', 'delivered');
            })
            ->sum('order_items.quantity');
        $totalOrders      = Order::count();
        $totalProducts    = Product::where('is_active', true)->count();
        $totalCustomers   = Customer::count();
        $pendingOrders    = Order::where('status', 'pending')->count();
        $lowStockProducts = Product::where('stock', '<=', $lowStockThreshold)
            ->where('is_active', true)->count();

        // --- Today ---
        // Today's sales as total quantity sold today (paid or delivered orders)
        $todaySales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where(function($q) {
                $q->where('orders.payment_status', 'paid')
                  ->orWhere('orders.status', 'delivered');
            })
            ->whereDate('orders.created_at', today())
            ->sum('order_items.quantity');
        $todayOrders = Order::whereDate('created_at', today())->count();

        // --- Recent orders ---
        $recentOrders = Order::with('items')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn($o) => [
                'id'             => $o->id,
                'order_number'   => $o->order_number,
                'customer_name'  => $o->customer_name,
                'total_amount'   => (float) $o->total_amount,
                'status'         => $o->status,
                'payment_status' => $o->payment_status,
                'order_type'     => $o->order_type,
                'items_count'    => $o->items->count(),
                'created_at'     => $o->created_at?->toDateTimeString(),
            ]);

        // --- Sales chart: current month (from 1st of this month until today)
        // Chart 'total' represents total quantity sold per day (sum of order_items.quantity)
        $startOfMonth = now()->startOfMonth();
        $salesChart = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where(function($q) {
                $q->where('orders.payment_status', 'paid')
                  ->orWhere('orders.status', 'delivered');
            })
            ->where('orders.created_at', '>=', $startOfMonth)
            ->selectRaw('DATE(orders.created_at) as date, SUM(order_items.quantity) as total, COUNT(DISTINCT orders.id) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // --- Low stock items ---
        $lowStockItems = Product::where('stock', '<=', $lowStockThreshold)
            ->where('is_active', true)
            ->select('id', 'name', 'stock', 'thumbnail')
            ->orderBy('stock')
            ->take(10)
            ->get()
            ->map(fn($p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'stock'     => $p->stock,
                'thumbnail' => $p->thumbnail ? asset('storage/' . $p->thumbnail) : null,
            ]);

        // --- Order status breakdown ---
        $orderStatusBreakdown = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'data' => [
                'summary' => [
                    'total_sales'        => (float) $totalSales,
                    'total_orders'       => $totalOrders,
                    'total_products'     => $totalProducts,
                    'total_customers'    => $totalCustomers,
                    'pending_orders'     => $pendingOrders,
                    'low_stock_products' => $lowStockProducts,
                    'today_sales'        => (float) $todaySales,
                    'today_orders'       => $todayOrders,
                ],
                'recent_orders'         => $recentOrders,
                'sales_chart'           => $salesChart,
                'low_stock_items'       => $lowStockItems,
                'order_status_breakdown'=> $orderStatusBreakdown,
            ],
        ]);
    }

    public function salesReport(Request $request, string $period = 'monthly'): JsonResponse
    {
        $query = Order::where(function($q) {
            $q->where('payment_status', 'paid')
              ->orWhere('status', 'delivered');
        });

        if (!empty($request->date_from)) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if (!empty($request->date_to)) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($period === 'daily') {
            $data = (clone $query)
                ->where('created_at', '>=', now()->subDays(29))
                ->selectRaw('DATE(created_at) as label, SUM(total_amount) as total, COUNT(*) as count')
                ->groupBy('label')
                ->orderBy('label')
                ->get();
        } else {
            $data = (clone $query)
                ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as label, SUM(total_amount) as total, COUNT(*) as count")
                ->groupBy('label')
                ->orderBy('label')
                ->get();
        }

        $grandTotal = $data->sum('total');
        $grandCount = $data->sum('count');

        return response()->json([
            'data'        => $data,
            'grand_total' => (float) $grandTotal,
            'grand_count' => (int) $grandCount,
            'period'      => $period,
        ]);
    }

    public function productSalesReport(Request $request): JsonResponse
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where(function($q) {
                $q->where('orders.payment_status', 'paid')
                  ->orWhere('orders.status', 'delivered');
            });

        if (!empty($request->date_from)) {
            $query->whereDate('orders.created_at', '>=', $request->date_from);
        }

        if (!empty($request->date_to)) {
            $query->whereDate('orders.created_at', '<=', $request->date_to);
        }

        $data = $query
            ->selectRaw('order_items.product_id as product_id, order_items.product_name as product_name, SUM(order_items.quantity) as total_qty, SUM(order_items.subtotal) as total_revenue')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_qty')
            ->limit(50)
            ->get();

        return response()->json(['data' => $data]);
    }

    public function stockReport(): JsonResponse
    {
        $lowStockThreshold = (int) Setting::get('low_stock_threshold', 5);

        $products = Product::with('variants.tier')
            ->select('id', 'name', 'stock', 'is_active', 'thumbnail')
            ->orderBy('stock')
            ->get()
            ->map(fn($p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'stock'     => $p->stock,
                'is_active' => $p->is_active,
                'is_low'    => $p->stock <= $lowStockThreshold,
                'thumbnail' => $p->thumbnail ? asset('storage/' . $p->thumbnail) : null,
                'variants'  => $p->variants->map(fn($v) => [
                    'id'    => $v->id,
                    'name'  => $v->name,
                    'stock' => $v->stock,
                    'is_low'=> $v->stock <= $lowStockThreshold,
                ]),
            ]);

        return response()->json([
            'data'              => $products,
            'low_stock_count'   => $products->where('is_low', true)->count(),
            'threshold'         => $lowStockThreshold,
        ]);
    }
}
