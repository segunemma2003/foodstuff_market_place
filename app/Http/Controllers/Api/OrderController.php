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
        ]);

        try {
            // Delete existing items
            $order->orderItems()->delete();

            $subtotal = 0;

            // Add new items
            foreach ($request->items as $item) {
                $totalPrice = $item['quantity'] * $item['unit_price'];
                $subtotal += $totalPrice;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'market_product_id' => $item['market_product_id'] ?? null,
                    'product_name' => $item['product_name'] ?? '',
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'total_price' => $totalPrice,
                ]);
            }

            $deliveryFee = $order->delivery_fee ?? 0;
            $totalAmount = $subtotal + $deliveryFee;

            $order->update([
                'subtotal' => $subtotal,
                'total_amount' => $totalAmount,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'total_amount' => $totalAmount,
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
            'payment_method' => 'required|in:paystack',
        ]);

        try {
            // Initialize payment
            $paymentData = $this->paymentService->initializePayment([
                'amount' => $order->total_amount * 100, // Convert to kobo
                'email' => $request->email ?? 'customer@example.com',
                'reference' => $order->order_number,
                'callback_url' => config('app.frontend_url') . '/payment/callback',
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ]);

            $order->update([
                'paystack_reference' => $paymentData['reference'],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_url' => $paymentData['authorization_url'],
                    'reference' => $paymentData['reference'],
                    'amount' => $order->total_amount,
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
