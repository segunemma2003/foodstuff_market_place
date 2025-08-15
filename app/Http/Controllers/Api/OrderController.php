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

    public function updateItems(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.product_name' => 'required|string|max:255',
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
}
