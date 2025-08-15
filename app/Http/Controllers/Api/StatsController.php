<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\Agent;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use App\Models\AgentEarning;
use App\Models\MarketProduct;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_markets' => Market::count(),
            'total_agents' => Agent::count(),
            'total_products' => Product::count(),
            'total_categories' => Category::count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'paid_orders' => Order::where('status', 'paid')->count(),
            'delivered_orders' => Order::where('status', 'delivered')->count(),
            'total_revenue' => Order::where('status', 'paid')->sum('total_amount'),
            'total_earnings' => AgentEarning::where('status', 'paid')->sum('amount'),
            'pending_earnings' => AgentEarning::where('status', 'pending')->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function marketStats(): JsonResponse
    {
        $marketStats = Market::withCount(['agents', 'orders'])
            ->withSum('orders', 'total_amount')
            ->get()
            ->map(function ($market) {
                return [
                    'id' => $market->id,
                    'name' => $market->name,
                    'agents_count' => $market->agents_count,
                    'orders_count' => $market->orders_count,
                    'total_revenue' => $market->orders_sum_total_amount ?? 0,
                    'is_active' => $market->is_active,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $marketStats,
        ]);
    }

    public function agentStats(): JsonResponse
    {
        $agentStats = Agent::withCount(['orders', 'earnings'])
            ->withSum('earnings', 'amount')
            ->get()
            ->map(function ($agent) {
                return [
                    'id' => $agent->id,
                    'name' => $agent->full_name,
                    'market' => $agent->market->name,
                    'orders_count' => $agent->orders_count,
                    'earnings_count' => $agent->earnings_count,
                    'total_earnings' => $agent->earnings_sum_amount ?? 0,
                    'is_active' => $agent->is_active,
                    'is_suspended' => $agent->is_suspended,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $agentStats,
        ]);
    }

    public function orderStats(): JsonResponse
    {
        $orderStats = [
            'total_orders' => Order::count(),
            'orders_by_status' => Order::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status'),
            'orders_by_market' => Order::with('market')
                ->select('market_id', DB::raw('count(*) as count'))
                ->groupBy('market_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'market_name' => $item->market->name,
                        'count' => $item->count,
                    ];
                }),
            'revenue_by_month' => Order::where('status', 'paid')
                ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'), DB::raw('SUM(total_amount) as revenue'))
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get(),
            'average_order_value' => Order::where('status', 'paid')->avg('total_amount'),
            'top_products' => DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->select('products.name', DB::raw('SUM(order_items.quantity) as total_quantity'))
                ->groupBy('products.id', 'products.name')
                ->orderBy('total_quantity', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $orderStats,
        ]);
    }

    public function productStats(): JsonResponse
    {
        $productStats = [
            'total_products' => Product::count(),
            'products_by_category' => Product::with('category')
                ->select('category_id', DB::raw('count(*) as count'))
                ->groupBy('category_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'category_name' => $item->category->name,
                        'count' => $item->count,
                    ];
                }),
            'available_products' => MarketProduct::where('is_available', true)->count(),
            'products_with_stock' => MarketProduct::where('stock_quantity', '>', 0)->count(),
            'low_stock_products' => MarketProduct::where('stock_quantity', '<=', 10)
                ->where('stock_quantity', '>', 0)
                ->count(),
            'out_of_stock_products' => MarketProduct::where('stock_quantity', 0)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $productStats,
        ]);
    }

    public function earningsStats(): JsonResponse
    {
        $earningsStats = [
            'total_earnings' => AgentEarning::sum('amount'),
            'paid_earnings' => AgentEarning::where('status', 'paid')->sum('amount'),
            'pending_earnings' => AgentEarning::where('status', 'pending')->sum('amount'),
            'earnings_by_agent' => AgentEarning::with('agent')
                ->select('agent_id', DB::raw('SUM(amount) as total_earnings'))
                ->groupBy('agent_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'agent_name' => $item->agent->full_name,
                        'total_earnings' => $item->total_earnings,
                    ];
                }),
            'earnings_by_month' => AgentEarning::where('status', 'paid')
                ->select(DB::raw('DATE_FORMAT(paid_at, "%Y-%m") as month'), DB::raw('SUM(amount) as earnings'))
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $earningsStats,
        ]);
    }

    public function recentActivity(): JsonResponse
    {
        $recentActivity = [
            'recent_orders' => Order::with(['market', 'agent'])
                ->latest()
                ->take(10)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer_name,
                        'total_amount' => $order->total_amount,
                        'status' => $order->status,
                        'market' => $order->market ? $order->market->name : null,
                        'agent' => $order->agent ? $order->agent->full_name : null,
                        'created_at' => $order->created_at,
                    ];
                }),
            'recent_earnings' => AgentEarning::with(['agent', 'order'])
                ->latest()
                ->take(10)
                ->get()
                ->map(function ($earning) {
                    return [
                        'id' => $earning->id,
                        'agent_name' => $earning->agent->full_name,
                        'order_number' => $earning->order->order_number,
                        'amount' => $earning->amount,
                        'status' => $earning->status,
                        'created_at' => $earning->created_at,
                    ];
                }),
        ];

        return response()->json([
            'success' => true,
            'data' => $recentActivity,
        ]);
    }

    public function performanceMetrics(): JsonResponse
    {
        $performanceMetrics = [
            'order_completion_rate' => $this->calculateOrderCompletionRate(),
            'average_delivery_time' => $this->calculateAverageDeliveryTime(),
            'agent_performance' => $this->calculateAgentPerformance(),
            'market_performance' => $this->calculateMarketPerformance(),
            'revenue_growth' => $this->calculateRevenueGrowth(),
        ];

        return response()->json([
            'success' => true,
            'data' => $performanceMetrics,
        ]);
    }

    private function calculateOrderCompletionRate(): float
    {
        $totalOrders = Order::count();
        $completedOrders = Order::where('status', 'delivered')->count();

        return $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 2) : 0;
    }

    private function calculateAverageDeliveryTime(): float
    {
        $deliveredOrders = Order::where('status', 'delivered')
            ->whereNotNull('delivered_at')
            ->whereNotNull('created_at')
            ->get();

        if ($deliveredOrders->isEmpty()) {
            return 0;
        }

        $totalHours = $deliveredOrders->sum(function ($order) {
            return $order->created_at->diffInHours($order->delivered_at);
        });

        return round($totalHours / $deliveredOrders->count(), 2);
    }

    private function calculateAgentPerformance(): array
    {
        return Agent::withCount(['orders' => function ($query) {
            $query->where('status', 'delivered');
        }])
        ->withSum('earnings', 'amount')
        ->get()
        ->map(function ($agent) {
            return [
                'agent_name' => $agent->full_name,
                'completed_orders' => $agent->orders_count,
                'total_earnings' => $agent->earnings_sum_amount ?? 0,
                'average_earnings_per_order' => $agent->orders_count > 0
                    ? round(($agent->earnings_sum_amount ?? 0) / $agent->orders_count, 2)
                    : 0,
            ];
        })
        ->toArray();
    }

    private function calculateMarketPerformance(): array
    {
        return Market::withCount(['orders' => function ($query) {
            $query->where('status', 'delivered');
        }])
        ->withSum('orders', 'total_amount')
        ->get()
        ->map(function ($market) {
            return [
                'market_name' => $market->name,
                'completed_orders' => $market->orders_count,
                'total_revenue' => $market->orders_sum_total_amount ?? 0,
                'average_order_value' => $market->orders_count > 0
                    ? round(($market->orders_sum_total_amount ?? 0) / $market->orders_count, 2)
                    : 0,
            ];
        })
        ->toArray();
    }

    private function calculateRevenueGrowth(): array
    {
        $currentMonth = now()->format('Y-m');
        $lastMonth = now()->subMonth()->format('Y-m');

        $currentMonthRevenue = Order::where('status', 'paid')
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$currentMonth])
            ->sum('total_amount');

        $lastMonthRevenue = Order::where('status', 'paid')
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$lastMonth])
            ->sum('total_amount');

        $growthRate = $lastMonthRevenue > 0
            ? round((($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 2)
            : 0;

        return [
            'current_month_revenue' => $currentMonthRevenue,
            'last_month_revenue' => $lastMonthRevenue,
            'growth_rate' => $growthRate,
            'trend' => $growthRate > 0 ? 'up' : ($growthRate < 0 ? 'down' : 'stable'),
        ];
    }
}
