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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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

        $products = MarketProduct::with(['product.category', 'productPrices'])
            ->where('market_id', $agent->market_id)
            ->where('agent_id', $agent->id)
            ->get()
            ->map(function ($marketProduct) {
                return [
                    'id' => $marketProduct->id,
                    'product_id' => $marketProduct->product_id,
                    'product_name' => $marketProduct->product_name,
                    'base_product_name' => $marketProduct->product->name,
                    'description' => $marketProduct->product->description,
                    'image' => $marketProduct->product->image,
                    'unit' => $marketProduct->product->unit,
                    'is_available' => $marketProduct->is_available,
                    'category' => $marketProduct->product->category->name,
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

    public function getCategories(): JsonResponse
    {
        $categories = \App\Models\Category::where('is_active', true)
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function createProduct(Request $request): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255|unique:products,name',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:50',
            'product_name' => 'required|string|max:255', // Custom name for agent's inventory
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Add image validation
            'prices' => 'required|array|min:1',
            'prices.*.measurement_scale' => 'required|string|max:50',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.stock_quantity' => 'nullable|integer|min:0',
        ]);

        // Check if custom product name already exists for this agent in this market
        $existingProduct = MarketProduct::where('market_id', $agent->market_id)
            ->where('agent_id', $agent->id)
            ->where('product_name', $request->product_name)
            ->first();

        if ($existingProduct) {
            return response()->json([
                'success' => false,
                'message' => 'Product with this name already exists in your inventory',
            ], 400);
        }

        // Handle image upload
        $imageUrl = null;
        if ($request->hasFile('image')) {
            try {
                $image = $request->file('image');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $imagePath = 'products/' . $imageName;

                // Upload to S3
                $imageUrl = Storage::disk('s3')->putFileAs('products', $image, $imageName);
                $imageUrl = config('filesystems.disks.s3.url') . '/' . $imageUrl;
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload image: ' . $e->getMessage(),
                ], 400);
            }
        }

        // Create the new product
        $product = \App\Models\Product::create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'description' => $request->description,
            'image' => $imageUrl,
            'unit' => $request->unit,
            'is_active' => true,
        ]);

        // Add the product to agent's inventory
        $marketProduct = MarketProduct::create([
            'market_id' => $agent->market_id,
            'product_id' => $product->id,
            'product_name' => $request->product_name,
            'agent_id' => $agent->id,
            'is_available' => true,
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
            'message' => 'Product created and added to inventory successfully',
            'data' => [
                'product' => $product->load('category'),
                'market_product' => $marketProduct->load(['productPrices', 'product.category']),
            ],
        ], 201);
    }

    public function addProduct(Request $request): JsonResponse
    {
        try {
            $agent = $this->getCurrentAgent();

            $request->validate([
                'product_id' => 'required|exists:products,id',
                'product_name' => 'required|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Add image validation
                'prices' => 'required|array|min:1',
                'prices.*.measurement_scale' => 'required|string|max:50',
                'prices.*.price' => 'required|numeric|min:0',
                'prices.*.stock_quantity' => 'nullable|integer|min:0',
            ]);

            // Check if product name already exists for this agent in this market
            $existingProduct = MarketProduct::where('market_id', $agent->market_id)
                ->where('agent_id', $agent->id)
                ->where('product_name', $request->product_name)
                ->first();

            if ($existingProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product with this name already exists in your inventory',
                ], 400);
            }

            // Handle image upload if provided
            $imageUrl = null;
            if ($request->hasFile('image')) {
                try {
                    $image = $request->file('image');

                    // Validate image
                    if (!$image->isValid()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid image file',
                        ], 400);
                    }

                    $imageName = time() . '_' . $image->getClientOriginalName();
                    $imagePath = 'products/' . $imageName;

                    // Try S3 first, fallback to local storage
                    try {
                        // Check if S3 is configured
                        if (config('filesystems.disks.s3.key') && config('filesystems.disks.s3.secret')) {
                            // Upload to S3
                            $uploadedPath = Storage::disk('s3')->putFileAs('products', $image, $imageName);

                            if ($uploadedPath) {
                                $imageUrl = config('filesystems.disks.s3.url') . '/' . $uploadedPath;
                            }
                        }
                    } catch (\Exception $s3Error) {
                        // S3 failed, try local storage
                        try {
                            $uploadedPath = Storage::disk('public')->putFileAs('products', $image, $imageName);
                            if ($uploadedPath) {
                                $imageUrl = url('storage/' . $uploadedPath);
                            }
                        } catch (\Exception $localError) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Failed to upload image to both S3 and local storage',
                            ], 500);
                        }
                    }

                    if (!$imageUrl) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to upload image',
                        ], 500);
                    }

                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to upload image: ' . $e->getMessage(),
                    ], 400);
                }
            }

            // Update the product image if provided
            if ($imageUrl) {
                \App\Models\Product::where('id', $request->product_id)->update(['image' => $imageUrl]);
            }

            $marketProduct = MarketProduct::create([
                'market_id' => $agent->market_id,
                'product_id' => $request->product_id,
                'product_name' => $request->product_name,
                'agent_id' => $agent->id,
                'is_available' => true,
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
                'data' => $marketProduct->load('product'),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while adding the product',
                'error' => $e->getMessage(),
            ], 500);
        }
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
            'measurement_scale' => 'sometimes|nullable|string|max:50',
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

    public function addProductPrice(Request $request, MarketProduct $marketProduct): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        if ($marketProduct->agent_id !== $agent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found in your inventory',
            ], 404);
        }

        $request->validate([
            'measurement_scale' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
        ]);

        // Check if measurement scale already exists for this product
        $existingPrice = $marketProduct->productPrices()
            ->where('measurement_scale', $request->measurement_scale)
            ->first();

        if ($existingPrice) {
            return response()->json([
                'success' => false,
                'message' => 'Measurement scale already exists for this product',
            ], 400);
        }

        $productPrice = $marketProduct->productPrices()->create([
            'measurement_scale' => $request->measurement_scale,
            'price' => $request->price,
            'stock_quantity' => $request->stock_quantity ?? null,
            'is_available' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $productPrice,
        ], 201);
    }

    public function updateProductPrice(Request $request, \App\Models\ProductPrice $productPrice): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        if ($productPrice->marketProduct->agent_id !== $agent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Product price not found in your inventory',
            ], 404);
        }

        $request->validate([
            'price' => 'sometimes|required|numeric|min:0',
            'stock_quantity' => 'sometimes|nullable|integer|min:0',
            'is_available' => 'sometimes|boolean',
        ]);

        $productPrice->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $productPrice,
        ]);
    }

    public function removeProductPrice(\App\Models\ProductPrice $productPrice): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        if ($productPrice->marketProduct->agent_id !== $agent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Product price not found in your inventory',
            ], 404);
        }

        $productPrice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product price removed successfully',
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

    /**
     * Test image upload endpoint for debugging
     */
    public function testImageUpload(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_file' => $request->hasFile('image'),
                    'all_data' => $request->all(),
                    'files' => $request->allFiles(),
                    'content_type' => $request->header('Content-Type'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    private function getCurrentAgent(): Agent
    {
        // Extract agent ID from bearer token
        $token = request()->bearerToken();

        if (!$token) {
            throw new \Exception('No authentication token provided');
        }

        // Parse token format: agent_token_{agent_id}_{timestamp}
        $parts = explode('_', $token);

        if (count($parts) < 3 || $parts[0] !== 'agent' || $parts[1] !== 'token') {
            throw new \Exception('Invalid token format');
        }

        $agentId = $parts[2];

        $agent = Agent::find($agentId);

        if (!$agent) {
            throw new \Exception('Agent not found');
        }

        if (!$agent->is_active || $agent->is_suspended) {
            throw new \Exception('Agent account is suspended or inactive');
        }

        return $agent;
    }
}
