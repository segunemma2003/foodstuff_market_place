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
    public function getItems(Order $order): JsonResponse
    {
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
        ]);
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

    public function show(Order $order): JsonResponse
    {
        $order->load(['orderItems.product', 'market', 'agent']);

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
                'updated_at' => $order->updated_at,
            ],
        ]);
    }

    public function getCartPrices(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => 'required|exists:markets,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.measurement_scale' => 'required|string|max:50',
        ]);

        try {
            $marketId = $request->market_id;
            $items = $request->items;
            $pricedItems = [];

            foreach ($items as $item) {
                // Find the market product
                $marketProduct = MarketProduct::with(['product.category', 'productPrices', 'agent'])
                    ->where('market_id', $marketId)
                    ->where('product_id', $item['product_id'])
                    ->where('is_available', true)
                    ->first();

                if (!$marketProduct) {
                    return response()->json([
                        'success' => false,
                        'message' => "Product not available in selected market",
                        'product_id' => $item['product_id'],
                    ], 400);
                }

                // Find the specific price for the measurement scale
                $productPrice = $marketProduct->productPrices()
                    ->where('measurement_scale', $item['measurement_scale'])
                    ->where('is_available', true)
                    ->first();

                if (!$productPrice) {
                    return response()->json([
                        'success' => false,
                        'message' => "Measurement scale '{$item['measurement_scale']}' not available for this product",
                        'product_id' => $item['product_id'],
                        'measurement_scale' => $item['measurement_scale'],
                    ], 400);
                }

                $pricedItems[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $marketProduct->product_name ?? $marketProduct->product->name,
                    'base_product_name' => $marketProduct->product->name,
                    'category' => $marketProduct->product->category->name,
                    'image' => $marketProduct->product->image,
                    'measurement_scale' => $item['measurement_scale'],
                    'unit_price' => $productPrice->price,
                    'agent_name' => $marketProduct->agent->full_name,
                    'agent_id' => $marketProduct->agent_id,
                    'stock_available' => $productPrice->stock_quantity,
                    'is_available' => $productPrice->is_available,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'market_id' => $marketId,
                    'items' => $pricedItems,
                    'item_count' => count($pricedItems),
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
     * Get all measurement scales and prices for products in a market
     */
    public function getMarketProductPrices(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => 'required|exists:markets,id',
        ]);

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
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01', // Allow decimal quantities
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.measurement_scale' => 'required|string|max:50', // Add measurement scale
        ]);

        try {
            // Clear existing items
            $order->orderItems()->delete();

            // Add new items
            $subtotal = 0;
            foreach ($request->items as $item) {
                $orderItem = $order->orderItems()->create([
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'measurement_scale' => $item['measurement_scale'], // Store measurement scale
                    'total_price' => $item['quantity'] * $item['unit_price'],
                ]);

                $subtotal += $orderItem->total_price;
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
                    'items_count' => count($request->items),
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
