<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WhatsappSession;
use App\Models\Agent;
use App\Models\AgentEarning;
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
        // Log the incoming webhook request
        Log::info('Paystack webhook received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            // Validate Paystack webhook format
            $request->validate([
                'event' => 'required|string',
                'data' => 'required|array',
                'data.reference' => 'required|string',
                'data.status' => 'required|string',
                'data.amount' => 'required|numeric',
                'data.metadata' => 'nullable|array',
            ]);

            Log::info('Webhook validation passed', [
                'event' => $request->event,
                'reference' => $request->data['reference'] ?? 'not_found',
                'status' => $request->data['status'] ?? 'not_found',
                'amount' => $request->data['amount'] ?? 'not_found',
            ]);

            // Only process charge.success events
            if ($request->event !== 'charge.success') {
                Log::info('Event ignored - not charge.success', [
                    'event' => $request->event,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Event ignored: ' . $request->event,
                ]);
            }

            $transactionData = $request->data;
            $reference = $transactionData['reference'];
            $status = $transactionData['status'];
            $amount = $transactionData['amount'] / 100; // Paystack amounts are in kobo
            $metadata = $transactionData['metadata'] ?? [];
            $sectionId = $metadata['section_id'] ?? null;

            Log::info('Processing transaction data', [
                'reference' => $reference,
                'status' => $status,
                'amount' => $amount,
                'metadata' => $metadata,
                'section_id' => $sectionId,
            ]);

            // Find order by reference (Paystack reference)
            $order = Order::where('paystack_reference', $reference)->first();
            if (!$order) {
                Log::warning('Order not found for Paystack reference', [
                    'reference' => $reference,
                    'event' => $request->event,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found for reference: ' . $reference,
                ], 404);
            }

            // Find the session by whatsapp number and section_id (if available)
            $session = null;
            if ($sectionId) {
                $session = WhatsappSession::where('whatsapp_number', $order->whatsapp_number)
                    ->where('section_id', $sectionId)
                    ->first();
            }

            // If session not found, try to find by whatsapp number only
            if (!$session) {
                $session = WhatsappSession::where('whatsapp_number', $order->whatsapp_number)
                    ->where('status', 'active')
                    ->first();
            }

            if (!$session) {
                Log::warning('WhatsApp session not found for order', [
                    'order_id' => $order->id,
                    'whatsapp_number' => $order->whatsapp_number,
                    'section_id' => $sectionId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp session not found for this order',
                ], 404);
            }

            if ($status === 'success') {
                // Update order status
                $order->update([
                    'status' => 'paid',
                    'payment_reference' => $reference,
                    'paystack_reference' => $reference,
                    'paid_at' => now(),
                ]);

                // Update session status and link order_id
                $session->update([
                    'status' => 'paid',
                    'order_id' => $order->id,
                    'last_activity' => now(),
                ]);

                // Automatically assign an agent (if available)
                try {
                    $this->assignAgentToOrder($order);
                } catch (\Exception $e) {
                    Log::warning('Could not assign agent automatically: ' . $e->getMessage(), [
                        'order_id' => $order->id,
                        'market_id' => $order->market_id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Continue with payment processing even if agent assignment fails
                }

                // Send payment success notification with agent details
                try {
                    $this->sendPaymentSuccessNotification($order, $session);
                } catch (\Exception $e) {
                    Log::error('Failed to send payment success notification: ' . $e->getMessage());
                }

                Log::info('Payment successful', [
                    'section_id' => $sectionId,
                    'order_number' => $order->order_number,
                    'amount' => $amount,
                    'reference' => $reference,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully',
                    'order_status' => 'paid',
                    'section_status' => 'paid',
                ]);

            } else {
                // Payment failed or other status
                $order->update([
                    'status' => 'payment_failed',
                    'payment_reference' => $reference,
                ]);

                // Update session status
                $session->update([
                    'status' => 'payment_failed',
                    'last_activity' => now(),
                ]);

                // Send payment failure notification
                try {
                    $this->sendPaymentFailureNotification($order, $session);
                } catch (\Exception $e) {
                    Log::error('Failed to send payment failure notification: ' . $e->getMessage());
                }

                Log::warning('Payment failed', [
                    'section_id' => $sectionId,
                    'order_number' => $order->order_number,
                    'status' => $status,
                    'reference' => $reference,
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

    private function assignAgentToOrder(Order $order): void
    {
        Log::info('Attempting to assign agent to order', [
            'order_id' => $order->id,
            'market_id' => $order->market_id,
        ]);

        // Find available agent in the market
        $availableAgent = Agent::where('market_id', $order->market_id)
            ->where('is_active', true)
            ->where('is_suspended', false)
            ->first();

        if (!$availableAgent) {
            Log::warning('No available agents for order', [
                'order_id' => $order->id,
                'market_id' => $order->market_id,
                'available_agents_count' => Agent::where('market_id', $order->market_id)->count(),
                'active_agents_count' => Agent::where('market_id', $order->market_id)->where('is_active', true)->count(),
            ]);
            // Don't throw exception, just return without assigning agent
            return;
        }

        Log::info('Found available agent', [
            'order_id' => $order->id,
            'agent_id' => $availableAgent->id,
            'agent_name' => $availableAgent->full_name,
        ]);

        // Update order with agent
        $order->update([
            'agent_id' => $availableAgent->id,
        ]);

        // Update order status to assigned
        $order->updateStatus('assigned', "Order assigned to agent {$availableAgent->full_name}");

        // Create agent earning record
        AgentEarning::create([
            'agent_id' => $availableAgent->id,
            'order_id' => $order->id,
            'amount' => $order->total_amount * 0.1, // 10% commission
            'status' => 'pending',
        ]);

        // Send agent assignment notification using the new format
        $this->sendWhatsAppStatusUpdate($order);

        Log::info('Agent assigned to order successfully', [
            'order_id' => $order->id,
            'agent_id' => $availableAgent->id,
            'agent_name' => $availableAgent->full_name,
        ]);
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
            $whatsappBotUrl = 'https://foodstuff-whatsapp-bot-1aeb07cc3b64.herokuapp.com';
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

    /**
     * Send WhatsApp status update using the new format
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
            $response = Http::post($whatsappBotUrl . '/order-status-update', $data);

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

    /**
     * Send payment success notification with agent details
     */
    private function sendPaymentSuccessNotification(Order $order, WhatsappSession $session): void
    {
        $whatsappBotUrl = 'https://foodstuff-whatsapp-bot-1aeb07cc3b64.herokuapp.com';

        // Load the agent relationship if not already loaded
        if (!$order->relationLoaded('agent')) {
            $order->load('agent');
        }

        $message = "Payment confirmed! Your order has been paid successfully.\n\nOrder: {$order->order_number}\nAmount: ₦" . number_format($order->total_amount / 100, 2);

        // Add agent information if agent is assigned
        if ($order->agent) {
            $message .= "\n\nAgent assigned: {$order->agent->full_name}\nPhone: {$order->agent->phone}";
        }

        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => 'paid',
            'message' => $message,
            'whatsapp_number' => $order->whatsapp_number,
        ];

        // Send to WhatsApp bot
        try {
            $response = Http::post($whatsappBotUrl . '/order-status-update', $data);

            if ($response->successful()) {
                Log::info('Payment success notification sent successfully', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);
            } else {
                Log::error('Failed to send payment success notification', [
                    'order_id' => $order->id,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending payment success notification: ' . $e->getMessage());
        }
    }

    /**
     * Send payment failure notification
     */
    private function sendPaymentFailureNotification(Order $order, WhatsappSession $session): void
    {
        $whatsappBotUrl = 'https://foodstuff-whatsapp-bot-1aeb07cc3b64.herokuapp.com';

        $message = "Payment failed! Your order payment was not successful.\n\nOrder: {$order->order_number}\nAmount: ₦" . number_format($order->total_amount / 100, 2) . "\n\nPlease try again or contact support.";

        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => 'payment_failed',
            'message' => $message,
            'whatsapp_number' => $order->whatsapp_number,
        ];

        // Send to WhatsApp bot
        try {
            $response = Http::post($whatsappBotUrl . '/order-status-update', $data);

            if ($response->successful()) {
                Log::info('Payment failure notification sent successfully', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);
            } else {
                Log::error('Failed to send payment failure notification', [
                    'order_id' => $order->id,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending payment failure notification: ' . $e->getMessage());
        }
    }
}
