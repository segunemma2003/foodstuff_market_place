<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use App\Services\OrderService;
use App\Models\WhatsappSession;
use App\Models\Order;
use App\Models\Market;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WhatsAppController extends Controller
{
    public function __construct(
        private WhatsAppService $whatsAppService,
        private OrderService $orderService,
        private \App\Services\DeliveryService $deliveryService
    ) {}

    public function webhook(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            Log::info('WhatsApp webhook received', $data);

            // Handle Termii webhook
            if (isset($data['event']) && $data['event'] === 'message') {
                $phone = $data['data']['from'] ?? null;
                $message = $data['data']['text'] ?? null;
                $messageId = $data['data']['message_id'] ?? null;

                if ($phone && $message) {
                    $this->processIncomingMessage($phone, $message, $messageId);
                }
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook error: ' . $e->getMessage());
            return response()->json(['success' => false], 500);
        }
    }

    public function processMessage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'from' => 'required|string',
                'body' => 'required|string',
            ]);

            $phone = $request->from;
            $message = $request->body;

            $response = $this->handleMessage($phone, $message);

            return response()->json([
                'success' => true,
                'reply' => $response,
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing message: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'reply' => 'Sorry, I encountered an error. Please try again.',
            ], 500);
        }
    }

    public function sendMessage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'phone' => 'required|string',
                'message' => 'required|string',
            ]);

            $success = $this->whatsAppService->sendMessage(
                $request->phone,
                $request->message
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Message sent successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function createSection(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'whatsapp_number' => 'required|string',
            ]);

            $session = WhatsappSession::updateOrCreate(
                ['whatsapp_number' => $request->whatsapp_number, 'status' => 'active'],
                [
                    'session_id' => uniqid('session_'),
                    'section_id' => 'SEC_' . time() . '_' . substr(md5($request->whatsapp_number), 0, 6),
                    'status' => 'active',
                    'current_step' => 'greeting',
                    'last_activity' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'section_id' => $session->section_id,
                'message' => 'Section created successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating section: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating section',
            ], 500);
        }
    }

    public function confirmSection(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $request->validate([
                'section_id' => 'required|string',
            ]);

            $session = WhatsappSession::where('section_id', $request->section_id)->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found',
                ], 404);
            }

            // Optimized customer name retrieval with single query
            $customerName = Order::where('whatsapp_number', $session->whatsapp_number)
                ->whereNotNull('customer_name')
                ->orderBy('created_at', 'desc')
                ->value('customer_name');

            $response = [
                'success' => true,
                'section' => [
                    'section_id' => $session->section_id,
                    'status' => $session->status,
                    'whatsapp_number' => $session->whatsapp_number,
                    'customer_name' => $customerName,
                    'delivery_address' => $session->delivery_address,
                    'delivery_latitude' => $session->delivery_latitude,
                    'delivery_longitude' => $session->delivery_longitude,
                    'selected_market_id' => $session->selected_market_id,
                    'order_id' => $session->order_id,
                    'created_at' => $session->created_at,
                    'last_activity' => $session->last_activity,
                ],
            ];

            $executionTime = (microtime(true) - $startTime) * 1000;
            Log::info('confirmSection performance', [
                'execution_time_ms' => round($executionTime, 2),
                'section_id' => $request->section_id
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;
            Log::error('Error confirming section', [
                'error' => $e->getMessage(),
                'execution_time_ms' => round($executionTime, 2),
                'section_id' => $request->section_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error confirming section',
            ], 500);
        }
    }

    public function getSectionStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'section_id' => 'required|string',
            ]);

            $session = WhatsappSession::where('section_id', $request->section_id)->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found',
                ], 404);
            }

            // Get customer name from previous orders
            $customerName = null;
            $previousOrder = Order::where('whatsapp_number', $session->whatsapp_number)
                ->whereNotNull('customer_name')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($previousOrder) {
                $customerName = $previousOrder->customer_name;
            }

            return response()->json([
                'success' => true,
                'section' => [
                    'section_id' => $session->section_id,
                    'status' => $session->status,
                    'whatsapp_number' => $session->whatsapp_number,
                    'customer_name' => $customerName,  // Will be null for new customers
                    'delivery_address' => $session->delivery_address,
                    'delivery_latitude' => $session->delivery_latitude,
                    'delivery_longitude' => $session->delivery_longitude,
                    'selected_market_id' => $session->selected_market_id,
                    'order_id' => $session->order_id,
                    'created_at' => $session->created_at,
                    'last_activity' => $session->last_activity,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting section status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving section status',
            ], 500);
        }
    }

    public function getNearbyMarkets(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'section_id' => 'required|string',
                'search' => 'nullable|string',
            ]);

            $session = WhatsappSession::where('section_id', $request->section_id)->first();
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found',
                ], 404);
            }

            // Update session with delivery location
            $session->update([
                'delivery_latitude' => $request->latitude,
                'delivery_longitude' => $request->longitude,
                'last_activity' => now(),
            ]);

            // Get nearby markets using delivery service with search
            $nearbyMarkets = $this->deliveryService->getNearbyMarkets(
                $request->latitude,
                $request->longitude,
                30,
                $request->search
            );

            return response()->json([
                'success' => true,
                'markets' => $nearbyMarkets,
                'total' => count($nearbyMarkets),
                'search_term' => $request->search,
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting nearby markets: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving nearby markets',
            ], 500);
        }
    }

    public function searchMarkets(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'search' => 'required|string|min:2',
                'section_id' => 'required|string',
            ]);

            $session = WhatsappSession::where('section_id', $request->section_id)->first();
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found',
                ], 404);
            }

            // Search markets by name or address
            $markets = \App\Models\Market::where('is_active', true)
                ->where(function($query) use ($request) {
                    $query->where('name', 'like', '%' . $request->search . '%')
                          ->orWhere('address', 'like', '%' . $request->search . '%');
                })
                ->get();

            $formattedMarkets = [];
            foreach ($markets as $market) {
                // Calculate distance if user has location
                $distance = null;
                $deliveryAmount = null;
                $deliveryTime = null;

                if ($session->delivery_latitude && $session->delivery_longitude) {
                    $distance = $this->deliveryService->calculateDistance(
                        $session->delivery_latitude,
                        $session->delivery_longitude,
                        $market->latitude,
                        $market->longitude
                    );

                    if ($distance <= 30) {
                        $deliveryAmount = $this->deliveryService->calculateDeliveryFee($distance);
                        $deliveryTime = $this->deliveryService->calculateDeliveryTime($distance);
                    }
                }

                $formattedMarkets[] = [
                    'id' => $market->id,
                    'name' => $market->name,
                    'address' => $market->address,
                    'distance' => $distance ? round($distance, 2) : null,
                    'delivery_amount' => $deliveryAmount,
                    'delivery_time' => $deliveryTime,
                    'latitude' => $market->latitude,
                    'longitude' => $market->longitude,
                    'is_within_range' => $distance ? ($distance <= 30) : false,
                ];
            }

            // Sort by distance if available, otherwise by name
            usort($formattedMarkets, function($a, $b) {
                if ($a['distance'] !== null && $b['distance'] !== null) {
                    return $a['distance'] <=> $b['distance'];
                }
                return strcasecmp($a['name'], $b['name']);
            });

            return response()->json([
                'success' => true,
                'markets' => $formattedMarkets,
                'total' => count($formattedMarkets),
                'search_term' => $request->search,
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching markets: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error searching markets',
            ], 500);
        }
    }

    public function getMarketProducts(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'market_id' => 'required|integer',
                'section_id' => 'required|string',
                'search' => 'nullable|string',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);

            $session = WhatsappSession::where('section_id', $request->section_id)->first();
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found',
                ], 404);
            }

            // Update session with selected market
            $session->update([
                'selected_market_id' => $request->market_id,
                'last_activity' => now(),
            ]);

            $perPage = $request->per_page ?? 20;
            $page = $request->page ?? 1;

            // Create cache key based on parameters
            $cacheKey = "market_products_{$request->market_id}_" .
                       ($request->search ? md5($request->search) : 'all') .
                       "_{$page}_{$perPage}";

            // Cache for 5 minutes for frequently accessed data
            $products = Cache::remember($cacheKey, 300, function () use ($request, $perPage, $page) {
                $query = \App\Models\MarketProduct::select([
                    'id', 'product_id', 'product_name', 'is_available'
                ])
                ->with([
                    'product:id,category_id,name,description,image,unit',
                    'product.category:id,name',
                    'prices:id,market_product_id,measurement_scale,price,unit,is_available'
                ])
                ->where('market_id', $request->market_id)
                ->where('is_available', true);

                if ($request->search) {
                    $query->whereHas('product', function($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->search . '%');
                    });
                }

                return $query->paginate($perPage, ['*'], 'page', $page);
            });

            $formattedProducts = $products->map(function($marketProduct) {
                // Add null checks to prevent errors
                $product = $marketProduct->product;
                $category = $product ? $product->category : null;

                return [
                    'id' => $marketProduct->id,
                    'product_id' => $marketProduct->product_id,
                    'name' => $product ? $product->name : ($marketProduct->product_name ?? 'Unknown Product'),
                    'description' => $product ? $product->description : null,
                    'image' => $product ? $product->image : null,
                    'category' => $category ? $category->name : null,
                    'prices' => $marketProduct->prices ? $marketProduct->prices->map(function($price) {
                        return [
                            'id' => $price->id,
                            'measurement_scale' => $price->measurement_scale,
                            'price' => $price->price,
                            'unit' => $price->unit,
                        ];
                    }) : [],
                    'is_available' => $marketProduct->is_available,
                    'stock_quantity' => $marketProduct->stock_quantity,
                ];
            });

            return response()->json([
                'success' => true,
                'products' => $formattedProducts,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting market products: ' . $e->getMessage(), [
                'market_id' => $request->market_id,
                'section_id' => $request->section_id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving products: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function createOrder(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'section_id' => 'required|string',
                'items' => 'required|array',
                'items.*.product_id' => 'required|integer',
                'items.*.quantity' => 'required|numeric|min:0.1',
                'items.*.measurement_scale' => 'required|string',
                'items.*.unit_price' => 'required|numeric|min:0',
                'customer_name' => 'nullable|string',
                'customer_phone' => 'required|string',
                'subtotal' => 'required|numeric|min:0',
                'delivery_fee' => 'required|numeric|min:0',
                'service_charge' => 'required|numeric|min:0',
                'total_amount' => 'required|numeric|min:0',
            ]);

            return DB::transaction(function () use ($request) {
                $session = WhatsappSession::where('section_id', $request->section_id)->first();
                if (!$session) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Section not found',
                    ], 404);
                }

                // Optimized customer name retrieval
                $customerName = $request->customer_name;
                if (!$customerName) {
                    $customerName = Order::where('whatsapp_number', $session->whatsapp_number)
                        ->whereNotNull('customer_name')
                        ->orderBy('created_at', 'desc')
                        ->value('customer_name');

                    if (!$customerName) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Customer name is required for new customers',
                        ], 400);
                    }
                }

                // Create order with optimized fields
                $order = Order::create([
                    'order_number' => 'FS' . date('Ymd') . Str::random(6),
                    'whatsapp_number' => $session->whatsapp_number,
                    'customer_name' => $customerName,
                    'customer_phone' => $request->customer_phone,
                    'delivery_address' => $session->delivery_address,
                    'delivery_latitude' => $session->delivery_latitude,
                    'delivery_longitude' => $session->delivery_longitude,
                    'market_id' => $session->selected_market_id,
                    'subtotal' => $request->subtotal,
                    'delivery_fee' => $request->delivery_fee,
                    'service_charge' => $request->service_charge,
                    'total_amount' => $request->total_amount,
                    'status' => 'pending',
                ]);

                // Bulk insert order items for better performance
                $orderItems = [];
                foreach ($request->items as $item) {
                    $orderItems[] = [
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'] ?? 'Product',
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'measurement_scale' => $item['measurement_scale'],
                        'total_price' => $item['unit_price'] * $item['quantity'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                \App\Models\OrderItem::insert($orderItems);

                // Update session - keep status active, order_id will be linked after payment
                $session->update([
                    'last_activity' => now(),
                ]);

                // Generate payment URL
                $paymentUrl = $this->generatePaymentUrl($order, $session->section_id);

                return response()->json([
                    'success' => true,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_url' => $paymentUrl,
                    'message' => 'Order created successfully',
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error creating order: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating order',
            ], 500);
        }
    }

    public function getOrderStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'section_id' => 'required|string',
            ]);

            $session = WhatsappSession::where('section_id', $request->section_id)
                ->with(['order', 'market'])
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found',
                ], 404);
            }

            if (!$session->order) {
                return response()->json([
                    'success' => false,
                    'message' => 'No order found for this section',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'order' => [
                    'id' => $session->order->id,
                    'order_number' => $session->order->order_number,
                    'status' => $session->order->status,
                    'customer_name' => $session->order->customer_name,
                    'delivery_address' => $session->order->delivery_address,
                    'market_name' => $session->market->name ?? 'Unknown Market',
                    'subtotal' => $session->order->subtotal,
                    'delivery_fee' => $session->order->delivery_fee,
                    'total_amount' => $session->order->total_amount,
                    'created_at' => $session->order->created_at,
                    'updated_at' => $session->order->updated_at,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting order status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving order status',
            ], 500);
        }
    }

    public function getUserOrders(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'whatsapp_number' => 'required|string',
            ]);

            $sessions = WhatsappSession::where('whatsapp_number', $request->whatsapp_number)
                ->whereNotNull('order_id')
                ->where('status', '!=', 'completed')
                ->with(['order', 'market'])
                ->get();

            $orders = $sessions->map(function($session) {
                return [
                    'section_id' => $session->section_id,
                    'order_number' => $session->order->order_number,
                    'status' => $session->order->status,
                    'market_name' => $session->market->name ?? 'Unknown Market',
                    'total_amount' => $session->order->total_amount,
                    'created_at' => $session->order->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'orders' => $orders,
                'total' => $orders->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting user orders: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving orders',
            ], 500);
        }
    }

    private function processIncomingMessage(string $phone, string $message, ?string $messageId = null): void
    {
        try {
            $response = $this->handleMessage($phone, $message);

            if ($response) {
                $this->whatsAppService->sendMessage($phone, $response);
            }
        } catch (\Exception $e) {
            Log::error('Error processing message: ' . $e->getMessage());
        }
    }

    private function handleMessage(string $phone, string $message): ?string
    {
        $message = strtolower(trim($message));
        $session = $this->getOrCreateSession($phone);

        // Check for greeting/introduction
        if (in_array($message, ['hi', 'hello', 'hey', 'start', 'menu'])) {
            return $this->handleGreeting($session);
        }

        // Check for choice selection (for users with previous orders)
        if ($session->current_step === 'choice_selection') {
            return $this->handleChoiceSelection($session, $message);
        }

        // Check for affirmative responses (for new users)
        if (in_array($message, ['yes', 'yeah', 'yep', 'ok', 'okay', 'sure', 'go ahead', 'proceed', 'continue'])) {
            return $this->handleAffirmative($session);
        }

        // Check for order tracking
        if (str_contains($message, 'track') || str_contains($message, 'order') || str_contains($message, 'status')) {
            if (str_contains($message, 'track all') || str_contains($message, 'all orders')) {
                return $this->handleTrackAll($session);
            }
            return $this->handleOrderTracking($session, $message);
        }

        // Check for help
        if (in_array($message, ['help', 'commands', 'what can you do'])) {
            return $this->handleHelp($session);
        }

        // Default response for unrecognized messages
        return $this->handleUnrecognized($session, $message);
    }

    private function getOrCreateSession(string $phone): WhatsappSession
    {
        $session = WhatsappSession::where('whatsapp_number', $phone)
            ->where('status', 'active')
            ->first();

        if (!$session) {
            $session = WhatsappSession::create([
                'whatsapp_number' => $phone,
                'session_id' => uniqid('session_'),
                'status' => 'active',
                'current_step' => 'greeting',
                'last_activity' => now(),
            ]);
        } else {
            $session->update(['last_activity' => now()]);
        }

        return $session;
    }

    private function handleGreeting(WhatsappSession $session): string
    {
        // Check if user has previous orders
        $previousOrders = WhatsappSession::where('whatsapp_number', $session->whatsapp_number)
            ->whereNotNull('order_id')
            ->where('status', '!=', 'completed')
            ->count();

        if ($previousOrders > 0) {
            $session->update(['current_step' => 'choice_selection']);

            return "🛒 *Welcome back to FoodStuff Store!* 🛒\n\n" .
                   "I see you have previous orders. What would you like to do?\n\n" .
                   "📝 *Please select an option:*\n" .
                   "1️⃣ *Make a new order* - Start shopping for new items\n" .
                   "2️⃣ *Track previous orders* - Check status of your existing orders\n\n" .
                   "Reply with:\n" .
                   "• '1' or 'new order' for option 1\n" .
                   "• '2' or 'track orders' for option 2";
        }

        // New user - no previous orders
        $session->update(['current_step' => 'greeting']);

        return "🛒 *Welcome to FoodStuff Store!* 🛒\n\n" .
               "I'm your personal shopping assistant for all your foodstuff needs.\n\n" .
               "🍚 *What we offer:*\n" .
               "• Fresh groceries and food items\n" .
               "• Fast delivery to your doorstep\n" .
               "• Competitive prices from local markets\n" .
               "• Secure payment options\n\n" .
               "Would you like to make use of our platform?\n\n" .
               "Reply with 'yes', 'go ahead', or any affirmative word to start shopping! 🚀";
    }

    private function handleChoiceSelection(WhatsappSession $session, string $message): string
    {
        $message = strtolower(trim($message));

        if (in_array($message, ['1', 'new order'])) {
            // Generate section ID
            $sectionId = $session->generateSectionId();
            $session->update([
                'section_id' => $sectionId,
                'status' => 'ongoing',
                'current_step' => 'section_created',
            ]);

            $frontendUrl = config('app.frontend_url', 'https://marketplace.foodstuff.store');

            return "🎉 *Great! Let's get you started!* 🎉\n\n" .
                   "I've created a shopping session for you.\n\n" .
                   "🔗 *Click the link below to start shopping:*\n" .
                   "{$frontendUrl}?section_id={$sectionId}\n\n" .
                   "📱 *What happens next:*\n" .
                   "1. Enter your delivery address\n" .
                   "2. Choose from nearby markets\n" .
                   "3. Browse and add products to cart\n" .
                   "4. Complete your payment\n\n" .
                   "Need help? Just type 'help' anytime! 🛒✨";
        } elseif (in_array($message, ['2', 'track orders'])) {
            return $this->handleOrderTracking($session, $message);
        } else {
            return "❌ *Invalid choice*\n\n" .
                   "Please select one of the following options:\n\n" .
                   "📝 *Available options:*\n" .
                   "1️⃣ *Make a new order* - Start shopping for new items\n" .
                   "2️⃣ *Track previous orders* - Check status of your existing orders\n\n" .
                   "Reply with:\n" .
                   "• '1' or 'new order' for option 1\n" .
                   "• '2' or 'track orders' for option 2\n\n" .
                   "Please try again with the correct option number.";
        }
    }

    private function handleAffirmative(WhatsappSession $session): string
    {
        // Generate section ID
        $sectionId = $session->generateSectionId();
        $session->update([
            'section_id' => $sectionId,
            'status' => 'ongoing',
            'current_step' => 'section_created',
        ]);

        $frontendUrl = config('app.frontend_url', 'https://marketplace.foodstuff.store');

        return "🎉 *Great! Let's get you started!* 🎉\n\n" .
               "I've created a shopping session for you.\n\n" .
               "🔗 *Click the link below to start shopping:*\n" .
               "{$frontendUrl}?section_id={$sectionId}\n\n" .
               "📱 *What happens next:*\n" .
               "1. Enter your delivery address\n" .
               "2. Choose from nearby markets\n" .
               "3. Browse and add products to cart\n" .
               "4. Complete your payment\n\n" .
               "Need help? Just type 'help' anytime! 🛒✨";
    }

    private function handleOrderTracking(WhatsappSession $session, string $message): string
    {
        // Get user's active orders
        $activeOrders = WhatsappSession::where('whatsapp_number', $session->whatsapp_number)
            ->whereNotNull('order_id')
            ->where('status', '!=', 'completed')
            ->with(['order', 'market'])
            ->get();

        if ($activeOrders->isEmpty()) {
            return "📋 *Order Tracking*\n\n" .
                   "You don't have any active orders at the moment.\n\n" .
                   "To place a new order, just say 'hi' or 'hello' to get started! 🛒";
        }

        if ($activeOrders->count() === 1) {
            $order = $activeOrders->first();
            $trackUrl = config('app.frontend_url', 'https://marketplace.foodstuff.store') .
                       "/track_order?section_id={$order->section_id}";

            return "📋 *Order Found!*\n\n" .
                   "📦 *Order:* {$order->order->order_number}\n" .
                   "🏪 *Market:* {$order->market->name}\n" .
                   "📊 *Status:* " . ucfirst($order->order->status) . "\n" .
                   "💰 *Amount:* ₦" . number_format($order->order->total_amount, 2) . "\n\n" .
                   "🔗 *Track your order:*\n" .
                   "{$trackUrl}\n\n" .
                   "I'll also send you updates via WhatsApp! 📱";
        }

        // Multiple orders
        $orderList = "📋 *Your Active Orders*\n\n";
        foreach ($activeOrders as $index => $order) {
            $orderList .= ($index + 1) . ". *{$order->order->order_number}*\n";
            $orderList .= "   Status: " . ucfirst($order->order->status) . "\n";
            $orderList .= "   Market: {$order->market->name}\n";
            $orderList .= "   Amount: ₦" . number_format($order->order->total_amount, 2) . "\n\n";
        }

        $orderList .= "To track a specific order, reply with the order number (e.g., 'FS20240816001') or say 'track all' to get individual tracking links.";

        return $orderList;
    }

    private function handleTrackAll(WhatsappSession $session): string
    {
        $activeOrders = WhatsappSession::where('whatsapp_number', $session->whatsapp_number)
            ->whereNotNull('order_id')
            ->where('status', '!=', 'completed')
            ->with(['order', 'market'])
            ->get();

        if ($activeOrders->isEmpty()) {
            return "📋 *Order Tracking*\n\n" .
                   "You don't have any active orders at the moment.\n\n" .
                   "To place a new order, just say 'hi' or 'hello' to get started! 🛒";
        }

        $orderList = "📋 *Your Active Orders - Tracking Links*\n\n";
        foreach ($activeOrders as $index => $order) {
            $trackUrl = config('app.frontend_url', 'https://marketplace.foodstuff.store') .
                       "/track_order?section_id={$order->section_id}";

            $orderList .= ($index + 1) . ". *{$order->order->order_number}*\n";
            $orderList .= "   Status: " . ucfirst($order->order->status) . "\n";
            $orderList .= "   Market: {$order->market->name}\n";
            $orderList .= "   Amount: ₦" . number_format($order->order->total_amount, 2) . "\n";
            $orderList .= "   🔗 Track: {$trackUrl}\n\n";
        }

        return $orderList;
    }

    private function handleHelp(WhatsappSession $session): string
    {
        return "🛒 *FoodStuff Store Bot Help*\n\n" .
               "📝 *Available Commands:*\n" .
               "• 'hi' or 'hello' - Start shopping\n" .
               "• 'track order' - Check your orders\n" .
               "• 'track all' - Get tracking links for all orders\n" .
               "• 'help' - Show this menu\n\n" .
               "🛍️ *How to Order:*\n" .
               "1. Say 'hi' to start\n" .
               "2. If you have previous orders, choose:\n" .
               "   • '1' for new order\n" .
               "   • '2' to track orders\n" .
               "3. Reply 'yes' to use the platform (new users)\n" .
               "4. Click the shopping link\n" .
               "5. Enter your delivery address\n" .
               "6. Choose from nearby markets\n" .
               "7. Browse and add products to cart\n" .
               "8. Complete your payment\n\n" .
               "📞 *Need Support?*\n" .
               "Contact us at support@foodstuff.store\n\n" .
               "Ready to shop? Just say 'hi'! 🚀";
    }

    private function handleUnrecognized(WhatsappSession $session, string $message): string
    {
        // If user is in choice selection mode, guide them back to the options
        if ($session->current_step === 'choice_selection') {
            return "❌ *Invalid choice*\n\n" .
                   "Please select one of the following options:\n\n" .
                   "📝 *Available options:*\n" .
                   "1️⃣ *Make a new order* - Start shopping for new items\n" .
                   "2️⃣ *Track previous orders* - Check status of your existing orders\n\n" .
                   "Reply with:\n" .
                   "• '1' or 'new order' for option 1\n" .
                   "• '2' or 'track orders' for option 2";
        }

        // Default response for unrecognized messages
        return "🤔 *I didn't quite understand that*\n\n" .
               "Let me help you get started:\n\n" .
               "• Say 'hi' or 'hello' to start shopping\n" .
               "• Say 'track order' to check your orders\n" .
               "• Say 'help' to see all commands\n\n" .
               "I'm here to help you order foodstuff items easily! 🛒\n\n" .
               "What would you like to do?";
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        return $miles * 1.609344; // Convert to kilometers
    }

    private function calculateDeliveryAmount(float $distance): float
    {
        $basePrice = 500; // Base delivery fee
        $perKmPrice = 50; // Additional fee per km

        return $basePrice + ($distance * $perKmPrice);
    }

    private function calculateDeliveryTime(float $distance): string
    {
        $baseTime = 30; // Base time in minutes
        $perKmTime = 2; // Additional time per km

        $totalMinutes = $baseTime + ($distance * $perKmTime);
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    private function generatePaymentUrl(Order $order, string $sectionId): string
    {
        // Generate Paystack payment URL using PaymentService
        $paymentService = app(\App\Services\PaymentService::class);
        $paymentData = $paymentService->initializePayment([
            'amount' => $order->total_amount * 100, // Convert to kobo
            'email' => 'customer@foodstuff.store', // You might want to collect email
            'reference' => $order->order_number,
            'callback_url' => config('app.frontend_url', 'https://marketplace.foodstuff.store') ,
            // . '/payment/callback',
            'metadata' => [
                'order_id' => $order->id,
                'section_id' => $sectionId,
                'website' => 'foodstuff-marketplace',
                'platform' => 'whatsapp-bot',
            ],
        ]);

        return $paymentData['authorization_url'] ?? '';
    }
}
