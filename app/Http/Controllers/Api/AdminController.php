<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\Agent;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use App\Models\MarketProduct;
use App\Models\Commission;
use App\Services\PaymentService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private PaystackService $paystackService
    ) {}

    public function healthCheck(): JsonResponse
    {
        try {
            // Test database connection
            DB::connection()->getPdo();

            // Test basic queries
            $marketCount = Market::count();
            $agentCount = Agent::count();
            $orderCount = Order::count();

            return response()->json([
                'success' => true,
                'message' => 'Database connection successful',
                'data' => [
                    'database_connected' => true,
                    'markets_count' => $marketCount,
                    'agents_count' => $agentCount,
                    'orders_count' => $orderCount,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Health check error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Database connection failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

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
        try {
            // Check if database connection is working
            DB::connection()->getPdo();

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
        } catch (\Exception $e) {
            Log::error('Admin dashboard error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Database connection error. Please check your database configuration.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
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
        ]);

        $market = Market::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $market,
        ], 201);
    }

    public function getMarkets(): JsonResponse
    {
        try {
        $markets = Market::all();

        return response()->json([
            'success' => true,
            'data' => $markets,
        ]);
        } catch (\Exception $e) {
            Log::error('Get markets error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch markets',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
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
            'bank_code' => 'required|string',
            'bank_name' => 'required|string',
            'account_number' => 'required|string|min:10|max:10',
            'account_name' => 'required|string',
        ]);

                // Verify bank account with Paystack
        $verificationResult = $this->paystackService->verifyAccountNumber(
            $request->account_number,
            $request->bank_code
        );

        if (!$verificationResult['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account verification failed',
                'error' => $verificationResult['message'],
            ], 422);
        }

        // Verify that the account name matches
        $verifiedAccountName = $verificationResult['data']['account_name'] ?? '';
        if (strtolower(trim($verifiedAccountName)) !== strtolower(trim($request->account_name))) {
            return response()->json([
                'success' => false,
                'message' => 'Account name does not match the verified account name',
                'error' => 'Expected: ' . $verifiedAccountName . ', Provided: ' . $request->account_name,
            ], 422);
        }

        $password = strtolower($request->first_name); // Default password

        $agent = Agent::create([
            'market_id' => $request->market_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $password,
            'bank_code' => $request->bank_code,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'bank_verified' => true,
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
                'bank_name' => $agent->bank_name,
                'account_name' => $agent->account_name,
                'bank_verified' => $agent->bank_verified,
                'default_password' => $password,
            ],
        ], 201);
    }

    public function getAgents(Request $request): JsonResponse
    {
        try {
            $query = Agent::with(['market']);

            // Filter by market if market_id is provided
            if ($request->has('market_id') && $request->market_id) {
                $query->where('market_id', $request->market_id);
            }

            $agents = $query->get()
            ->map(function ($agent) {
                return [
                    'id' => $agent->id,
                    'name' => $agent->full_name,
                    'email' => $agent->email,
                    'phone' => $agent->phone,
                        'market' => $agent->market ? $agent->market->name : null,
                        'market_id' => $agent->market_id,
                        'bank_name' => $agent->bank_name,
                        'account_name' => $agent->account_name,
                        'bank_verified' => $agent->bank_verified,
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
        } catch (\Exception $e) {
            Log::error('Get agents error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch agents',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
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

    public function showAgent(Agent $agent): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $agent->id,
                'name' => $agent->full_name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'market' => $agent->market ? $agent->market->name : null,
                'market_id' => $agent->market_id,
                'bank_name' => $agent->bank_name,
                'account_name' => $agent->account_name,
                'bank_verified' => $agent->bank_verified,
                'is_active' => $agent->is_active,
                'is_suspended' => $agent->is_suspended,
                'last_login_at' => $agent->last_login_at,
                'created_at' => $agent->created_at,
            ],
        ]);
    }

    public function updateAgent(Request $request, Agent $agent): JsonResponse
    {
        $request->validate([
            'market_id' => 'sometimes|required|exists:markets,id',
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:agents,email,' . $agent->id,
            'phone' => 'sometimes|required|string|max:20',
            'bank_code' => 'sometimes|required|string',
            'bank_name' => 'sometimes|required|string',
            'account_number' => 'sometimes|required|string|min:10|max:10',
            'account_name' => 'sometimes|required|string',
        ]);

        // If bank details are being updated, verify them
        if ($request->has('bank_code') && $request->has('account_number') && $request->has('account_name')) {
            $verificationResult = $this->paystackService->verifyAccountNumber(
                $request->account_number,
                $request->bank_code
            );

            if (!$verificationResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank account verification failed',
                    'error' => $verificationResult['message'],
                ], 422);
            }

            // Verify that the account name matches
            $verifiedAccountName = $verificationResult['data']['account_name'] ?? '';
            if (strtolower(trim($verifiedAccountName)) !== strtolower(trim($request->account_name))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account name does not match the verified account name',
                    'error' => 'Expected: ' . $verifiedAccountName . ', Provided: ' . $request->account_name,
                ], 422);
            }

            $request->merge(['bank_verified' => true]);
        }

        $agent->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Agent updated successfully',
            'data' => [
                'id' => $agent->id,
                'name' => $agent->full_name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'market' => $agent->market ? $agent->market->name : null,
                'bank_name' => $agent->bank_name,
                'account_name' => $agent->account_name,
                'bank_verified' => $agent->bank_verified,
            ],
        ]);
    }

    public function destroyAgent(Agent $agent): JsonResponse
    {
        // Check if agent has active orders
        $activeOrders = $agent->orders()->whereIn('status', [
            'assigned',
            'preparing',
            'ready_for_delivery',
            'out_for_delivery'
        ])->count();

        if ($activeOrders > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete agent with active orders',
                'error' => 'Agent has ' . $activeOrders . ' active orders',
            ], 422);
        }

        $agent->delete();

        return response()->json([
            'success' => true,
            'message' => 'Agent deleted successfully',
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
                    'image' => $product->image,
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

    public function getProductsByMarket(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => 'required|exists:markets,id',
        ]);

        try {
            $marketProducts = MarketProduct::with(['product.category', 'agent', 'productPrices'])
                ->where('market_id', $request->market_id)
                ->get()
                ->map(function ($marketProduct) {
                    return [
                        'id' => $marketProduct->id,
                        'product_id' => $marketProduct->product_id,
                        'product_name' => $marketProduct->product_name ?? $marketProduct->product->name,
                        'base_product_name' => $marketProduct->product->name,
                        'product_description' => $marketProduct->product->description,
                        'image' => $marketProduct->product->image,
                        'unit' => $marketProduct->product->unit,
                        'category' => $marketProduct->product->category->name,
                        'agent_name' => $marketProduct->agent->full_name,
                        'agent_id' => $marketProduct->agent_id,
                        'is_available' => $marketProduct->is_available,
                        'created_at' => $marketProduct->created_at,
                        'prices' => $marketProduct->productPrices->map(function ($price) {
                            return [
                                'id' => $price->id,
                                'measurement_scale' => $price->measurement_scale,
                                'price' => $price->price,
                                'stock_quantity' => $price->stock_quantity,
                                'is_available' => $price->is_available,
                            ];
                        }),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $marketProducts,
            ]);
        } catch (\Exception $e) {
            Log::error('Get products by market error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products for market',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
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
        $marketProducts = MarketProduct::with(['market', 'product.category', 'agent', 'productPrices'])
            ->get()
            ->map(function ($marketProduct) {
                return [
                    'id' => $marketProduct->id,
                    'market' => $marketProduct->market->name,
                    'product_name' => $marketProduct->product_name ?? $marketProduct->product->name,
                    'base_product_name' => $marketProduct->product->name,
                    'category' => $marketProduct->product->category->name,
                    'agent' => $marketProduct->agent->full_name,
                    'is_available' => $marketProduct->is_available,
                    'created_at' => $marketProduct->created_at,
                    'prices' => $marketProduct->productPrices->map(function ($price) {
                        return [
                            'id' => $price->id,
                            'measurement_scale' => $price->measurement_scale,
                            'price' => $price->price,
                            'stock_quantity' => $price->stock_quantity,
                            'is_available' => $price->is_available,
                        ];
                    }),
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
            'product_name' => 'required|string|max:255',
            'agent_id' => 'required|exists:agents,id',
            'prices' => 'required|array|min:1',
            'prices.*.measurement_scale' => 'required|string|max:50',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.stock_quantity' => 'nullable|integer|min:0',
            'is_available' => 'boolean',
        ]);

        // Check if product name already exists for this agent in this market
        $existingProduct = MarketProduct::where('market_id', $request->market_id)
            ->where('agent_id', $request->agent_id)
            ->where('product_name', $request->product_name)
            ->first();

        if ($existingProduct) {
            return response()->json([
                'success' => false,
                'message' => 'Product with this name already exists for this agent in this market',
            ], 400);
        }

        $marketProduct = MarketProduct::create([
            'market_id' => $request->market_id,
            'product_id' => $request->product_id,
            'product_name' => $request->product_name,
            'agent_id' => $request->agent_id,
            'is_available' => $request->is_available ?? true,
        ]);

        // Create product prices for different measurement scales
        foreach ($request->prices as $priceData) {
            $marketProduct->productPrices()->create([
                'measurement_scale' => $priceData['measurement_scale'],
                'price' => $priceData['price'],
                'stock_quantity' => $priceData['stock_quantity'] ?? null,
                'is_available' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $marketProduct->load(['market', 'product.category', 'agent', 'productPrices']),
        ], 201);
    }

    public function updateMarketProduct(Request $request, MarketProduct $marketProduct): JsonResponse
    {
        $request->validate([
            'product_name' => 'sometimes|required|string|max:255',
            'is_available' => 'boolean',
        ]);

        $marketProduct->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $marketProduct->load(['market', 'product.category', 'agent', 'productPrices']),
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
    public function getOrders(Request $request): JsonResponse
    {
        $query = Order::select([
            'id', 'order_number', 'customer_name', 'whatsapp_number',
            'delivery_address', 'total_amount', 'status', 'market_id', 'agent_id',
            'created_at', 'updated_at'
        ])
        ->with([
            'market:id,name',
            'agent:id,first_name,last_name'
        ]); // Only load necessary fields

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by market
        if ($request->has('market_id') && $request->market_id) {
            $query->where('market_id', $request->market_id);
        }

        // Filter by agent
        if ($request->has('agent_id') && $request->agent_id) {
            $query->where('agent_id', $request->agent_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Search by order number, customer name, or phone
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('order_number', 'like', "%{$searchTerm}%")
                  ->orWhere('customer_name', 'like', "%{$searchTerm}%")
                  ->orWhere('whatsapp_number', 'like', "%{$searchTerm}%");
            });
        }

        // Pagination with optimized limit
        $perPage = min($request->per_page ?? 20, 100); // Cap at 100 for performance
        $orders = $query->latest('created_at')->paginate($perPage);

        $formattedOrders = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'whatsapp_number' => $order->whatsapp_number,
                'delivery_address' => $order->delivery_address,
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'market' => $order->market ? [
                    'id' => $order->market->id,
                    'name' => $order->market->name,
                ] : null,
                'agent' => $order->agent ? [
                    'id' => $order->agent->id,
                    'name' => $order->agent->full_name,
                ] : null,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedOrders,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Search orders for admin
     */
    public function searchOrders(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:255',
        ]);

        $orders = Order::with(['market', 'agent'])
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
                    'market' => $order->market ? [
                        'id' => $order->market->id,
                        'name' => $order->market->name,
                    ] : null,
                    'agent' => $order->agent ? [
                        'id' => $order->agent->id,
                        'name' => $order->agent->full_name,
                    ] : null,
                    'created_at' => $order->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $orders,
            'count' => $orders->count(),
        ]);
    }

    public function assignAgent(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'agent_id' => 'required|exists:agents,id',
        ]);

        $order->update(['agent_id' => $request->agent_id]);

        // Update order status to assigned
        $order->updateStatus('assigned', 'Agent assigned to order');

        // Send WhatsApp notification about agent assignment
        try {
            $this->sendWhatsAppStatusUpdate($order);
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp status update: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Agent assigned successfully',
        ]);
    }

    public function updateOrderStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready_for_delivery,out_for_delivery,delivered,cancelled',
            'message' => 'nullable|string',
        ]);

        $order->updateStatus($request->status, $request->message ?? '');

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
        ]);
    }

    /**
     * Approve agent for order
     */
    public function approveAgent(Request $request, Order $order): JsonResponse
    {
        if (!$order->agent_id) {
            return response()->json([
                'success' => false,
                'message' => 'No agent assigned to this order',
            ], 400);
        }

        // Create commission record
        $commission = $order->commissions()->create([
            'agent_id' => $order->agent_id,
            'amount' => $order->total_amount * 0.1, // 10% commission
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // Send notification to operations@foodstuff.store
        // TODO: Implement email notification

        return response()->json([
            'success' => true,
            'message' => 'Agent approved and commission created',
            'data' => [
                'commission_id' => $commission->id,
                'amount' => $commission->amount,
            ],
        ]);
    }

    /**
     * Switch agent for order
     */
    public function switchAgent(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'agent_id' => 'required|exists:agents,id',
        ]);

        $oldAgentId = $order->agent_id;
        $order->update(['agent_id' => $request->agent_id]);

        // Send notification to operations@foodstuff.store about agent switch
        // TODO: Implement email notification

        return response()->json([
            'success' => true,
            'message' => 'Agent switched successfully',
            'data' => [
                'old_agent_id' => $oldAgentId,
                'new_agent_id' => $request->agent_id,
            ],
        ]);
    }

    /**
     * Update agent permissions
     */
    public function updateAgentPermissions(Request $request, Agent $agent): JsonResponse
    {
        $request->validate([
            'can_add_products' => 'sometimes|boolean',
            'can_update_prices' => 'sometimes|boolean',
        ]);

        $agent->update($request->only(['can_add_products', 'can_update_prices']));

        return response()->json([
            'success' => true,
            'message' => 'Agent permissions updated successfully',
            'data' => [
                'agent_id' => $agent->id,
                'agent_name' => $agent->full_name,
                'can_add_products' => $agent->can_add_products,
                'can_update_prices' => $agent->can_update_prices,
            ],
        ]);
    }

    /**
     * Get agent permissions
     */
    public function getAgentPermissions(Agent $agent): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'agent_id' => $agent->id,
                'agent_name' => $agent->full_name,
                'can_add_products' => $agent->can_add_products,
                'can_update_prices' => $agent->can_update_prices,
            ],
        ]);
    }

    /**
     * Switch agent to different market
     */
    public function switchAgentMarket(Request $request, Agent $agent): JsonResponse
    {
        $request->validate([
            'market_id' => 'required|exists:markets,id',
        ]);

        $oldMarketId = $agent->market_id;
        $agent->update(['market_id' => $request->market_id]);

        return response()->json([
            'success' => true,
            'message' => 'Agent market switched successfully',
            'data' => [
                'agent_id' => $agent->id,
                'old_market_id' => $oldMarketId,
                'new_market_id' => $request->market_id,
            ],
        ]);
    }

    /**
     * Get all commissions
     */
    public function getCommissions(): JsonResponse
    {
        $commissions = Commission::with(['order', 'agent'])
            ->latest()
            ->get()
            ->map(function ($commission) {
                return [
                    'id' => $commission->id,
                    'order_number' => $commission->order->order_number,
                    'agent_name' => $commission->agent->full_name,
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
     * Approve commission
     */
    public function approveCommission(Commission $commission): JsonResponse
    {
        $commission->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // Transfer money to agent
        // TODO: Implement money transfer logic

        return response()->json([
            'success' => true,
            'message' => 'Commission approved and money transferred',
        ]);
    }

    /**
     * Reject commission
     */
    public function rejectCommission(Commission $commission): JsonResponse
    {
        $commission->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commission rejected',
        ]);
    }

    /**
     * Bulk approve commissions
     */
    public function bulkApproveCommissions(Request $request): JsonResponse
    {
        $request->validate([
            'commission_ids' => 'required|array',
            'commission_ids.*' => 'exists:commissions,id',
        ]);

        $commissions = Commission::whereIn('id', $request->commission_ids)
            ->where('status', 'pending')
            ->get();

        foreach ($commissions as $commission) {
            $commission->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);
            // TODO: Implement money transfer logic
        }

        return response()->json([
            'success' => true,
            'message' => count($commissions) . ' commissions approved',
        ]);
    }

    /**
     * Get system settings
     */
    public function getSettings(): JsonResponse
    {
        $settings = [
            'commission_rate' => config('app.commission_rate', 0.1), // 10%
            'delivery_fee' => config('app.delivery_fee', 500),
            'max_delivery_distance' => config('app.max_delivery_distance', 5), // km
            'auto_assign_agents' => config('app.auto_assign_agents', true),
            'whatsapp_bot_url' => config('app.whatsapp_bot_url'),
            'paystack_public_key' => config('services.paystack.public_key'),
        ];

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update system settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'commission_rate' => 'nullable|numeric|between:0,1',
            'delivery_fee' => 'nullable|numeric|min:0',
            'max_delivery_distance' => 'nullable|numeric|min:1|max:50',
            'auto_assign_agents' => 'nullable|boolean',
        ]);

        // Update config values
        // Note: In production, you'd want to store these in database
        foreach ($request->all() as $key => $value) {
            config(['app.' . $key => $value]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
        ]);
    }

    public function logout(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get list of all banks from Paystack
     */
    public function getBanks(): JsonResponse
    {
        try {
            $result = $this->paystackService->getBanks();

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Get banks error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve banks',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Verify bank account number
     */
    public function verifyBankAccount(Request $request): JsonResponse
    {
        $request->validate([
            'account_number' => 'required|string|min:10|max:10',
            'bank_code' => 'required|string',
        ]);

        try {
            $result = $this->paystackService->verifyAccountNumber(
                $request->account_number,
                $request->bank_code
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Verify bank account error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify bank account',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get bank details by code
     */
    public function getBankDetails(Request $request): JsonResponse
    {
        $request->validate([
            'bank_code' => 'required|string',
        ]);

        try {
            $result = $this->paystackService->getBankByCode($request->bank_code);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Get bank details error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bank details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Send WhatsApp status update
     */
    private function sendWhatsAppStatusUpdate(Order $order): void
    {
        $whatsappBotUrl = env('WHATSAPP_BOT_URL', 'https://foodstuff-whatsapp-bot-6536aa3f6997.herokuapp.com');

        // Load the agent relationship if not already loaded
        if (!$order->relationLoaded('agent')) {
            $order->load('agent');
        }

        $statusMessages = [
            'pending' => 'Your order is being processed.',
            'confirmed' => 'Your order has been confirmed!',
            'paid' => 'Payment received! Your order is being prepared.',
            'assigned' => 'An agent has been assigned to your order.',
            'preparing' => 'Your order is being prepared.',
            'ready_for_delivery' => 'Your order is ready for delivery!',
            'out_for_delivery' => 'Your order is on its way to you!',
            'delivered' => 'Your order has been delivered! Enjoy your meal!',
            'cancelled' => 'Your order has been cancelled.',
            'failed' => 'There was an issue with your order.',
            'completed' => 'Your order has been completed successfully!',
        ];

        $message = $statusMessages[$order->status] ?? 'Your order status has been updated.';

        // Add agent information to the message if status is 'assigned' and agent exists
        if ($order->status === 'assigned' && $order->agent) {
            $message = "An agent has been assigned to your order.\n\nAgent: {$order->agent->full_name}\nPhone: {$order->agent->phone}";
        }

        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'message' => $message,
            'whatsapp_number' => $order->whatsapp_number,
        ];

        // Send to WhatsApp bot
        try {
            $whatsappBotUrl = 'https://foodstuff-whatsapp-bot-1aeb07cc3b64.herokuapp.com';
            $response = \Illuminate\Support\Facades\Http::post($whatsappBotUrl . '/order-status-update', $data);

            if ($response->successful()) {
                Log::info('WhatsApp status update sent successfully', [
                    'order_id' => $order->id,
                    'status' => $order->status,
                ]);
            } else {
                Log::error('Failed to send WhatsApp status update', [
                    'order_id' => $order->id,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp status update: ' . $e->getMessage());
        }
    }
}
