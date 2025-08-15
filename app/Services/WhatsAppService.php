<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $baseUrl;
    private string $apiKey;
    private string $channelId;
    private string $senderId;

    public function __construct()
    {
        $this->baseUrl = config('services.termii.base_url', 'https://api.ng.termii.com/api');
        $this->apiKey = config('services.termii.api_key') ?? '';
        $this->channelId = config('services.termii.channel_id') ?? '';
        $this->senderId = config('services.termii.sender_id') ?? '';
    }

    public function sendMessage(string $to, string $message): bool
    {
        try {
            // Remove any non-numeric characters and ensure it starts with country code
            $phoneNumber = $this->formatPhoneNumber($to);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/send-message", [
                'api_key' => $this->apiKey,
                'to' => $phoneNumber,
                'from' => $this->senderId,
                'type' => 'text',
                'channel' => 'whatsapp',
                'sms' => $message,
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                if (isset($responseData['code']) && $responseData['code'] === 'ok') {
                    Log::info('Termii WhatsApp message sent successfully', [
                        'to' => $phoneNumber,
                        'message' => $message,
                        'message_id' => $responseData['message_id'] ?? null,
                    ]);
                    return true;
                }
            }

            Log::error('Termii WhatsApp message failed', [
                'to' => $phoneNumber,
                'response' => $response->json(),
                'status' => $response->status(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Termii WhatsApp service error', [
                'error' => $e->getMessage(),
                'to' => $to,
            ]);
            return false;
        }
    }

    public function sendOrderStatusUpdate(string $to, string $orderNumber, string $status, string $message = ''): bool
    {
        $statusMessage = "📦 Order Update\n\n";
        $statusMessage .= "Order #: {$orderNumber}\n";
        $statusMessage .= "Status: {$status}\n";

        if ($message) {
            $statusMessage .= "\n{$message}";
        }

        return $this->sendMessage($to, $statusMessage);
    }

    public function sendOrderConfirmation(string $to, string $orderNumber, float $amount): bool
    {
        $message = "✅ Order Confirmed!\n\n";
        $message .= "Order #: {$orderNumber}\n";
        $message .= "Amount: ₦" . number_format($amount, 2) . "\n\n";
        $message .= "Your order has been confirmed and is being processed. We'll keep you updated on the progress.";

        return $this->sendMessage($to, $message);
    }

    public function sendAgentAssignment(string $to, string $orderNumber, string $agentName, string $agentPhone): bool
    {
        $message = "👨‍💼 Agent Assigned\n\n";
        $message .= "Order #: {$orderNumber}\n";
        $message .= "Agent: {$agentName}\n";
        $message .= "Phone: {$agentPhone}\n\n";
        $message .= "Your agent will contact you shortly to confirm your order details.";

        return $this->sendMessage($to, $message);
    }

    public function sendPaymentLink(string $to, string $orderNumber, string $paymentUrl): bool
    {
        $message = "💳 Payment Required\n\n";
        $message .= "Order #: {$orderNumber}\n\n";
        $message .= "Please complete your payment using the link below:\n";
        $message .= "{$paymentUrl}\n\n";
        $message .= "Your order will be processed once payment is confirmed.";

        return $this->sendMessage($to, $message);
    }

    public function sendDeliveryUpdate(string $to, string $orderNumber, string $status, string $estimatedTime = ''): bool
    {
        $message = "🚚 Delivery Update\n\n";
        $message .= "Order #: {$orderNumber}\n";
        $message .= "Status: {$status}\n";

        if ($estimatedTime) {
            $message .= "Estimated Time: {$estimatedTime}\n";
        }

        return $this->sendMessage($to, $message);
    }

    public function sendOrderPreparing(string $to, string $orderNumber, string $agentName, string $estimatedTime = '30 minutes'): bool
    {
        $message = "🛒 Order Status Update\n\n";
        $message .= "Order #: {$orderNumber}\n";
        $message .= "Status: Preparing\n";
        $message .= "Agent: {$agentName}\n";
        $message .= "Estimated Time: {$estimatedTime}\n\n";
        $message .= "Your order is being prepared. We'll notify you when it's ready for delivery!";

        return $this->sendMessage($to, $message);
    }

    public function sendOrderReady(string $to, string $orderNumber, string $agentName): bool
    {
        $message = "✅ Order Status Update\n\n";
        $message .= "Order #: {$orderNumber}\n";
        $message .= "Status: Ready for Delivery\n";
        $message .= "Agent: {$agentName}\n\n";
        $message .= "Your order is ready! Your agent will be on the way shortly.";

        return $this->sendMessage($to, $message);
    }

    public function sendOrderOutForDelivery(string $to, string $orderNumber, string $agentName, string $agentPhone): bool
    {
        $message = "🚚 Order Status Update\n\n";
        $message .= "Order #: {$orderNumber}\n";
        $message .= "Status: Out for Delivery\n";
        $message .= "Agent: {$agentName}\n";
        $message .= "Phone: {$agentPhone}\n\n";
        $message .= "Your order is on the way! Your agent will contact you when they arrive.";

        return $this->sendMessage($to, $message);
    }

    public function sendOrderDelivered(string $to, string $orderNumber, string $agentName): bool
    {
        $message = "🎉 *Order Status Update*\n\n";
        $message .= "Order #: {$orderNumber}\n";
        $message .= "Status: Delivered\n";
        $message .= "Agent: {$agentName}\n\n";
        $message .= "Your order has been delivered successfully!\n\n";
        $message .= "✅ *Order Complete*\n\n";
        $message .= "Thank you for choosing FoodStuff Store! 🛒\n\n";
        $message .= "💡 *Ready to order again?*\n";
        $message .= "Just send us a message anytime to start a new order!\n\n";
        $message .= "📞 *Need help?*\n";
        $message .= "Call: +234 801 234 5678\n";
        $message .= "Email: support@foodstuff.store";

        return $this->sendMessage($to, $message);
    }

    public function sendPaymentSuccess(string $to, string $orderNumber, float $amount): bool
    {
        $message = "💳 Payment Successful!\n\n";
        $message .= "Order #: {$orderNumber}\n";
        $message .= "Amount: ₦" . number_format($amount, 2) . "\n\n";
        $message .= "Your payment has been confirmed! We're now processing your order.\n\n";
        $message .= "You'll receive updates as your order progresses.";

        return $this->sendMessage($to, $message);
    }

    public function sendPaymentFailed(string $to, string $orderNumber, string $reason = ''): bool
    {
        $message = "❌ Payment Failed\n\n";
        $message .= "Order #: {$orderNumber}\n\n";

        if ($reason) {
            $message .= "Reason: {$reason}\n\n";
        }

        $message .= "Please try again or contact support:\n";
        $message .= "📞 +234 801 234 5678\n";
        $message .= "📧 support@foodstuff.store";

        return $this->sendMessage($to, $message);
    }

    public function sendOrderCancelled(string $to, string $orderNumber, string $reason = ''): bool
    {
        $message = "❌ Order Cancelled\n\n";
        $message .= "Order #: {$orderNumber}\n\n";

        if ($reason) {
            $message .= "Reason: {$reason}\n\n";
        }

        $message .= "Your order has been cancelled. If you have any questions, please contact us:\n";
        $message .= "📞 +234 801 234 5678\n";
        $message .= "📧 support@foodstuff.store\n\n";
        $message .= "Ready to order again? Just send us a message!";

        return $this->sendMessage($to, $message);
    }

    public function sendAgentReassigned(string $to, string $orderNumber, string $newAgentName, string $newAgentPhone): bool
    {
        $message = "👨‍💼 Agent Reassigned\n\n";
        $message .= "Order #: {$orderNumber}\n";
        $message .= "New Agent: {$newAgentName}\n";
        $message .= "Phone: {$newAgentPhone}\n\n";
        $message .= "Your order has been reassigned to a new agent. They will contact you shortly.";

        return $this->sendMessage($to, $message);
    }

    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If it doesn't start with 234 (Nigeria), add it
        if (!str_starts_with($phone, '234')) {
            // If it starts with 0, replace with 234
            if (str_starts_with($phone, '0')) {
                $phone = '234' . substr($phone, 1);
            } else {
                // If it's 11 digits (local format), add 234
                if (strlen($phone) === 11) {
                    $phone = '234' . substr($phone, 1);
                }
            }
        }

        return $phone;
    }

    public function getMessageStatus(string $messageId): array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/get-message-status", [
                'api_key' => $this->apiKey,
                'message_id' => $messageId,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return ['error' => 'Failed to get message status'];
        } catch (\Exception $e) {
            Log::error('Failed to get Termii message status', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
            ]);
            return ['error' => $e->getMessage()];
        }
    }
}
