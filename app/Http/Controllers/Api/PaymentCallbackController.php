<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WhatsappSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PaymentCallbackController extends Controller
{
    public function __construct()
    {}

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

            // Find order by order number (transaction reference)
            $order = Order::where('order_number', $request->transaction_reference)->first();
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found for order number: ' . $request->transaction_reference,
                ], 404);
            }

            // Find the session by whatsapp number and section_id
            $session = WhatsappSession::where('whatsapp_number', $order->whatsapp_number)
                ->where('section_id', $request->section_id)
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp session not found for this order',
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

                // Send success notification to WhatsApp bot (temporarily disabled for testing)
                // $this->sendWhatsAppNotification(
                //     $session->whatsapp_number,
                //     $order->order_number,
                //     'paid',
                //     'Payment successful',
                //     $session->section_id
                // );

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

                // Send failure notification to WhatsApp bot (temporarily disabled for testing)
                // $this->sendWhatsAppNotification(
                //     $session->whatsapp_number,
                //     $order->order_number,
                //     'payment_failed',
                //     'Payment failed',
                //     $session->section_id
                // );

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

        // Send notification to WhatsApp bot
        $this->sendWhatsAppNotification(
            $session->whatsapp_number,
            $order->order_number,
            $status,
            $message,
            $session->section_id
        );
    }

    private function sendWhatsAppNotification(string $whatsappNumber, string $orderNumber, string $status, string $message, ?string $sectionId = null): void
    {
        $whatsappBotUrl = env('WHATSAPP_BOT_URL', 'https://foodstuff-whatsapp-bot-6536aa3f6997.herokuapp.com');

        $data = [
            'section_id' => $sectionId,
            'status' => $status,
            'message' => $message,
            'whatsapp_number' => $whatsappNumber,
            'order_number' => $orderNumber,
        ];

        // Send to WhatsApp bot
        try {
            $response = Http::post($whatsappBotUrl . '/order-status-update', $data);

            if ($response->successful()) {
                Log::info('WhatsApp status notification sent successfully', [
                    'order_number' => $orderNumber,
                    'status' => $status,
                ]);
            } else {
                Log::error('Failed to send WhatsApp status notification', [
                    'order_number' => $orderNumber,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp status notification: ' . $e->getMessage());
        }
    }
}
