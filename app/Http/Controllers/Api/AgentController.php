<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Order;
use App\Models\MarketProduct;
use App\Models\AgentEarning;
use App\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AgentController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $agent = Agent::where('email', $request->email)->first();

        if (!$agent || !Hash::check($request->password, $agent->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (!$agent->is_active || $agent->is_suspended) {
            return response()->json([
                'success' => false,
                'message' => 'Account is suspended or inactive',
            ], 403);
        }

        $agent->update(['last_login_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => 'agent_token_' . $agent->id . '_' . time(),
                'user' => [
                    'id' => $agent->id,
                    'name' => $agent->full_name,
                    'email' => $agent->email,
                    'phone' => $agent->phone,
                    'market' => $agent->market->name,
                    'role' => 'agent',
                ],
            ],
        ]);
    }

    public function dashboard(): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        $stats = [
            'total_orders' => $agent->orders()->count(),
            'pending_orders' => $agent->orders()->where('status', 'assigned')->count(),
            'active_orders' => $agent->orders()->whereIn('status', ['preparing', 'ready_for_delivery', 'out_for_delivery'])->count(),
            'completed_orders' => $agent->orders()->where('status', 'delivered')->count(),
            'total_earnings' => $agent->earnings()->where('status', 'paid')->sum('amount'),
            'pending_earnings' => $agent->earnings()->where('status', 'pending')->sum('amount'),
        ];

        $recentOrders = $agent->orders()
            ->with('market')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_orders' => $recentOrders,
            ],
        ]);
    }

    public function getOrders(): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        $orders = $agent->orders()
            ->with('market')
            ->latest()
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'whatsapp_number' => $order->whatsapp_number,
                    'delivery_address' => $order->delivery_address,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Search orders for agent
     */
    public function searchOrders(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:255',
        ]);

        $agent = $this->getCurrentAgent();

        $orders = $agent->orders()
            ->with('market')
            ->where(function ($query) use ($request) {
                $query->where('order_number', 'like', '%' . $request->query . '%')
                      ->orWhere('customer_name', 'like', '%' . $request->query . '%')
                      ->orWhere('whatsapp_number', 'like', '%' . $request->query . '%');
            })
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'whatsapp_number' => $order->whatsapp_number,
                    'delivery_address' => $order->delivery_address,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $orders,
            'count' => $orders->count(),
        ]);
    }

    public function updateOrderStatus(Request $request, Order $order): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        if ($order->agent_id !== $agent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Order not assigned to you',
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:preparing,ready_for_delivery,out_for_delivery,delivered',
            'message' => 'nullable|string',
        ]);

        $order->updateStatus($request->status, $request->message ?? '');

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
        ]);
    }

    public function getEarnings(): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        $earnings = $agent->earnings()
            ->with('order')
            ->latest()
            ->get()
            ->map(function ($earning) {
                return [
                    'id' => $earning->id,
                    'order_number' => $earning->order->order_number,
                    'amount' => $earning->amount,
                    'status' => $earning->status,
                    'paid_at' => $earning->paid_at,
                    'created_at' => $earning->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $earnings,
        ]);
    }

    /**
     * Get all commissions for agent
     */
    public function getCommissions(): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        $commissions = Commission::where('agent_id', $agent->id)
            ->with('order')
            ->latest()
            ->get()
            ->map(function ($commission) {
                return [
                    'id' => $commission->id,
                    'order_number' => $commission->order->order_number,
                    'amount' => $commission->amount,
                    'status' => $commission->status,
                    'approved_at' => $commission->approved_at,
                    'paid_at' => $commission->paid_at,
                    'created_at' => $commission->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $commissions,
        ]);
    }

    /**
     * Get pending commissions for agent
     */
    public function getPendingCommissions(): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        $commissions = Commission::where('agent_id', $agent->id)
            ->where('status', 'pending')
            ->with('order')
            ->latest()
            ->get()
            ->map(function ($commission) {
                return [
                    'id' => $commission->id,
                    'order_number' => $commission->order->order_number,
                    'amount' => $commission->amount,
                    'created_at' => $commission->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $commissions,
        ]);
    }

    /**
     * Get paid commissions for agent
     */
    public function getPaidCommissions(): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        $commissions = Commission::where('agent_id', $agent->id)
            ->where('status', 'paid')
            ->with('order')
            ->latest()
            ->get()
            ->map(function ($commission) {
                return [
                    'id' => $commission->id,
                    'order_number' => $commission->order->order_number,
                    'amount' => $commission->amount,
                    'paid_at' => $commission->paid_at,
                    'created_at' => $commission->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $commissions,
        ]);
    }

    // Product Management
    public function getProducts(): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        $products = MarketProduct::with(['product.category'])
            ->where('market_id', $agent->market_id)
            ->where('agent_id', $agent->id)
            ->get()
            ->map(function ($marketProduct) {
                return [
                    'id' => $marketProduct->id,
                    'product_id' => $marketProduct->product_id,
                    'name' => $marketProduct->product->name,
                    'description' => $marketProduct->product->description,
                    'unit' => $marketProduct->product->unit,
                    'price' => $marketProduct->price,
                    'stock_quantity' => $marketProduct->stock_quantity,
                    'is_available' => $marketProduct->is_available,
                    'category' => $marketProduct->product->category->name,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function getAllProducts(): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        // Get all products that the agent hasn't added to their inventory yet
        $existingProductIds = MarketProduct::where('market_id', $agent->market_id)
            ->where('agent_id', $agent->id)
            ->pluck('product_id');

        $products = \App\Models\Product::with('category')
            ->whereNotIn('id', $existingProductIds)
            ->where('is_active', true)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'unit' => $product->unit,
                    'category' => $product->category->name,
                    'category_id' => $product->category_id,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function addProduct(Request $request): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
        ]);

        // Check if product already exists for this agent
        $existingProduct = MarketProduct::where('market_id', $agent->market_id)
            ->where('product_id', $request->product_id)
            ->where('agent_id', $agent->id)
            ->first();

        if ($existingProduct) {
            return response()->json([
                'success' => false,
                'message' => 'Product already exists in your inventory',
            ], 400);
        }

        $marketProduct = MarketProduct::create([
            'market_id' => $agent->market_id,
            'product_id' => $request->product_id,
            'agent_id' => $agent->id,
            'price' => $request->price,
            'stock_quantity' => $request->stock_quantity ?? null,
            'is_available' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $marketProduct->load('product'),
        ], 201);
    }

    public function updateProduct(Request $request, MarketProduct $marketProduct): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        if ($marketProduct->agent_id !== $agent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found in your inventory',
            ], 404);
        }

        $request->validate([
            'price' => 'sometimes|required|numeric|min:0',
            'stock_quantity' => 'sometimes|nullable|integer|min:0',
            'is_available' => 'sometimes|boolean',
        ]);

        $marketProduct->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $marketProduct->load('product'),
        ]);
    }

    public function removeProduct(MarketProduct $marketProduct): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        if ($marketProduct->agent_id !== $agent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found in your inventory',
            ], 404);
        }

        $marketProduct->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product removed successfully',
        ]);
    }

    // Profile Management
    public function getProfile(): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $agent->id,
                'first_name' => $agent->first_name,
                'last_name' => $agent->last_name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'market' => $agent->market->name,
                'is_active' => $agent->is_active,
                'last_login_at' => $agent->last_login_at,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|unique:agents,phone,' . $agent->id,
        ]);

        $agent->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $agent->fresh(),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
            'confirm_password' => 'required|same:new_password',
        ]);

        if (!Hash::check($request->current_password, $agent->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        $agent->update(['password' => Hash::make($request->new_password)]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    private function getCurrentAgent(): Agent
    {
        // In a real implementation, you'd get this from the authenticated user
        // For now, we'll use a placeholder
        return Agent::first();
    }
}
