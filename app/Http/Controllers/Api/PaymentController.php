<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private OrderService $orderService
    ) {}

    public function initialize(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'email' => 'required|email',
        ]);

        try {
            $order = Order::findOrFail($request->order_id);

            if ($order->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order is not in pending status',
                ], 400);
            }

            $paymentData = $this->paymentService->initializePayment([
                'amount' => $order->total_amount * 100, // Convert to kobo
                'email' => $request->email,
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

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => 'required|string',
        ]);

        try {
            $verification = $this->paymentService->verifyPayment($request->reference);

            $order = Order::where('paystack_reference', $request->reference)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            if ($verification['status'] === 'success') {
                $order->update([
                    'status' => 'paid',
                    'payment_reference' => $request->reference,
                    'paid_at' => now(),
                ]);

                $order->updateStatus('paid', 'Payment verified successfully');

                // Assign agent automatically
                $this->orderService->assignAgent($order);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => 'paid',
                        'amount' => $verification['amount'],
                    ],
                ]);
            } else {
                $order->updateStatus('failed', 'Payment verification failed');

                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed',
                    'data' => [
                        'order_id' => $order->id,
                        'status' => 'failed',
                    ],
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function callback(Request $request): JsonResponse
    {
        try {
            $reference = $request->input('reference');

            if (!$reference) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reference not provided',
                ], 400);
            }

            $verification = $this->paymentService->verifyPayment($reference);

            $order = Order::where('paystack_reference', $reference)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            if ($verification['status'] === 'success') {
                $order->update([
                    'status' => 'paid',
                    'payment_reference' => $reference,
                    'paid_at' => now(),
                ]);

                $order->updateStatus('paid', 'Payment completed via callback');

                // Assign agent automatically
                $this->orderService->assignAgent($order);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => 'paid',
                    ],
                ]);
            } else {
                $order->updateStatus('failed', 'Payment failed via callback');

                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed',
                    'data' => [
                        'order_id' => $order->id,
                        'status' => 'failed',
                    ],
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
