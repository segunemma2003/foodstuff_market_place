<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\Agent;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use App\Models\MarketProduct;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // For demo purposes, using a simple admin check
        // In production, you'd have a proper admin table
        if ($request->email === 'admin@foodstuff.store' && $request->password === 'admin123') {
            return response()->json([
                'success' => true,
                'data' => [
                    'token' => 'admin_token_' . time(),
                    'user' => [
                        'id' => 1,
                        'name' => 'Admin',
                        'email' => 'admin@foodstuff.store',
                        'role' => 'admin',
                    ],
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials',
        ], 401);
    }

    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_markets' => Market::count(),
            'total_agents' => Agent::count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'paid_orders' => Order::where('status', 'paid')->count(),
            'delivered_orders' => Order::where('status', 'delivered')->count(),
            'total_revenue' => Order::where('status', 'paid')->sum('total_amount'),
        ];

        $recentOrders = Order::with(['market', 'agent'])
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
            });

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_orders' => $recentOrders,
            ],
        ]);
    }

    // Market Management
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $market = Market::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $market,
        ], 201);
    }

    public function getMarkets(): JsonResponse
    {
        $markets = Market::all();

        return response()->json([
            'success' => true,
            'data' => $markets,
        ]);
    }

    public function show(Market $market): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $market,
        ]);
    }

    public function update(Request $request, Market $market): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'sometimes|required|string',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $market->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $market,
        ]);
    }

    public function destroy(Market $market): JsonResponse
    {
        $market->delete();

        return response()->json([
            'success' => true,
            'message' => 'Market deleted successfully',
        ]);
    }

    public function toggleMarketStatus(Market $market): JsonResponse
    {
        $market->update(['is_active' => !$market->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Market status updated successfully',
            'data' => [
                'is_active' => $market->is_active,
            ],
        ]);
    }

    // Agent Management
    public function createAgent(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => 'required|exists:markets,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:agents,email',
            'phone' => 'required|string|max:20',
        ]);

        $password = strtolower($request->first_name); // Default password

        $agent = Agent::create([
            'market_id' => $request->market_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($password),
        ]);

        // Send welcome email with credentials
        // Mail::to($agent->email)->send(new AgentWelcomeMail($agent, $password));

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $agent->id,
                'name' => $agent->full_name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'market' => $agent->market->name,
                'default_password' => $password,
            ],
        ], 201);
    }

    public function getAgents(): JsonResponse
    {
        $agents = Agent::with(['market'])
            ->get()
            ->map(function ($agent) {
                return [
                    'id' => $agent->id,
                    'name' => $agent->full_name,
                    'email' => $agent->email,
                    'phone' => $agent->phone,
                    'market' => $agent->market->name,
                    'is_active' => $agent->is_active,
                    'is_suspended' => $agent->is_suspended,
                    'last_login_at' => $agent->last_login_at,
                    'created_at' => $agent->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }

    public function suspendAgent(Agent $agent): JsonResponse
    {
        $agent->update(['is_suspended' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Agent suspended successfully',
        ]);
    }

    public function activateAgent(Agent $agent): JsonResponse
    {
        $agent->update(['is_suspended' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Agent activated successfully',
        ]);
    }

    public function resetAgentPassword(Agent $agent): JsonResponse
    {
        $newPassword = strtolower($agent->first_name);
        $agent->update(['password' => Hash::make($newPassword)]);

        // Send password reset email
        // Mail::to($agent->email)->send(new AgentPasswordResetMail($agent, $newPassword));

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully',
            'data' => [
                'new_password' => $newPassword,
            ],
        ]);
    }

    // Product Management
    public function getProducts(): JsonResponse
    {
        $products = Product::with(['category'])
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'unit' => $product->unit,
                    'category' => $product->category->name,
                    'is_active' => $product->is_active,
                    'created_at' => $product->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function createProduct(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:50',
            'is_active' => 'boolean',
        ]);

        $product = Product::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $product->load('category'),
        ], 201);
    }

    public function showProduct(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $product->load('category'),
        ]);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'category_id' => 'sometimes|required|exists:categories,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'sometimes|required|string|max:50',
            'is_active' => 'boolean',
        ]);

        $product->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $product->load('category'),
        ]);
    }

    public function destroyProduct(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    // Category Management
    public function getCategories(): JsonResponse
    {
        $categories = Category::all();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function createCategory(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $category,
        ], 201);
    }

    public function showCategory(Category $category): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
        ]);

        $category->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    public function destroyCategory(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    // Market Product Management (Admin can add products to any market)
    public function getMarketProducts(): JsonResponse
    {
        $marketProducts = MarketProduct::with(['market', 'product.category', 'agent'])
            ->get()
            ->map(function ($marketProduct) {
                return [
                    'id' => $marketProduct->id,
                    'market' => $marketProduct->market->name,
                    'product' => $marketProduct->product->name,
                    'category' => $marketProduct->product->category->name,
                    'agent' => $marketProduct->agent->full_name,
                    'price' => $marketProduct->price,
                    'stock_quantity' => $marketProduct->stock_quantity,
                    'is_available' => $marketProduct->is_available,
                    'created_at' => $marketProduct->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $marketProducts,
        ]);
    }

    public function createMarketProduct(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => 'required|exists:markets,id',
            'product_id' => 'required|exists:products,id',
            'agent_id' => 'required|exists:agents,id',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'is_available' => 'boolean',
        ]);

        // Check if product already exists for this market and agent
        $existingProduct = MarketProduct::where('market_id', $request->market_id)
            ->where('product_id', $request->product_id)
            ->where('agent_id', $request->agent_id)
            ->first();

        if ($existingProduct) {
            return response()->json([
                'success' => false,
                'message' => 'Product already exists for this market and agent',
            ], 400);
        }

        $marketProduct = MarketProduct::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $marketProduct->load(['market', 'product.category', 'agent']),
        ], 201);
    }

    public function updateMarketProduct(Request $request, MarketProduct $marketProduct): JsonResponse
    {
        $request->validate([
            'price' => 'sometimes|required|numeric|min:0',
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'is_available' => 'boolean',
        ]);

        $marketProduct->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $marketProduct->load(['market', 'product.category', 'agent']),
        ]);
    }

    public function destroyMarketProduct(MarketProduct $marketProduct): JsonResponse
    {
        $marketProduct->delete();

        return response()->json([
            'success' => true,
            'message' => 'Market product deleted successfully',
        ]);
    }

    // Order Management
    public function getOrders(): JsonResponse
    {
        $orders = Order::with(['market', 'agent'])
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
                    'market' => $order->market ? $order->market->name : null,
                    'agent' => $order->agent ? $order->agent->full_name : null,
                    'created_at' => $order->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function assignAgent(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'agent_id' => 'required|exists:agents,id',
        ]);

        $agent = Agent::findOrFail($request->agent_id);

        if (!$agent->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Agent is not available',
            ], 400);
        }

        $order->update(['agent_id' => $agent->id]);
        $order->updateStatus('assigned', "Order manually assigned to {$agent->full_name}");

        return response()->json([
            'success' => true,
            'message' => 'Agent assigned successfully',
            'data' => [
                'agent' => [
                    'id' => $agent->id,
                    'name' => $agent->full_name,
                    'phone' => $agent->phone,
                ],
            ],
        ]);
    }

    public function updateOrderStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,paid,assigned,preparing,ready_for_delivery,out_for_delivery,delivered,cancelled,failed',
            'message' => 'nullable|string',
        ]);

        $order->updateStatus($request->status, $request->message ?? '');

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
        ]);
    }

    public function logout(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}
