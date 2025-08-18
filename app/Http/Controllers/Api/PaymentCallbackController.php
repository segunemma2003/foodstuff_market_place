<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Agent;
use App\Services\WhatsAppService;

class PaymentCallbackController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function handleWebhook(Request $request)
    {
        Log::info('Paystack webhook received', [
            'headers' => $request->headers->all(),
            'body' => $request->getContent()
        ]);

        // First check if the header is present. Else, terminate the code.
        if (!$request->hasHeader("x-paystack-signature")) {
            Log::error('Paystack webhook: No signature header present');
            return response()->json(['error' => 'No signature header present'], 400);
        }

        // Get our paystack secret key from our .env file
        $secret = config('services.paystack.secret_key');

        // Validate the signature
        $expectedSignature = hash_hmac("sha512", $request->getContent(), $secret);
        $receivedSignature = $request->header("x-paystack-signature");

        if ($receivedSignature !== $expectedSignature) {
            Log::error('Paystack webhook: Invalid signature', [
                'expected' => $expectedSignature,
                'received' => $receivedSignature
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // If our code reaches here, then the request is valid from paystack.
        // We can go ahead and handle it
        Log::info('Paystack webhook signature verified successfully');

        $event = $request->event; // event type. e.g charge.success
        $data = $request->data; // request payload.

        // Log the full payload for debugging
        Log::info('PAYSTACK PAYLOAD', [
            'event' => $event,
            'data' => $data
        ]);

        if ($event === "charge.success") {
            return $this->handleChargeSuccess($data);
        }

        // For other events, just log them
        Log::info('Unhandled Paystack event', ['event' => $event]);

        return response()->json(['status' => 'success'], 200);
    }

    protected function handleChargeSuccess($data)
    {
        try {
            // Transaction info
            $reference = $data["reference"];
            $amount = $data["amount"];
            $status = $data["status"];

            Log::info('Processing charge.success', [
                'reference' => $reference,
                'amount' => $amount,
                'status' => $status
            ]);

            // Find the order by reference
            $order = Order::where('reference', $reference)->first();

            if (!$order) {
                Log::error('Order not found for reference', ['reference' => $reference]);
                return response()->json(['error' => 'Order not found'], 404);
            }

            Log::info('Order found', [
                'order_id' => $order->id,
                'current_status' => $order->status
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

                    // Send WhatsApp notification to customer about successful payment and agent assignment
                    $this->sendPaymentSuccessNotification($order, $assignedAgent);
                } else {
                    Log::warning('No agent available for order', ['order_id' => $order->id]);

                    // Send WhatsApp notification to customer about successful payment (no agent assigned yet)
                    $this->sendPaymentSuccessNotification($order, null);
                }
            } else {
                Log::warning('Payment failed', [
                    'order_id' => $order->id,
                    'status' => $status
                ]);

                $order->status = 'failed';
                $order->save();

                // Send WhatsApp notification about failed payment
                $this->sendPaymentFailureNotification($order);
            }

            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Error processing charge.success', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    protected function assignAgentToOrder($order)
    {
        // Find available agents (not currently assigned to another order)
        $availableAgent = Agent::where('is_active', true)
            ->whereDoesntHave('orders', function ($query) {
                $query->whereIn('status', ['pending', 'paid', 'assigned']);
            })
            ->first();

        if ($availableAgent) {
            $order->agent_id = $availableAgent->id;
            $order->status = 'assigned';
            $order->save();

            return $availableAgent;
        }

        return null;
    }

    protected function sendPaymentSuccessNotification($order, $agent = null)
    {
        try {
            $message = "🎉 Payment Successful!\n\n";
            $message .= "Order #{$order->id} has been paid successfully.\n";
            $message .= "Amount: ₦" . number_format($order->total_amount / 100, 2) . "\n\n";

            if ($agent) {
                $message .= "✅ Your order has been assigned to:\n";
                $message .= "Agent: {$agent->full_name}\n";
                $message .= "Phone: {$agent->phone}\n\n";
                $message .= "They will contact you shortly to arrange delivery.";
            } else {
                $message .= "⏳ Your order is being processed.\n";
                $message .= "An agent will be assigned to you shortly.";
            }

            $this->whatsappService->sendMessage($order->customer_phone, $message);

            Log::info('Payment success notification sent', [
                'order_id' => $order->id,
                'phone' => $order->customer_phone
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment success notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function sendPaymentFailureNotification($order)
    {
        try {
            $message = "❌ Payment Failed\n\n";
            $message .= "Order #{$order->id} payment was unsuccessful.\n";
            $message .= "Please try again or contact support for assistance.";

            $this->whatsappService->sendMessage($order->customer_phone, $message);

            Log::info('Payment failure notification sent', [
                'order_id' => $order->id,
                'phone' => $order->customer_phone
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment failure notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
