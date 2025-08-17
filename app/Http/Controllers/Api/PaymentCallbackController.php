<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use App\Models\Order;
use App\Models\WhatsappSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    public function __construct(
        private WhatsAppService $whatsAppService
    ) {}

    public function handlePaymentCallback(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'section_id' => 'required|string',
                'payment_status' => 'required|string|in:success,failed',
                'transaction_reference' => 'required|string',
                'amount' => 'required|numeric',
                'message' => 'nullable|string',
            ]);

            $session = WhatsappSession::where('section_id', $request->section_id)->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found',
                ], 404);
            }

            $order = $session->order;
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found for this section',
                ], 404);
            }

            if ($request->payment_status === 'success') {
                // Update order status
                $order->update([
                    'status' => 'paid',
                    'payment_reference' => $request->transaction_reference,
                    'paystack_reference' => $request->transaction_reference,
                    'paid_at' => now(),
                ]);

                // Update session status
                $session->update([
                    'status' => 'paid',
                    'last_activity' => now(),
                ]);

                // Send success notification with section info
                $this->whatsAppService->sendPaymentSuccess(
                    $session->whatsapp_number,
                    $order->order_number,
                    $request->amount,
                    $session->section_id
                );

                Log::info('Payment successful', [
                    'section_id' => $request->section_id,
                    'order_number' => $order->order_number,
                    'amount' => $request->amount,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully',
                    'order_status' => 'paid',
                    'section_status' => 'paid',
                ]);

            } else {
                // Payment failed
                $order->update([
                    'status' => 'payment_failed',
                    'payment_reference' => $request->transaction_reference,
                ]);

                // Update session status
                $session->update([
                    'status' => 'payment_failed',
                    'last_activity' => now(),
                ]);

                // Send failure notification with section info
                $this->whatsAppService->sendPaymentFailed(
                    $session->whatsapp_number,
                    $order->order_number,
                    $session->section_id
                );

                Log::warning('Payment failed', [
                    'section_id' => $request->section_id,
                    'order_number' => $order->order_number,
                    'message' => $request->message,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment failure recorded',
                    'order_status' => 'payment_failed',
                    'section_status' => 'payment_failed',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Payment callback error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error processing payment callback',
            ], 500);
        }
    }

    public function updateOrderStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'section_id' => 'required|string',
                'status' => 'required|string',
                'message' => 'nullable|string',
                'agent_id' => 'nullable|integer',
            ]);

            $session = WhatsappSession::where('section_id', $request->section_id)->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found',
                ], 404);
            }

            $order = $session->order;
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found for this section',
                ], 404);
            }

            // Update order status
            $order->updateStatus($request->status, $request->message ?? '');

            // Update session status based on order status
            $sessionStatus = $this->mapOrderStatusToSessionStatus($request->status);
            $session->update([
                'status' => $sessionStatus,
                'last_activity' => now(),
            ]);

            // Send WhatsApp notification
            $this->sendStatusNotification($session, $request->status, $request->message);

            Log::info('Order status updated', [
                'section_id' => $request->section_id,
                'order_number' => $order->order_number,
                'status' => $request->status,
                'message' => $request->message,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'order_status' => $request->status,
                'session_status' => $sessionStatus,
            ]);

        } catch (\Exception $e) {
            Log::error('Order status update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating order status',
            ], 500);
        }
    }

    private function mapOrderStatusToSessionStatus(string $orderStatus): string
    {
        $statusMap = [
            'pending' => 'ongoing',
            'confirmed' => 'ongoing',
            'paid' => 'paid',
            'assigned' => 'assigned',
            'preparing' => 'preparing',
            'ready_for_delivery' => 'ready_for_delivery',
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'payment_failed' => 'payment_failed',
        ];

        return $statusMap[$orderStatus] ?? 'ongoing';
    }

    private function sendStatusNotification(WhatsappSession $session, string $status, string $message = ''): void
    {
        $order = $session->order;
        if (!$order) return;

        // Use the new tracking method with section information
        $this->whatsAppService->sendOrderTrackingInfo(
            $session->whatsapp_number,
            $order->order_number,
            $status,
            $session->section_id
        );
    }
}
