<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $apiKey;
    private string $channelId;
    private string $senderId;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.termii.api_key') ?? '';
        $this->channelId = config('services.termii.channel_id') ?? '';
        $this->senderId = config('services.termii.sender_id') ?? '';
        $this->baseUrl = config('services.termii.base_url') ?? 'https://api.ng.termii.com/api';
    }

    public function sendMessage(string $phone, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/whatsapp/send", [
                'api_key' => $this->apiKey,
                'channel_id' => $this->channelId,
                'from' => $this->senderId,
                'to' => $phone,
                'type' => 'text',
                'text' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("WhatsApp message sent successfully", [
                    'phone' => $phone,
                    'message_id' => $data['message_id'] ?? null,
                ]);
                return true;
            }

            Log::error("Failed to send WhatsApp message", [
                'phone' => $phone,
                'response' => $response->body(),
                'status' => $response->status(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error("WhatsApp service error", [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getMessageStatus(string $messageId): ?array
    {
        try {
            $response = Http::get("{$this->baseUrl}/insights/message-status", [
                'api_key' => $this->apiKey,
                'message_id' => $messageId,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get message status", [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function sendPaymentLink(string $phone, string $paymentUrl): bool
    {
        $message = "💳 *Payment Link*\n\n" .
                   "Click the link below to complete your payment:\n\n" .
                   "{$paymentUrl}\n\n" .
                   "Payment is secure and powered by Paystack. 🔒";

        return $this->sendMessage($phone, $message);
    }

    public function sendDeliveryUpdate(string $phone, string $status, string $message = ''): bool
    {
        $statusMessages = [
            'preparing' => '👨‍🍳 *Order Update*\n\nYour order is being prepared!',
            'ready_for_delivery' => '🚚 *Order Ready*\n\nYour order is ready for delivery!',
            'out_for_delivery' => '🛵 *Out for Delivery*\n\nYour order is on its way!',
            'delivered' => '✅ *Order Delivered*\n\nYour order has been delivered! Enjoy! 🎉'
        ];

        $statusMessage = $statusMessages[$status] ?? "📦 *Order Update*\n\n{$message}";

        if ($message) {
            $statusMessage .= "\n\n{$message}";
        }

        return $this->sendMessage($phone, $statusMessage);
    }

    public function sendOrderPreparing(string $phone, string $orderNumber): bool
    {
        $message = "👨‍🍳 *Order Update*\n\n" .
                   "Your order #{$orderNumber} is being prepared!\n\n" .
                   "We'll notify you when it's ready for delivery. 🚚";

        return $this->sendMessage($phone, $message);
    }

    public function sendOrderReady(string $phone, string $orderNumber): bool
    {
        $message = "🚚 *Order Ready*\n\n" .
                   "Your order #{$orderNumber} is ready for delivery!\n\n" .
                   "Our delivery agent will contact you soon. 📞";

        return $this->sendMessage($phone, $message);
    }

    public function sendOrderOutForDelivery(string $phone, string $orderNumber): bool
    {
        $message = "🛵 *Out for Delivery*\n\n" .
                   "Your order #{$orderNumber} is on its way!\n\n" .
                   "Please keep your phone nearby for delivery updates. 📱";

        return $this->sendMessage($phone, $message);
    }

    public function sendOrderDelivered(string $phone, string $orderNumber): bool
    {
        $message = "✅ *Order Delivered*\n\n" .
                   "Your order #{$orderNumber} has been delivered!\n\n" .
                   "Thank you for choosing FoodStuff Store! 🎉\n\n" .
                   "Enjoy your items! 🛒✨";

        return $this->sendMessage($phone, $message);
    }

    public function sendPaymentSuccess(string $phone, string $orderNumber, float $amount): bool
    {
        $message = "💳 *Payment Successful*\n\n" .
                   "Your payment for order #{$orderNumber} has been confirmed!\n\n" .
                   "💰 Amount: ₦" . number_format($amount, 2) . "\n\n" .
                   "Your order is now being processed. 🚀";

        return $this->sendMessage($phone, $message);
    }

    public function sendPaymentFailed(string $phone, string $orderNumber): bool
    {
        $message = "❌ *Payment Failed*\n\n" .
                   "Your payment for order #{$orderNumber} was unsuccessful.\n\n" .
                   "Please try again or contact support for assistance. 📞";

        return $this->sendMessage($phone, $message);
    }

    public function sendOrderCancelled(string $phone, string $orderNumber, string $reason = ''): bool
    {
        $message = "🚫 *Order Cancelled*\n\n" .
                   "Your order #{$orderNumber} has been cancelled.";

        if ($reason) {
            $message .= "\n\nReason: {$reason}";
        }

        $message .= "\n\nPlease place a new order if needed. 🛒";

        return $this->sendMessage($phone, $message);
    }

    public function sendAgentReassigned(string $phone, string $orderNumber, string $agentName): bool
    {
        $message = "👤 *Agent Reassigned*\n\n" .
                   "Your order #{$orderNumber} has been reassigned to {$agentName}.\n\n" .
                   "You'll receive updates from your new agent. 📱";

        return $this->sendMessage($phone, $message);
    }
}
