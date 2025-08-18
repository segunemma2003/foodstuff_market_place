<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Agent;
use App\Models\WhatsappSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\WhatsAppService;

class PaystackWebhookController extends Controller
{
    protected $whatsappService;

    public function __construct()
    {
        $this->whatsappService = app(WhatsAppService::class);
    }

    /**
     * Handle Paystack webhook exactly as per documentation
     */
    public function handle(Request $request)
    {
         Log::info('=== PAYSTACK WEBHOOK STARTED ===');
        Log::info('Webhook received', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'body_size' => strlen($request->getContent())
        ]);

        // Verify the webhook signature
        Log::info('Verifying Paystack signature...');
        try {
            // Log incoming request for debugging
            Log::info('Paystack webhook request received', [
                'method' => $request->method(),
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // 1. Get the raw request body (exactly as Paystack docs say)
            $payload = $request->getContent();

            // 2. Get the signature header
            $signature = $request->header('X-Paystack-Signature');

            Log::info('Webhook verification details', [
                'payload_length' => strlen($payload),
                'signature_present' => !empty($signature),
                'secret_configured' => !empty(config('services.paystack.secret_key')),
                'payload_preview' => substr($payload, 0, 100), // Add payload preview for debugging
            ]);

            // 3. Verify signature using HMAC-SHA512
            $secret = config('services.paystack.secret_key');

            if (empty($secret)) {
                Log::error('Paystack secret key not configured');
                return response()->json(['error' => 'Secret key not configured'], 500);
            }

            $expectedSignature = hash_hmac('sha512', $payload, $secret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning('Paystack webhook signature verification failed', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'expected' => $expectedSignature,
                    'received' => $signature,
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // 4. Parse the JSON payload
            $data = json_decode($payload, true);

            if (!$data) {
                Log::error('Invalid JSON in Paystack webhook payload', [
                    'payload' => $payload,
                    'json_error' => json_last_error_msg(),
                ]);
                return response()->json(['error' => 'Invalid JSON'], 400);
            }

            Log::info('Paystack webhook received', [
                'event' => $data['event'] ?? 'unknown',
                'reference' => $data['data']['reference'] ?? 'unknown',
                'full_data' => $data, // Add full data for debugging
            ]);

            // 5. Handle the event (only process events we care about)
            switch ($data['event']) {
                case 'charge.success':
                    $this->handleChargeSuccess($data['data']);
                    break;

                case 'charge.failed':
                    $this->handleChargeFailed($data['data']);
                    break;

                default:
                    // Acknowledge other events but don't process them
                    Log::info('Paystack webhook event ignored', [
                        'event' => $data['event'],
                    ]);
                    break;
            }

            // 6. Return 200 OK quickly (as per Paystack docs)
            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Error processing Paystack webhook', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->getContent(),
            ]);

            // Return 500 for server errors (Paystack will retry)
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle successful charge
     */
    private function handleChargeSuccess(array $transactionData): void
    {
        DB::beginTransaction();

        try {
            // Add null checks for required fields
            if (!isset($transactionData['reference'])) {
                Log::error('Missing reference in transaction data', [
                    'transaction_data' => $transactionData
                ]);
                DB::rollBack();
                return;
            }

            $reference = $transactionData['reference'];
            $amount = isset($transactionData['amount']) ? $transactionData['amount'] / 100 : 0;
            $metadata = $transactionData['metadata'] ?? [];

            Log::info('Processing successful charge', [
                'reference' => $reference,
                'amount' => $amount,
                'metadata' => $metadata,
            ]);

            // Find the order by Paystack reference
            $order = Order::where('paystack_reference', $reference)->first();

            if (!$order) {
                Log::warning('Order not found for Paystack reference', [
                    'reference' => $reference,
                ]);
                DB::rollBack();
                return;
            }

            // Update order status
            $order->status = 'paid';
            $order->paid_at = now();
            $order->save();

            Log::info('Order marked as paid', [
                'order_id' => $order->id,
                'reference' => $reference,
            ]);

            // Assign agent to the order
            $assignedAgent = $this->assignAgentToOrder($order);

            // Send WhatsApp notification
            $this->sendPaymentSuccessNotification($order, $assignedAgent);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error handling charge success', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'reference' => $reference ?? 'unknown',
            ]);
            throw $e;
        }
    }

    /**
     * Handle failed charge
     */
    private function handleChargeFailed(array $transactionData): void
    {
        try {
            // Add null checks for required fields
            if (!isset($transactionData['reference'])) {
                Log::error('Missing reference in failed transaction data', [
                    'transaction_data' => $transactionData
                ]);
                return;
            }

            $reference = $transactionData['reference'];
            $metadata = $transactionData['metadata'] ?? [];

            Log::info('Processing failed charge', [
                'reference' => $reference,
                'metadata' => $metadata,
            ]);

            // Find the order by Paystack reference
            $order = Order::where('paystack_reference', $reference)->first();

            if (!$order) {
                Log::warning('Order not found for failed Paystack reference', [
                    'reference' => $reference,
                ]);
                return;
            }

            // Update order status
            $order->status = 'failed';
            $order->save();

            Log::info('Order marked as failed', [
                'order_id' => $order->id,
                'reference' => $reference,
            ]);

            // Send WhatsApp notification
            $this->sendPaymentFailureNotification($order);

        } catch (\Exception $e) {
            Log::error('Error handling charge failure', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'reference' => $reference ?? 'unknown',
            ]);
            throw $e;
        }
    }

    /**
     * Assign an agent to the order
     */
    private function assignAgentToOrder(Order $order): ?Agent
    {
        try {
            // Add null check for market_id
            if (empty($order->market_id)) {
                Log::warning('Order has no market_id set', [
                    'order_id' => $order->id,
                ]);
                return null;
            }

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

            // Select the first available agent
            $agent = $availableAgents->first();

            // Update order with agent assignment
            $order->agent_id = $agent->id;
            $order->status = 'assigned';
            $order->assigned_at = now();
            $order->save();

            Log::info('Agent assigned to order', [
                'order_id' => $order->id,
                'agent_id' => $agent->id,
                'agent_name' => $agent->full_name,
            ]);

            return $agent;

        } catch (\Exception $e) {
            Log::error('Error assigning agent to order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }

    /**
     * Send payment success notification
     */
    private function sendPaymentSuccessNotification(Order $order, ?Agent $agent): void
    {
        try {
            // Add null check for whatsapp_number
            if (empty($order->whatsapp_number)) {
                Log::warning('Order has no WhatsApp number', [
                    'order_id' => $order->id,
                ]);
                return;
            }

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

            // Send via WhatsApp service with additional error handling
            if ($this->whatsappService) {
                $this->whatsappService->sendMessage($order->whatsapp_number, $message);
            } else {
                Log::warning('WhatsApp service not available', [
                    'order_id' => $order->id,
                ]);
            }

            Log::info('Payment success notification sent', [
                'order_id' => $order->id,
                'phone' => $order->whatsapp_number,
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending payment success notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * Send payment failure notification
     */
    private function sendPaymentFailureNotification(Order $order): void
    {
        try {
            // Add null check for whatsapp_number
            if (empty($order->whatsapp_number)) {
                Log::warning('Order has no WhatsApp number for failure notification', [
                    'order_id' => $order->id,
                ]);
                return;
            }

            $message = "❌ Payment Failed\n\n";
            $message .= "Order #{$order->order_number}\n";
            $message .= "Amount: ₦" . number_format($order->total_amount, 2) . "\n";
            $message .= "Status: Payment failed\n\n";
            $message .= "Please try again or contact support if the issue persists.";

            // Send via WhatsApp service with additional error handling
            if ($this->whatsappService) {
                $this->whatsappService->sendMessage($order->whatsapp_number, $message);
            } else {
                Log::warning('WhatsApp service not available for failure notification', [
                    'order_id' => $order->id,
                ]);
            }

            Log::info('Payment failure notification sent', [
                'order_id' => $order->id,
                'phone' => $order->whatsapp_number,
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending payment failure notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}
