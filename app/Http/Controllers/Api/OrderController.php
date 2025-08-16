<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MarketProduct;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\WhatsappSession; // Added this import

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private PaymentService $paymentService
    ) {}

    /**
     * Search orders by order number
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'order_number' => 'required|string|max:255',
        ]);

        $order = Order::where('order_number', $request->order_number)
            ->with(['market', 'agent'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'customer_name' => $order->customer_name,
                'whatsapp_number' => $order->whatsapp_number,
                'delivery_address' => $order->delivery_address,
                'total_amount' => $order->total_amount,
                'market' => $order->market ? [
                    'id' => $order->market->id,
                    'name' => $order->market->name,
                    'address' => $order->market->address,
                ] : null,
                'agent' => $order->agent ? [
                    'id' => $order->agent->id,
                    'name' => $order->agent->full_name,
                    'phone' => $order->agent->phone,
                ] : null,
                'created_at' => $order->created_at,
            ],
        ]);
    }

    /**
     * Get just the items for an order
     */
    public function getItems($orderId): JsonResponse
    {
        try {
            // Check if it's a temporary WhatsApp order ID
            if (str_starts_with($orderId, 'TEMP_')) {
                // Extract WhatsApp number from temp order ID
                $parts = explode('_', $orderId);
                if (count($parts) >= 3) {
                    $whatsappHash = $parts[2];

                    // Find WhatsApp session by hash
                    $session = WhatsappSession::where('status', 'active')
                        ->get()
                        ->filter(function($session) use ($whatsappHash) {
                            return substr(md5($session->whatsapp_number), 0, 6) === $whatsappHash;
                        })
                        ->first();

                    if ($session && $session->cart_items) {
                        return response()->json([
                            'success' => true,
                            'data' => collect($session->cart_items)->map(function ($item, $index) {
                                return [
                                    'id' => $index + 1,
                                    'product_name' => $item['name'],
                                    'quantity' => $item['quantity'],
                                    'unit_price' => 0, // Will be set when market is selected
                                    'total_price' => 0, // Will be calculated when market is selected
                                    'notes' => $item['notes'] ?? null,
                                    'product' => [
                                        'id' => null,
                                        'name' => $item['name'],
                                        'unit' => 'piece',
                                        'description' => 'Item from WhatsApp cart',
                                    ],
                                ];
                            }),
                            'is_whatsapp_session' => true,
                            'session_id' => $session->session_id,
                        ]);
                    }
                }

                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp session not found',
                ], 404);
            }

            // Try to find real order
            $order = Order::find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

        $items = $order->orderItems()->with('product')->get();

        return response()->json([
            'success' => true,
            'data' => $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'unit' => $item->product->unit,
                        'description' => $item->product->description,
                    ],
                ];
            }),
                'is_whatsapp_session' => false,
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting order items: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving order items',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'whatsapp_number' => 'required|string',
            'customer_name' => 'required|string|max:255',
            'delivery_address' => 'required|string',
            'delivery_latitude' => 'required|numeric|between:-90,90',
            'delivery_longitude' => 'required|numeric|between:-180,180',
            'market_id' => 'required|exists:markets,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $order = $this->orderService->createOrder($request->all());

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function show($orderId): JsonResponse
    {
        try {
            // Check if it's a temporary WhatsApp order ID
            if (str_starts_with($orderId, 'TEMP_')) {
                // Extract WhatsApp number from temp order ID
                $parts = explode('_', $orderId);
                if (count($parts) >= 3) {
                    $whatsappHash = $parts[2];

                    // Find WhatsApp session by hash
                    $session = WhatsappSession::where('status', 'active')
                        ->get()
                        ->filter(function($session) use ($whatsappHash) {
                            return substr(md5($session->whatsapp_number), 0, 6) === $whatsappHash;
                        })
                        ->first();

                    if ($session && $session->cart_items) {
                        return response()->json([
                            'success' => true,
                            'data' => [
                                'id' => $orderId,
                                'order_number' => $orderId,
                                'status' => 'pending',
                                'customer_name' => 'WhatsApp Customer',
                                'delivery_address' => 'To be provided',
                                'delivery_latitude' => null,
                                'delivery_longitude' => null,
                                'subtotal' => 0,
                                'delivery_fee' => 0,
                                'total_amount' => 0,
                                'market' => null,
                                'agent' => null,
                                'items' => collect($session->cart_items)->map(function ($item, $index) {
                                    return [
                                        'id' => $index + 1,
                                        'product_name' => $item['name'],
                                        'quantity' => $item['quantity'],
                                        'unit_price' => 0,
                                        'total_price' => 0,
                                        'notes' => $item['notes'] ?? null,
                                        'product' => [
                                            'id' => null,
                                            'name' => $item['name'],
                                            'unit' => 'piece',
                                        ],
                                    ];
                                }),
                                'created_at' => $session->created_at,
                                'is_whatsapp_session' => true,
                                'session_id' => $session->session_id,
                            ],
                        ]);
                    }
                }

                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp session not found',
                ], 404);
            }

            // Try to find real order
            $order = Order::with(['orderItems.product', 'market', 'agent'])->find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'customer_name' => $order->customer_name,
                'delivery_address' => $order->delivery_address,
                'delivery_latitude' => $order->delivery_latitude,
                'delivery_longitude' => $order->delivery_longitude,
                'subtotal' => $order->subtotal,
                'delivery_fee' => $order->delivery_fee,
                'total_amount' => $order->total_amount,
                'market' => $order->market ? [
                    'id' => $order->market->id,
                    'name' => $order->market->name,
                    'address' => $order->market->address,
                ] : null,
                'agent' => $order->agent ? [
                    'id' => $order->agent->id,
                    'name' => $order->agent->full_name,
                    'phone' => $order->agent->phone,
                ] : null,
                'items' => $order->orderItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'unit' => $item->product->unit,
                        ],
                    ];
                }),
                'created_at' => $order->created_at,
                    'is_whatsapp_session' => false,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting order: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving order',
            ], 500);
        }
    }

    public function getCartPrices(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => 'required|exists:markets,id',
            'items' => 'required|array|min:1',
            'items.*.product_name' => 'required|string|max:255', // Changed from product_id to product_name
            'items.*.measurement_scale' => 'required|string|max:50',
        ]);

        try {
            $marketId = $request->market_id;
            $items = $request->items;
            $pricedItems = [];

            foreach ($items as $item) {
                // Use fuzzy matching to find the market product
                $marketProduct = $this->findProductByFuzzyMatch($marketId, $item['product_name']);

                if (!$marketProduct) {
                    // Product not found - add as unavailable
                    $pricedItems[] = [
                        'product_name' => $item['product_name'],
                        'measurement_scale' => $item['measurement_scale'],
                        'is_available' => false,
                        'availability_status' => 'product_not_found',
                        'message' => "Product '{$item['product_name']}' not found in selected market",
                    ];
                    continue;
                }

                // Find the specific price for the measurement scale
                $productPrice = $marketProduct->productPrices()
                    ->where('measurement_scale', $item['measurement_scale'])
                    ->where('is_available', true)
                    ->first();

                if (!$productPrice) {
                    // Product found but measurement scale not available - add as unavailable
                    $pricedItems[] = [
                        'product_id' => $marketProduct->product_id,
                        'product_name' => $marketProduct->product_name ?? $marketProduct->product->name,
                        'base_product_name' => $marketProduct->product->name,
                        'category' => $marketProduct->product->category->name,
                        'image' => $marketProduct->product->image,
                        'measurement_scale' => $item['measurement_scale'],
                        'is_available' => false,
                        'availability_status' => 'measurement_scale_not_available',
                        'message' => "Measurement scale '{$item['measurement_scale']}' not available for this product",
                        'available_measurement_scales' => $marketProduct->productPrices()
                            ->where('is_available', true)
                            ->pluck('measurement_scale')
                            ->toArray(),
                    ];
                    continue;
                }

                // Product and measurement scale are available
                $pricedItems[] = [
                    'product_id' => $marketProduct->product_id,
                    'product_name' => $marketProduct->product_name ?? $marketProduct->product->name,
                    'base_product_name' => $marketProduct->product->name,
                    'category' => $marketProduct->product->category->name,
                    'image' => $marketProduct->product->image,
                    'measurement_scale' => $item['measurement_scale'],
                    'unit_price' => $productPrice->price,
                    'agent_name' => $marketProduct->agent->full_name,
                    'agent_id' => $marketProduct->agent_id,
                    'stock_available' => $productPrice->stock_quantity,
                    'is_available' => true,
                    'availability_status' => 'available',
                    'matched_product_name' => $marketProduct->product_name ?? $marketProduct->product->name, // Show what was matched
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'market_id' => $marketId,
                    'items' => $pricedItems,
                    'item_count' => count($pricedItems),
                    'available_count' => collect($pricedItems)->where('is_available', true)->count(),
                    'unavailable_count' => collect($pricedItems)->where('is_available', false)->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get prices',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get prices for items in a WhatsApp order by order ID
     */
    public function getWhatsAppOrderPrices(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_id' => 'required|string',
                'market_id' => 'required|exists:markets,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $orderId = $request->order_id;
            $marketId = $request->market_id;

            // Check if it's a temporary WhatsApp order ID
            if (str_starts_with($orderId, 'TEMP_')) {
                // Extract WhatsApp number from temp order ID
                $parts = explode('_', $orderId);
                if (count($parts) >= 3) {
                    $whatsappHash = $parts[2];

                    // Find WhatsApp session by hash
                    $session = WhatsappSession::where('status', 'active')
                        ->get()
                        ->filter(function($session) use ($whatsappHash) {
                            return substr(md5($session->whatsapp_number), 0, 6) === $whatsappHash;
                        })
                        ->first();

                    if ($session && $session->cart_items) {
                        $pricedItems = [];
                        $availableCount = 0;
                        $unavailableCount = 0;

                        foreach ($session->cart_items as $item) {
                            // Use fuzzy matching to find the market product
                            $marketProduct = $this->findProductByFuzzyMatch($marketId, $item['name']);

                            if (!$marketProduct) {
                                // Product not found - add as unavailable
                                $pricedItems[] = [
                                    'product_name' => $item['name'],
                                    'quantity' => $item['quantity'],
                                    'measurement_scale' => $item['quantity'], // Use quantity as measurement scale
                                    'is_available' => false,
                                    'availability_status' => 'product_not_found',
                                    'message' => "Product '{$item['name']}' not found in selected market",
                                ];
                                $unavailableCount++;
                                continue;
                            }

                            // Find the specific price for the measurement scale
                            $productPrice = $marketProduct->productPrices()
                                ->where('measurement_scale', $item['quantity'])
                                ->where('is_available', true)
                                ->first();

                            if (!$productPrice) {
                                // Product found but measurement scale not available - add as unavailable
                                $pricedItems[] = [
                                    'product_id' => $marketProduct->product_id,
                                    'product_name' => $marketProduct->product_name ?? $marketProduct->product->name,
                                    'base_product_name' => $marketProduct->product->name,
                                    'category' => $marketProduct->product->category->name,
                                    'image' => $marketProduct->product->image,
                                    'quantity' => $item['quantity'],
                                    'measurement_scale' => $item['quantity'],
                                    'is_available' => false,
                                    'availability_status' => 'measurement_scale_not_available',
                                    'message' => "Measurement scale '{$item['quantity']}' not available for this product",
                                    'available_measurement_scales' => $marketProduct->productPrices()
                                        ->where('is_available', true)
                                        ->pluck('measurement_scale')
                                        ->toArray(),
                                ];
                                $unavailableCount++;
                                continue;
                            }

                            // Product and measurement scale are available
                            $pricedItems[] = [
                                'product_id' => $marketProduct->product_id,
                                'product_name' => $marketProduct->product_name ?? $marketProduct->product->name,
                                'base_product_name' => $marketProduct->product->name,
                                'category' => $marketProduct->product->category->name,
                                'image' => $marketProduct->product->image,
                                'quantity' => $item['quantity'],
                                'measurement_scale' => $item['quantity'],
                                'unit_price' => $productPrice->price,
                                'agent_name' => $marketProduct->agent->full_name,
                                'agent_id' => $marketProduct->agent_id,
                                'stock_available' => $productPrice->stock_quantity,
                                'is_available' => true,
                                'availability_status' => 'available',
                                'matched_product_name' => $marketProduct->product_name ?? $marketProduct->product->name,
                            ];
                            $availableCount++;
                        }

                        return response()->json([
                            'success' => true,
                            'data' => [
                                'order_id' => $orderId,
                                'market_id' => $marketId,
                                'items' => $pricedItems,
                                'available_count' => $availableCount,
                                'unavailable_count' => $unavailableCount,
                                'is_whatsapp_session' => true,
                                'session_id' => $session->session_id,
                            ],
                        ]);
                    }
                }

                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp session not found',
                ], 404);
            }

            // Try to find real order
            $order = Order::with(['orderItems.product', 'market', 'agent'])->find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            // For real orders, return the existing items with their prices
            $pricedItems = $order->orderItems->map(function ($item) {
                return [
                    'product_id' => $item->product->id,
                    'product_name' => $item->product_name,
                    'base_product_name' => $item->product->name,
                    'category' => $item->product->category->name,
                    'image' => $item->product->image,
                    'quantity' => $item->quantity,
                    'measurement_scale' => $item->measurement_scale,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'is_available' => true,
                    'availability_status' => 'available',
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $orderId,
                    'market_id' => $order->market_id,
                    'items' => $pricedItems,
                    'available_count' => $pricedItems->count(),
                    'unavailable_count' => 0,
                    'is_whatsapp_session' => false,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting WhatsApp order prices: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get order prices',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find product by fuzzy matching with 80% similarity threshold
     */
    private function findProductByFuzzyMatch(int $marketId, string $searchProductName): ?MarketProduct
    {
        // Get all market products for this market
        $marketProducts = MarketProduct::with(['product.category', 'productPrices', 'agent'])
            ->where('market_id', $marketId)
            ->where('is_available', true)
            ->get();

        $bestMatch = null;
        $bestSimilarity = 0;
        $threshold = 0.7; // Lowered from 0.8 to 0.7 for better matching

        // Debug: Log the search
        Log::info('Fuzzy matching search:', [
            'search_product_name' => $searchProductName,
            'market_id' => $marketId,
            'available_products_count' => $marketProducts->count(),
        ]);

        foreach ($marketProducts as $marketProduct) {
            // Check both product_name (custom name) and base product name
            $productNames = [
                $marketProduct->product_name,
                $marketProduct->product->name
            ];

            foreach ($productNames as $productName) {
                if (!$productName) continue;

                $similarity = $this->calculateSimilarity($searchProductName, $productName);

                // Debug: Log each comparison
                Log::info('Product comparison:', [
                    'search' => $searchProductName,
                    'database' => $productName,
                    'similarity' => $similarity,
                    'threshold' => $threshold,
                    'is_match' => $similarity >= $threshold,
                ]);

                if ($similarity > $bestSimilarity && $similarity >= $threshold) {
                    $bestSimilarity = $similarity;
                    $bestMatch = $marketProduct;
                }
            }
        }

        // Debug: Log the result
        Log::info('Fuzzy matching result:', [
            'search_product_name' => $searchProductName,
            'best_match' => $bestMatch ? ($bestMatch->product_name ?? $bestMatch->product->name) : 'none',
            'best_similarity' => $bestSimilarity,
        ]);

        return $bestMatch;
    }

    /**
     * Calculate similarity between two strings using multiple algorithms
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        // Normalize strings for comparison
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));

        // If exact match, return 1.0
        if ($str1 === $str2) {
            return 1.0;
        }

        // Check if one string contains the other (for cases like "Add rice" vs "rice")
        if (str_contains($str1, $str2) || str_contains($str2, $str1)) {
            $shorter = strlen($str1) < strlen($str2) ? $str1 : $str2;
            $longer = strlen($str1) >= strlen($str2) ? $str1 : $str2;

            // If the shorter string is at least 3 characters and represents at least 50% of the longer string
            if (strlen($shorter) >= 3 && (strlen($shorter) / strlen($longer)) >= 0.5) {
                return 0.95; // Very high similarity for contained matches
            }
        }

        // Check for word-level containment (e.g., "Add rice" contains word "rice")
        $words1 = preg_split('/\s+/', $str1);
        $words2 = preg_split('/\s+/', $str2);

        $commonWords = array_intersect($words1, $words2);
        if (!empty($commonWords)) {
            $longestCommonWord = max(array_map('strlen', $commonWords));
            if ($longestCommonWord >= 3) {
                return 0.9; // High similarity for word matches
            }
        }

        // Calculate different similarity metrics
        $levenshtein = levenshtein($str1, $str2);
        $maxLength = max(strlen($str1), strlen($str2));
        $levenshteinSimilarity = $maxLength > 0 ? (1 - ($levenshtein / $maxLength)) : 0;

        // Calculate Jaro-Winkler similarity
        $jaroSimilarity = $this->jaroWinklerSimilarity($str1, $str2);

        // Calculate substring similarity
        $substringSimilarity = $this->substringSimilarity($str1, $str2);

        // Weighted average of different similarity measures
        $weightedSimilarity = ($levenshteinSimilarity * 0.4) + ($jaroSimilarity * 0.4) + ($substringSimilarity * 0.2);

        return $weightedSimilarity;
    }

    /**
     * Calculate Jaro-Winkler similarity
     */
    private function jaroWinklerSimilarity(string $str1, string $str2): float
    {
        if (empty($str1) || empty($str2)) {
            return 0.0;
        }

        $matchWindow = (int) (max(strlen($str1), strlen($str2)) / 2) - 1;
        if ($matchWindow < 0) $matchWindow = 0;

        $str1Matches = array_fill(0, strlen($str1), false);
        $str2Matches = array_fill(0, strlen($str2), false);

        $matches = 0;
        $transpositions = 0;

        // Find matches
        for ($i = 0; $i < strlen($str1); $i++) {
            $start = max(0, $i - $matchWindow);
            $end = min(strlen($str2), $i + $matchWindow + 1);

            for ($j = $start; $j < $end; $j++) {
                if ($str2Matches[$j] || $str1[$i] !== $str2[$j]) {
                    continue;
                }
                $str1Matches[$i] = true;
                $str2Matches[$j] = true;
                $matches++;
                break;
            }
        }

        if ($matches === 0) {
            return 0.0;
        }

        // Find transpositions
        $k = 0;
        for ($i = 0; $i < strlen($str1); $i++) {
            if (!$str1Matches[$i]) {
                continue;
            }
            while (!$str2Matches[$k]) {
                $k++;
            }
            if ($str1[$i] !== $str2[$k]) {
                $transpositions++;
            }
            $k++;
        }

        $jaro = (($matches / strlen($str1)) + ($matches / strlen($str2)) + (($matches - $transpositions / 2) / $matches)) / 3;

        // Winkler modification
        $prefix = 0;
        $maxPrefix = min(4, min(strlen($str1), strlen($str2)));
        for ($i = 0; $i < $maxPrefix; $i++) {
            if ($str1[$i] === $str2[$i]) {
                $prefix++;
            } else {
                break;
            }
        }

        return $jaro + (0.1 * $prefix * (1 - $jaro));
    }

    /**
     * Calculate substring similarity
     */
    private function substringSimilarity(string $str1, string $str2): float
    {
        $words1 = preg_split('/\s+/', $str1);
        $words2 = preg_split('/\s+/', $str2);

        $commonWords = array_intersect($words1, $words2);
        $totalWords = array_unique(array_merge($words1, $words2));

        if (empty($totalWords)) {
            return 0.0;
        }

        return count($commonWords) / count($totalWords);
    }

    /**
     * Get all measurement scales and prices for products in a market
     */
    public function getMarketProductPrices(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'market_id' => 'required|exists:markets,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $marketProducts = MarketProduct::with(['product.category', 'productPrices', 'agent'])
                ->where('market_id', $request->market_id)
                ->where('is_available', true)
                ->get();

            $products = [];
            foreach ($marketProducts as $marketProduct) {
                $prices = [];
                foreach ($marketProduct->productPrices as $price) {
                    if ($price->is_available) {
                        $prices[] = [
                            'measurement_scale' => $price->measurement_scale,
                            'price' => $price->price,
                            'stock_quantity' => $price->stock_quantity,
                            'is_available' => $price->is_available,
                        ];
                    }
                }

                if (!empty($prices)) {
                    $products[] = [
                        'product_id' => $marketProduct->product_id,
                        'product_name' => $marketProduct->product_name ?? $marketProduct->product->name,
                        'base_product_name' => $marketProduct->product->name,
                        'category' => $marketProduct->product->category->name,
                        'image' => $marketProduct->product->image,
                        'agent_name' => $marketProduct->agent->full_name,
                        'agent_id' => $marketProduct->agent_id,
                        'prices' => $prices,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'market_id' => $request->market_id,
                    'products' => $products,
                    'product_count' => count($products),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getMarketProductPrices: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get market product prices',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateItems(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_name' => 'required|string|max:255', // Changed from product_id to product_name
            'items.*.quantity' => 'required|numeric|min:0.01', // Allow decimal quantities
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.measurement_scale' => 'required|string|max:50', // Add measurement scale
        ]);

        try {
            // Clear existing items
            $order->orderItems()->delete();

            // Add new items
            $subtotal = 0;
            $availableItems = [];
            $unavailableItems = [];

            foreach ($request->items as $item) {
                // Use fuzzy matching to find the market product
                $marketProduct = $this->findProductByFuzzyMatch($order->market_id, $item['product_name']);

                if (!$marketProduct) {
                    // Product not found - add to unavailable items
                    $unavailableItems[] = [
                        'product_name' => $item['product_name'],
                        'measurement_scale' => $item['measurement_scale'],
                        'reason' => 'product_not_found',
                        'message' => "Product '{$item['product_name']}' not found in market",
                    ];
                    continue;
                }

                // Check if the measurement scale is available
                $productPrice = $marketProduct->productPrices()
                    ->where('measurement_scale', $item['measurement_scale'])
                    ->where('is_available', true)
                    ->first();

                if (!$productPrice) {
                    // Product found but measurement scale not available
                    $unavailableItems[] = [
                        'product_name' => $marketProduct->product_name ?? $marketProduct->product->name,
                        'measurement_scale' => $item['measurement_scale'],
                        'reason' => 'measurement_scale_not_available',
                        'message' => "Measurement scale '{$item['measurement_scale']}' not available for this product",
                        'available_measurement_scales' => $marketProduct->productPrices()
                            ->where('is_available', true)
                            ->pluck('measurement_scale')
                            ->toArray(),
                    ];
                    continue;
                }

                // Product and measurement scale are available - add to order
                $orderItem = $order->orderItems()->create([
                    'product_id' => $marketProduct->product_id,
                    'product_name' => $marketProduct->product_name ?? $marketProduct->product->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'measurement_scale' => $item['measurement_scale'], // Store measurement scale
                    'total_price' => $item['quantity'] * $item['unit_price'],
                ]);

                $subtotal += $orderItem->total_price;
                $availableItems[] = $orderItem;
            }

            // Update order totals
            $deliveryFee = 500; // Fixed delivery fee
            $totalAmount = $subtotal + $deliveryFee;

            $order->update([
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $totalAmount,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'total_amount' => $totalAmount,
                    'available_items_count' => count($availableItems),
                    'unavailable_items_count' => count($unavailableItems),
                    'available_items' => $availableItems,
                    'unavailable_items' => $unavailableItems,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function checkout(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            // Initialize payment with Paystack
            $paymentData = $this->paymentService->initializePayment([
                'order_id' => $order->id,
                'amount' => $order->total_amount * 100, // Convert to kobo
                'email' => $request->email,
                'reference' => 'FS_' . $order->order_number,
                'callback_url' => config('app.frontend_url') . '/payment/callback',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'payment_url' => $paymentData['authorization_url'],
                    'reference' => $paymentData['reference'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready_for_delivery,out_for_delivery,delivered,cancelled',
            'message' => 'nullable|string',
        ]);

        try {
            $order->updateStatus($request->status, $request->message ?? '');

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'updated_at' => $order->updated_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get order status and send WhatsApp notification
     */
    public function getOrderStatus(Request $request, string $orderNumber): JsonResponse
    {
        $request->validate([
            'whatsapp_number' => 'required|string',
        ]);

        $order = Order::where('order_number', $orderNumber)
            ->where('whatsapp_number', $request->whatsapp_number)
            ->with(['market', 'agent'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        // Send WhatsApp notification about status
        try {
            $this->sendWhatsAppStatusUpdate($order);
        } catch (\Exception $e) {
            \Log::error('Failed to send WhatsApp status update: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'customer_name' => $order->customer_name,
                'whatsapp_number' => $order->whatsapp_number,
                'delivery_address' => $order->delivery_address,
                'total_amount' => $order->total_amount,
                'market' => $order->market ? [
                    'id' => $order->market->id,
                    'name' => $order->market->name,
                    'address' => $order->market->address,
                ] : null,
                'agent' => $order->agent ? [
                    'id' => $order->agent->id,
                    'name' => $order->agent->full_name,
                    'phone' => $order->agent->phone,
                ] : null,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ],
        ]);
    }

    /**
     * Send WhatsApp status update
     */
    private function sendWhatsAppStatusUpdate(Order $order): void
    {
        $whatsappBotUrl = env('WHATSAPP_BOT_URL', 'https://foodstuff-whatsapp-bot-6536aa3f6997.herokuapp.com');

        $statusMessages = [
            'pending' => 'Your order is being processed.',
            'confirmed' => 'Your order has been confirmed!',
            'paid' => 'Payment received! Your order is being prepared.',
            'assigned' => 'An agent has been assigned to your order.',
            'preparing' => 'Your order is being prepared in the kitchen.',
            'ready_for_delivery' => 'Your order is ready for delivery!',
            'out_for_delivery' => 'Your order is on its way to you!',
            'delivered' => 'Your order has been delivered! Enjoy your meal!',
            'cancelled' => 'Your order has been cancelled.',
            'failed' => 'There was an issue with your order.',
            'completed' => 'Your order has been completed successfully!',
        ];

        $message = $statusMessages[$order->status] ?? 'Your order status has been updated.';

        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'message' => $message,
            'whatsapp_number' => $order->whatsapp_number,
        ];

        // Send to WhatsApp bot
        try {
            $response = \Http::post($whatsappBotUrl . '/order-status-update', $data);

            if ($response->successful()) {
                \Log::info('WhatsApp status update sent successfully', [
                    'order_id' => $order->id,
                    'status' => $order->status,
                ]);
            } else {
                \Log::error('Failed to send WhatsApp status update', [
                    'order_id' => $order->id,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error sending WhatsApp status update: ' . $e->getMessage());
        }
    }
}
