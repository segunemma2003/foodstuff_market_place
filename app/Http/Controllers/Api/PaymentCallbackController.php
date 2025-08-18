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
use Yabacon\Paystack;

class PaymentCallbackController extends Controller
{
    protected $paystack;

    public function __construct()
    {
        $this->paystack = new Paystack(config('services.paystack.secret_key'));
    }

    public function handlePaymentCallback(Request $request): JsonResponse
    {
        // Handle OPTIONS requests for CORS preflight
        if ($request->isMethod('OPTIONS')) {
            Log::info('CORS preflight request received for Paystack webhook');
            return response()->json(['success' => true, 'message' => 'CORS preflight OK'], 200);
        }

        // Handle GET requests (some webhook providers might send GET for testing)
        if ($request->isMethod('GET')) {
            Log::info('GET request received for Paystack webhook', [
                'query' => $request->query(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            return response()->json(['success' => true, 'message' => 'Webhook endpoint is working'], 200);
        }

        // Log the incoming webhook request
        Log::info('Paystack webhook received', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
        ]);

        try {
            // Use Paystack's built-in webhook verification
            $webhookSecret = config('services.paystack.webhook_secret');
            if (!$webhookSecret) {
                Log::error('Paystack webhook secret not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'Webhook secret not configured',
                ], 500);
            }

            // Get the raw request body
            $rawBody = $request->getContent();
            $signature = $request->header('X-Paystack-Signature');

            Log::info('Webhook verification details', [
                'raw_body_length' => strlen($rawBody),
                'signature' => $signature,
                'webhook_secret_configured' => !empty($webhookSecret),
            ]);

            // Verify the webhook signature using Paystack's method
            if (!$this->verifyWebhookSignature($rawBody, $signature, $webhookSecret)) {
                Log::warning('Paystack webhook signature verification failed', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'method' => $request->method(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid signature',
                ], 401);
            }

            Log::info('Paystack webhook signature verified successfully');

            // Parse the webhook data
            $webhookData = json_decode($rawBody, true);
            if (!$webhookData) {
                Log::error('Invalid JSON in webhook body');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON',
                ], 400);
            }

            Log::info('Webhook data parsed', [
                'event' => $webhookData['event'] ?? 'not_found',
                'data_keys' => array_keys($webhookData['data'] ?? []),
            ]);

            // Only process charge.success events
            if (($webhookData['event'] ?? '') !== 'charge.success') {
                Log::info('Event ignored - not charge.success', [
                    'event' => $webhookData['event'] ?? 'not_found',
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Event ignored: ' . ($webhookData['event'] ?? 'unknown'),
                ]);
            }

            $transactionData = $webhookData['data'];
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
                    'event' => $webhookData['event'],
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found for reference: ' . $reference,
                ], 402);
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

            Log::info('Order and session found', [
                'order_id' => $order->id,
                'order_status' => $order->status,
                'session_found' => $session ? true : false,
                'session_id' => $session?->id,
            ]);

            // Update order status based on Paystack status
            if ($status === 'success') {
                $order->status = 'paid';
                $order->paid_at = now();
                $order->save();

                Log::info('Order marked as paid', [
                    'order_id' => $order->id,
                    'reference' => $reference,
                ]);

                // Assign agent to the order
                $assignedAgent = $this->assignAgentToOrder($order);
                if ($assignedAgent) {
                    Log::info('Agent assigned to order', [
                        'order_id' => $order->id,
                        'agent_id' => $assignedAgent->id,
                        'agent_name' => $assignedAgent->full_name,
                    ]);
                }

                // Send payment success notification
                $this->sendPaymentSuccessNotification($order, $assignedAgent);

            } elseif ($status === 'failed') {
                $order->status = 'failed';
                $order->save();

                Log::info('Order marked as failed', [
                    'order_id' => $order->id,
                    'reference' => $reference,
                ]);

                // Send payment failure notification
                $this->sendPaymentFailureNotification($order);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing Paystack webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
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
                    'message' => 'Session not found',
                ], 404);
            }

            // Update session status
            $session->update([
                'status' => $request->status,
                'last_activity' => now(),
            ]);

            // If agent_id is provided, update the order
            if ($request->agent_id && $session->order_id) {
                $order = Order::find($session->order_id);
                if ($order) {
                    $order->update(['agent_id' => $request->agent_id]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating order status: ' . $e->getMessage());
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

    /**
     * Verify Paystack webhook signature
     */
    private function verifyWebhookSignature(string $rawBody, ?string $signature, string $secret): bool
    {
        if (!$signature) {
            Log::warning('No Paystack signature header found');
            return false;
        }

        $expectedSignature = hash_hmac('sha512', $rawBody, $secret);

        Log::info('Signature verification', [
            'expected' => $expectedSignature,
            'received' => $signature,
            'matches' => hash_equals($expectedSignature, $signature),
        ]);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Assign an agent to the order
     */
    private function assignAgentToOrder(Order $order): ?Agent
    {
        try {
            // Find available agents in the order's market
            $availableAgents = Agent::where('market_id', $order->market_id)
                ->where('status', 'active')
                ->where('is_available', true)
                ->get();

            Log::info('Available agents found', [
                'market_id' => $order->market_id,
                'count' => $availableAgents->count(),
            ]);

            if ($availableAgents->isEmpty()) {
                Log::warning('No available agents found for market', [
                    'market_id' => $order->market_id,
                ]);
                return null;
            }

            // Select the first available agent (you can implement more sophisticated logic)
            $agent = $availableAgents->first();

            // Update order with agent assignment
            $order->agent_id = $agent->id;
            $order->status = 'assigned';
            $order->assigned_at = now();
            $order->save();

            // Send WhatsApp notification about agent assignment
            $this->sendWhatsAppStatusUpdate($order, 'assigned');

            return $agent;

        } catch (\Exception $e) {
            Log::error('Error assigning agent to order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
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
     * Send WhatsApp status update
     */
    private function sendWhatsAppStatusUpdate(Order $order, string $status): void
    {
        try {
            $statusMessages = [
                'assigned' => "Your order #{$order->order_number} has been assigned to an agent and is being prepared.",
                'preparing' => "Your order #{$order->order_number} is being prepared.",
                'ready' => "Your order #{$order->order_number} is ready for pickup/delivery.",
                'delivering' => "Your order #{$order->order_number} is out for delivery.",
                'delivered' => "Your order #{$order->order_number} has been delivered. Thank you for your business!",
                'cancelled' => "Your order #{$order->order_number} has been cancelled.",
            ];

            $message = $statusMessages[$status] ?? "Your order #{$order->order_number} status has been updated to: {$status}";

            // Add agent details for assigned status
            if ($status === 'assigned' && $order->agent) {
                $message .= "\n\nAssigned Agent:\n";
                $message .= "Name: {$order->agent->full_name}\n";
                $message .= "Phone: {$order->agent->phone}";
            }

            // Send via WhatsApp service
            $whatsappService = app(\App\Services\WhatsAppService::class);
            $whatsappService->sendMessage($order->whatsapp_number, $message);

            Log::info('WhatsApp status update sent', [
                'order_id' => $order->id,
                'status' => $status,
                'phone' => $order->whatsapp_number,
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp status update', [
                'order_id' => $order->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send payment success notification
     */
    private function sendPaymentSuccessNotification(Order $order, ?Agent $agent): void
    {
        try {
            $message = "🎉 Payment Successful!\n\n";
            $message .= "Order #{$order->order_number}\n";
            $message .= "Amount: ₦" . number_format($order->total_amount, 2) . "\n";
            $message .= "Status: Payment confirmed\n\n";

            if ($agent) {
                $message .= "Your order has been assigned to:\n";
                $message .= "Agent: {$agent->full_name}\n";
                $message .= "Phone: {$agent->phone}\n\n";
                $message .= "You will be contacted shortly for delivery.";
            } else {
                $message .= "Your order is being processed and will be assigned to an agent shortly.";
            }

            // Send via WhatsApp service
            $whatsappService = app(\App\Services\WhatsAppService::class);
            $whatsappService->sendMessage($order->whatsapp_number, $message);

            Log::info('Payment success notification sent', [
                'order_id' => $order->id,
                'phone' => $order->whatsapp_number,
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending payment success notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send payment failure notification
     */
    private function sendPaymentFailureNotification(Order $order): void
    {
        try {
            $message = "❌ Payment Failed\n\n";
            $message .= "Order #{$order->order_number}\n";
            $message .= "Amount: ₦" . number_format($order->total_amount, 2) . "\n";
            $message .= "Status: Payment failed\n\n";
            $message .= "Please try again or contact support if the issue persists.";

            // Send via WhatsApp service
            $whatsappService = app(\App\Services\WhatsAppService::class);
            $whatsappService->sendMessage($order->whatsapp_number, $message);

            Log::info('Payment failure notification sent', [
                'order_id' => $order->id,
                'phone' => $order->whatsapp_number,
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending payment failure notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
